<?php

namespace Tests\Feature;

use App\ShopOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopOwnerApiTest extends TestCase
{
    private Migration $shopOwnerMigration;

    private Migration $distributorMigration;

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

        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        DB::table('customers')->insert([
            ['id' => 1, 'name' => 'Dhaka Distributor'],
            ['id' => 2, 'name' => 'Chattogram Distributor'],
        ]);

        $this->shopOwnerMigration = require database_path(
            'migrations/2026_08_13_000001_create_shop_owners_table.php'
        );
        $this->shopOwnerMigration->up();

        $this->distributorMigration = require database_path(
            'migrations/2026_08_13_000002_add_distributor_id_to_shop_owners_table.php'
        );
        $this->distributorMigration->up();
        (require database_path('migrations/2026_10_18_000001_add_road_name_and_visit_day_to_shop_owners_table.php'))->up();
    }

    protected function tearDown(): void
    {
        $this->shopOwnerMigration->down();
        Schema::dropIfExists('customers');
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_it_stores_shop_owner_information_with_multiple_phone_numbers(): void
    {
        $response = $this->postJson('/api/shop-owners', [
            'distributor_id' => 1,
            'name' => 'Rahim Store',
            'email' => 'rahim@example.com',
            'phones' => ['+8801712345678', '01812345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka, Bangladesh',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.distributor_id', 1)
            ->assertJsonPath('data.name', 'Rahim Store')
            ->assertJsonPath('data.phones.0', '+8801712345678')
            ->assertJsonPath('data.phones.1', '01812345678');

        $shopOwner = ShopOwner::where('email', 'rahim@example.com')->firstOrFail();

        $this->assertDatabaseHas('shop_owners', [
            'id' => $shopOwner->id,
            'distributor_id' => 1,
            'name' => 'Rahim Store',
        ]);
        $this->assertDatabaseHas('shop_owner_phones', [
            'shop_owner_id' => $shopOwner->id,
            'phone' => '+8801712345678',
        ]);
        $this->assertDatabaseHas('shop_owner_phones', [
            'shop_owner_id' => $shopOwner->id,
            'phone' => '01812345678',
        ]);
    }

    public function test_it_saves_and_returns_the_road_and_every_supported_visit_day(): void
    {
        foreach (array_keys(ShopOwner::VISIT_DAYS) as $day) {
            $response = $this->postJson('/api/shop-owners', [
                'distributor_id' => 1,
                'name' => 'Scheduled Shop',
                'phones' => ['01712345678'],
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'address' => 'Dhaka',
                'road_name' => '  Mirpur Road  ',
                'visit_day' => $day,
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.road_name', 'Mirpur Road')
                ->assertJsonPath('data.visit_day', $day);
            $this->assertDatabaseHas('shop_owners', [
                'id' => $response->json('data.id'),
                'road_name' => 'Mirpur Road',
                'visit_day' => $day,
            ]);
            $this->getJson('/api/shop-owners?distributor_id=1')
                ->assertOk()
                ->assertJsonPath('data.0.road_name', 'Mirpur Road')
                ->assertJsonPath('data.0.visit_day', $day);
        }
    }

    public function test_road_and_visit_day_remain_optional_for_existing_clients(): void
    {
        $payload = [
            'distributor_id' => 1,
            'name' => 'Unscheduled Shop',
            'phones' => ['01712345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
        ];

        foreach ([[], ['road_name' => null, 'visit_day' => null], ['road_name' => ' ', 'visit_day' => '']] as $schedule) {
            $response = $this->postJson('/api/shop-owners', array_merge($payload, $schedule));
            $response->assertCreated()
                ->assertJsonPath('data.road_name', null)
                ->assertJsonPath('data.visit_day', null);
            $this->assertDatabaseHas('shop_owners', [
                'id' => $response->json('data.id'),
                'road_name' => null,
                'visit_day' => null,
            ]);
        }
    }

    public function test_it_rejects_invalid_roads_and_visit_days(): void
    {
        $this->postJson('/api/shop-owners', [
            'distributor_id' => 1,
            'name' => 'Invalid Shop',
            'phones' => ['01712345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
            'road_name' => str_repeat('x', 256),
            'visit_day' => 'someday',
        ])->assertUnprocessable()->assertJsonValidationErrors(['road_name', 'visit_day']);

        $this->assertDatabaseCount('shop_owners', 0);
    }

    public function test_it_accepts_missing_null_and_blank_emails_for_multiple_shop_owners(): void
    {
        $payload = [
            'distributor_id' => 1,
            'name' => 'Shop Without Email',
            'phones' => ['01712345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
        ];

        foreach ([[], ['email' => null], ['email' => ''], ['email' => '   ']] as $email) {
            $response = $this->postJson('/api/shop-owners', array_merge($payload, $email));

            $response->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.email', null);
            $this->assertDatabaseHas('shop_owners', [
                'id' => $response->json('data.id'),
                'email' => null,
            ]);
        }

        $this->assertDatabaseCount('shop_owners', 4);
        $this->getJson('/api/shop-owners?distributor_id=1')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.email', null);
    }

    public function test_it_still_rejects_duplicate_non_empty_emails(): void
    {
        $payload = [
            'distributor_id' => 1,
            'name' => 'Shop With Email',
            'email' => 'same@example.com',
            'phones' => ['01712345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
        ];

        $this->postJson('/api/shop-owners', $payload)->assertCreated();
        $this->postJson('/api/shop-owners', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('shop_owners', 1);
    }

    public function test_it_rejects_invalid_shop_owner_information(): void
    {
        $response = $this->postJson('/api/shop-owners', [
            'name' => '',
            'email' => 'not-an-email',
            'phones' => [],
            'latitude' => 91,
            'longitude' => -181,
            'address' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'distributor_id',
                'name',
                'email',
                'phones',
                'latitude',
                'longitude',
                'address',
            ]);
    }

    public function test_it_returns_a_paginated_shop_owner_list_for_the_pos(): void
    {
        $firstOwner = ShopOwner::create([
            'distributor_id' => 1,
            'name' => 'Karim Grocery',
            'email' => 'karim@example.com',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Mirpur, Dhaka',
        ]);
        $firstOwner->phones()->createMany([
            ['phone' => '01711111111'],
            ['phone' => '01811111111'],
        ]);

        $secondOwner = ShopOwner::create([
            'distributor_id' => 1,
            'name' => 'Rahim Pharmacy',
            'email' => 'rahim@example.com',
            'latitude' => 23.7500,
            'longitude' => 90.3900,
            'address' => 'Dhanmondi, Dhaka',
        ]);
        $secondOwner->phones()->create([
            'phone' => '01922222222',
        ]);

        $response = $this->getJson('/api/shop-owners?distributor_id=1&per_page=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rahim Pharmacy')
            ->assertJsonPath('data.0.phones.0', '01922222222')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_can_search_shop_owners_by_phone_number(): void
    {
        $shopOwner = ShopOwner::create([
            'distributor_id' => 1,
            'name' => 'Karim Grocery',
            'email' => 'karim@example.com',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Mirpur, Dhaka',
        ]);
        $shopOwner->phones()->create(['phone' => '01712345678']);

        $response = $this->getJson('/api/shop-owners?distributor_id=1&search=345678');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shopOwner->id)
            ->assertJsonPath('data.0.name', 'Karim Grocery');
    }

    public function test_it_returns_only_the_selected_distributors_shop_owners(): void
    {
        ShopOwner::create([
            'distributor_id' => 1,
            'name' => 'Dhaka Shop',
            'email' => 'dhaka@example.com',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka',
        ]);

        ShopOwner::create([
            'distributor_id' => 2,
            'name' => 'Chattogram Shop',
            'email' => 'chattogram@example.com',
            'latitude' => 22.3569,
            'longitude' => 91.7832,
            'address' => 'Chattogram',
        ]);

        $response = $this->getJson('/api/shop-owners?distributor_id=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.distributor_id', 1)
            ->assertJsonPath('data.0.name', 'Dhaka Shop');
    }
}
