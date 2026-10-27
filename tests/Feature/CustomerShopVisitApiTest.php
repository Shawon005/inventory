<?php

namespace Tests\Feature;

use App\Customer;
use App\CustomerShopVisit;
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
        (require database_path('migrations/2026_10_18_000001_add_road_name_and_visit_day_to_shop_owners_table.php'))->up();
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

    public function test_daily_schedule_uses_the_business_weekday_and_only_the_authenticated_srs_shops(): void
    {
        // It is still Saturday in UTC, but Sunday in the business timezone.
        Carbon::setTestNow(Carbon::parse('2026-10-17 19:00:00', 'UTC'));
        $customer = $this->createSr();
        $otherCustomer = $this->createSr();
        $shop = $this->createScheduledShop($customer, 'Sunday Shop', 'sunday');
        $shop->phones()->createMany([
            ['phone' => '01711111111'],
            ['phone' => '01811111111'],
        ]);
        $this->createScheduledShop($customer, 'Saturday Shop', 'saturday');
        $this->createScheduledShop($customer, 'Unscheduled Shop', null);
        $this->createScheduledShop($otherCustomer, 'Other SR Shop', 'sunday');
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/shop-visits?distributor_id='.$otherCustomer->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shop->id)
            ->assertJsonPath('data.0.distributor_id', $customer->id)
            ->assertJsonPath('data.0.name', 'Sunday Shop')
            ->assertJsonPath('data.0.email', $shop->email)
            ->assertJsonPath('data.0.phones', ['01711111111', '01811111111'])
            ->assertJsonPath('data.0.address', 'Dhaka')
            ->assertJsonPath('data.0.road_name', 'Mirpur Road')
            ->assertJsonPath('data.0.latitude', 23.8103)
            ->assertJsonPath('data.0.longitude', 90.4125)
            ->assertJsonPath('data.0.visit_day', 'sunday')
            ->assertJsonPath('data.0.is_visited', false)
            ->assertJsonPath('data.0.visit_id', null)
            ->assertJsonPath('data.0.visited_at', null)
            ->assertJsonPath('data.0.distance_meters', null)
            ->assertJsonPath('meta.date', '2026-10-18')
            ->assertJsonPath('meta.day', 'Sunday')
            ->assertJsonPath('meta.visit_day', 'sunday')
            ->assertJsonPath('meta.timezone', 'Asia/Dhaka')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.visited', 0)
            ->assertJsonPath('meta.pending', 1);
    }

    public function test_daily_schedule_includes_only_the_selected_days_and_srs_visit_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Dhaka'));
        $customer = $this->createSr();
        $otherCustomer = $this->createSr();
        $visited = $this->createScheduledShop($customer, 'A Visited Shop', 'sunday');
        $pending = $this->createScheduledShop($customer, 'B Pending Shop', 'sunday');
        $visit = $this->createVisit($customer, $visited, '2026-10-18');
        $this->createVisit($customer, $pending, '2026-10-17');
        $this->createVisit($customer, $pending, '2026-10-19');
        $this->createVisit($otherCustomer, $pending, '2026-10-18');
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/shop-visits')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $visited->id)
            ->assertJsonPath('data.0.is_visited', true)
            ->assertJsonPath('data.0.visit_id', $visit->id)
            ->assertJsonPath('data.0.visited_at', '2026-10-18T09:30:00+06:00')
            ->assertJsonPath('data.0.distance_meters', 2.5)
            ->assertJsonPath('data.1.id', $pending->id)
            ->assertJsonPath('data.1.is_visited', false)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.visited', 1)
            ->assertJsonPath('meta.pending', 1);
    }

    public function test_daily_schedule_accepts_a_date_and_paginates_with_full_schedule_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Dhaka'));
        $customer = $this->createSr();
        $first = $this->createScheduledShop($customer, 'A Monday Shop', 'monday');
        $second = $this->createScheduledShop($customer, 'B Monday Shop', 'monday');
        $this->createVisit($customer, $first, '2026-10-19');
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/shop-visits?date=2026-10-19&per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('meta.date', '2026-10-19')
            ->assertJsonPath('meta.day', 'Monday')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.visited', 1)
            ->assertJsonPath('meta.pending', 1);
    }

    public function test_daily_schedule_returns_an_empty_list_when_no_shops_are_scheduled(): void
    {
        $customer = $this->createSr();
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/shop-visits')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.visited', 0)
            ->assertJsonPath('meta.pending', 0);
    }

    public function test_daily_schedule_falls_back_to_the_application_timezone(): void
    {
        DB::table('business')->where('id', 1)->update(['time_zone' => null]);
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-10-17 19:00:00', 'UTC'));
        Sanctum::actingAs($this->createSr());

        $this->getJson('/api/customer/shop-visits')
            ->assertOk()
            ->assertJsonPath('meta.date', '2026-10-17')
            ->assertJsonPath('meta.day', 'Saturday')
            ->assertJsonPath('meta.timezone', 'UTC');
    }

    public function test_daily_schedule_validates_date_and_pagination(): void
    {
        Sanctum::actingAs($this->createSr());

        $this->getJson('/api/customer/shop-visits?date=2026-02-30&per_page=101&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'per_page', 'page']);
        $this->getJson('/api/customer/shop-visits?date=sunday&per_page=0&page=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'per_page', 'page']);
    }

    public function test_daily_schedule_requires_authentication(): void
    {
        $this->getJson('/api/customer/shop-visits')->assertUnauthorized();
    }

    public function test_daily_schedule_rejects_non_sr_accounts(): void
    {
        $customer = $this->createSr();
        $customer->update(['employee_type' => Customer::TYPE_DSR]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/customer/shop-visits')->assertForbidden();
    }

    private function createSr(): Customer
    {
        return Customer::create([
            'business_id' => 1,
            'name' => 'Test SR',
            'employee_type' => Customer::TYPE_SR,
        ]);
    }

    private function createScheduledShop(Customer $customer, string $name, ?string $day): ShopOwner
    {
        return ShopOwner::create([
            'distributor_id' => $customer->id,
            'name' => $name,
            'email' => str_replace(' ', '', strtolower($name)).'@example.com',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
            'road_name' => 'Mirpur Road',
            'visit_day' => $day,
        ]);
    }

    private function createVisit(Customer $customer, ShopOwner $shop, string $date): CustomerShopVisit
    {
        return CustomerShopVisit::create([
            'customer_id' => $customer->id,
            'shop_owner_id' => $shop->id,
            'visit_date' => $date,
            'shop_name' => $shop->name,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'shop_latitude' => $shop->latitude,
            'shop_longitude' => $shop->longitude,
            'distance_meters' => 2.5,
            'visited_at' => $date.' 09:30:00',
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
