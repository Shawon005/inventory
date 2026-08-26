<?php

namespace Tests\Feature;

use App\Customer;
use App\ShopOwner;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerShopVisitApiTest extends TestCase
{
    private Migration $shopOwnerMigration;

    private Migration $shopOwnerDistributorMigration;

    private Migration $visitMigration;

    private Migration $dailyVisitMigration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('business', function (Blueprint $table) {
            $table->increments('id');
            $table->string('time_zone')->nullable();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('employee_type')->default('sr');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::table('business')->insert(['id' => 1, 'time_zone' => 'Asia/Dhaka']);
        $this->shopOwnerMigration = require database_path('migrations/2026_08_13_000001_create_shop_owners_table.php');
        $this->shopOwnerMigration->up();
        $this->shopOwnerDistributorMigration = require database_path('migrations/2026_08_13_000002_add_distributor_id_to_shop_owners_table.php');
        $this->shopOwnerDistributorMigration->up();
        $this->visitMigration = require database_path('migrations/2026_08_24_000003_create_customer_shop_visits_table.php');
        $this->visitMigration->up();
        $this->dailyVisitMigration = require database_path('migrations/2026_08_25_000001_add_daily_uniqueness_to_customer_shop_visits.php');
        $this->dailyVisitMigration->up();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('customer_shop_visits');
        Schema::dropIfExists('shop_owner_phones');
        Schema::dropIfExists('shop_owners');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('business');
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_sr_can_record_a_visit_at_the_nearest_shop_within_ten_meters(): void
    {
        $customer = Customer::create([
            'business_id' => 1,
            'name' => 'Test SR',
            'employee_type' => Customer::TYPE_SR,
        ]);
        $owner = ShopOwner::create([
            'distributor_id' => $customer->id,
            'name' => 'Nearby Shop',
            'email' => 'nearby@example.com',
            'latitude' => 23.81030000,
            'longitude' => 90.41250000,
            'address' => 'Dhaka',
        ]);
        $owner->phones()->create(['phone' => '01700000000']);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/customer/shop-visits', [
            'latitude' => 23.81030001,
            'longitude' => 90.41250001,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shop_owner.id', $owner->id)
            ->assertJsonPath('data.today_visit_count', 1);
        $this->assertLessThanOrEqual(10, $response->json('data.distance_meters'));
        $this->assertDatabaseHas('customer_shop_visits', [
            'customer_id' => $customer->id,
            'shop_owner_id' => $owner->id,
            'shop_name' => 'Nearby Shop',
        ]);
    }

    public function test_visit_is_rejected_when_nearest_shop_is_more_than_ten_meters_away(): void
    {
        $customer = Customer::create([
            'business_id' => 1,
            'name' => 'Test SR',
            'employee_type' => Customer::TYPE_SR,
        ]);
        ShopOwner::create([
            'distributor_id' => $customer->id,
            'name' => 'Far Shop',
            'email' => 'far@example.com',
            'latitude' => 23.81030000,
            'longitude' => 90.41250000,
            'address' => 'Dhaka',
        ]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/customer/shop-visits', [
            'latitude' => 23.81130000,
            'longitude' => 90.41250000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.maximum_distance_meters', 10);
        $this->assertDatabaseCount('customer_shop_visits', 0);
    }

    public function test_repeated_visits_to_the_same_shop_are_counted_only_once_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-25 10:00:00', 'Asia/Dhaka'));
        $customer = Customer::create([
            'business_id' => 1,
            'name' => 'Test SR',
            'employee_type' => Customer::TYPE_SR,
        ]);
        $owner = ShopOwner::create([
            'distributor_id' => $customer->id,
            'name' => 'Nearby Shop',
            'email' => 'nearby@example.com',
            'latitude' => 23.81030000,
            'longitude' => 90.41250000,
            'address' => 'Dhaka',
        ]);
        Sanctum::actingAs($customer);

        $firstResponse = $this->postJson('/api/customer/shop-visits', [
            'latitude' => 23.81030001,
            'longitude' => 90.41250001,
        ]);
        $secondResponse = $this->postJson('/api/customer/shop-visits', [
            'latitude' => 23.81030001,
            'longitude' => 90.41250001,
        ]);

        $firstResponse->assertCreated()
            ->assertJsonPath('data.already_visited', false);
        $secondResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'This shop visit was already recorded today.')
            ->assertJsonPath('data.already_visited', true)
            ->assertJsonPath('data.visit_id', $firstResponse->json('data.visit_id'))
            ->assertJsonPath('data.today_visit_count', 1);
        $this->assertDatabaseCount('customer_shop_visits', 1);
        $this->assertDatabaseHas('customer_shop_visits', [
            'customer_id' => $customer->id,
            'shop_owner_id' => $owner->id,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Asia/Dhaka'));
        $nextDayResponse = $this->postJson('/api/customer/shop-visits', [
            'latitude' => 23.81030001,
            'longitude' => 90.41250001,
        ]);

        $nextDayResponse->assertCreated()
            ->assertJsonPath('data.already_visited', false)
            ->assertJsonPath('data.today_visit_count', 1);
        $this->assertDatabaseCount('customer_shop_visits', 2);
    }
}
