<?php

namespace Tests\Feature;

use App\Http\Controllers\Distribution\ShopOwnerController;
use App\ShopOwner;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ShopOwnerManagementTest extends TestCase
{
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

        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->string('type')->default('customer');
            $table->boolean('is_dealer')->default(false);
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('business_id');
            $table->string('employee_type')->default('sr');
            $table->unsignedInteger('contact_id')->nullable();
        });

        DB::table('customers')->insert([
            ['id' => 1, 'name' => 'First SR', 'business_id' => 1],
            ['id' => 2, 'name' => 'Second SR', 'business_id' => 1],
            ['id' => 3, 'name' => 'Other Business SR', 'business_id' => 2],
        ]);

        foreach ([
            '2026_08_13_000001_create_shop_owners_table.php',
            '2026_08_13_000002_add_distributor_id_to_shop_owners_table.php',
            '2026_10_18_000001_add_road_name_and_visit_day_to_shop_owners_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $guardableColumns = new \ReflectionProperty(ShopOwner::class, 'guardableColumns');
        $guardableColumns->setAccessible(true);
        $guardableColumns->setValue(null, []);

        Schema::create('customer_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('shop_owner_id');
            $table->decimal('sale_price', 22, 4);
        });

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturn(true);
        $this->actingAs($user);
        session()->put('user.business_id', 1);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_it_creates_an_owner_with_a_road_and_weekly_visit_day(): void
    {
        (new ShopOwnerController)->store($this->ownerRequest([
            'road_name' => 'Mirpur Road',
            'visit_day' => 'sunday',
        ]));

        $this->assertTrue(session('status.success'));
        $this->assertDatabaseHas('shop_owners', [
            'email' => 'rahim@example.com',
            'road_name' => 'Mirpur Road',
            'visit_day' => 'sunday',
        ]);
    }

    public function test_it_creates_multiple_owners_with_missing_null_or_blank_emails(): void
    {
        $controller = new ShopOwnerController;
        $request = $this->ownerRequest();
        $request->request->remove('email');
        $controller->store($request);

        foreach ([null, ''] as $email) {
            $controller->store($this->ownerRequest(['email' => $email]));
        }

        $this->assertSame(3, ShopOwner::whereNull('email')->count());
        $this->assertDatabaseCount('shop_owner_phones', 3);
    }

    public function test_it_can_clear_and_restore_an_existing_owners_email(): void
    {
        $controller = new ShopOwnerController;
        $controller->store($this->ownerRequest());
        $owner = ShopOwner::firstOrFail();

        // Keeping the owner's own email is allowed by the unique rule.
        $controller->update($this->ownerRequest(), $owner->id);
        $this->assertSame('rahim@example.com', $owner->fresh()->email);

        foreach ([null, ''] as $email) {
            $controller->update($this->ownerRequest(['email' => $email]), $owner->id);
            $this->assertNull($owner->fresh()->email);
        }

        $controller->update($this->ownerRequest(['email' => 'updated@example.com']), $owner->id);
        $this->assertSame('updated@example.com', $owner->fresh()->email);
    }

    public function test_it_still_validates_provided_email_addresses(): void
    {
        $controller = new ShopOwnerController;
        $controller->store($this->ownerRequest());

        foreach (['invalid-email', 'rahim@example.com'] as $email) {
            try {
                $controller->store($this->ownerRequest(['email' => $email]));
                $this->fail('Invalid or duplicate email was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('email', $exception->errors());
            }
        }

        $this->assertDatabaseCount('shop_owners', 1);
    }

    public function test_it_updates_and_clears_the_road_and_visit_day(): void
    {
        $controller = new ShopOwnerController;
        $controller->store($this->ownerRequest([
            'road_name' => 'Mirpur Road',
            'visit_day' => 'sunday',
        ]));
        $owner = ShopOwner::firstOrFail();

        $controller->update($this->ownerRequest([
            'road_name' => 'College Road',
            'visit_day' => 'monday',
        ]), $owner->id);

        $this->assertDatabaseHas('shop_owners', [
            'id' => $owner->id,
            'road_name' => 'College Road',
            'visit_day' => 'monday',
        ]);

        $controller->update($this->ownerRequest([
            'road_name' => null,
            'visit_day' => null,
        ]), $owner->id);

        $this->assertDatabaseHas('shop_owners', [
            'id' => $owner->id,
            'road_name' => null,
            'visit_day' => null,
        ]);
    }

    public function test_existing_owner_fields_can_be_saved_without_a_schedule(): void
    {
        (new ShopOwnerController)->store($this->ownerRequest());

        $this->assertDatabaseHas('shop_owners', [
            'email' => 'rahim@example.com',
            'road_name' => null,
            'visit_day' => null,
        ]);
    }

    public function test_it_validates_the_road_length_and_weekday(): void
    {
        try {
            (new ShopOwnerController)->store($this->ownerRequest([
                'road_name' => str_repeat('a', 256),
                'visit_day' => 'someday',
            ]));
            $this->fail('Invalid road and visit day were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('road_name', $exception->errors());
            $this->assertArrayHasKey('visit_day', $exception->errors());
        }

        $this->assertDatabaseCount('shop_owners', 0);
    }

    public function test_filters_combine_and_road_options_stay_within_the_business(): void
    {
        foreach ([
            [1, 'Mirpur Road', 'sunday'],
            [1, 'Mirpur Road', 'monday'],
            [1, 'College Road', 'sunday'],
            [2, 'Mirpur Road', 'sunday'],
            [3, 'Other Business Road', 'sunday'],
            [3, 'Mirpur Road', 'sunday'],
        ] as $index => [$distributorId, $road, $day]) {
            ShopOwner::create([
                'distributor_id' => $distributorId,
                'name' => 'Shop '.$index,
                'email' => 'shop'.$index.'@example.com',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'address' => 'Dhaka',
                'road_name' => $road,
                'visit_day' => $day,
            ]);
        }

        $controller = new ShopOwnerController;
        $request = Request::create('/', 'GET', [
            'road_name' => 'Mirpur Road',
            'visit_day' => 'sunday',
            'distributor_id' => 1,
        ]);
        $request->setLaravelSession(session()->driver());
        $data = $controller->index($request)->getData();

        $this->assertSame(['Shop 0'], $data['owners']->pluck('name')->all());
        $this->assertSame(['College Road', 'Mirpur Road'], $data['roads']->all());
        $this->assertStringContainsString('road_name=Mirpur%20Road', $data['owners']->url(2));
        $this->assertStringContainsString('visit_day=sunday', $data['owners']->url(2));

        $request = Request::create('/', 'GET', ['search' => 'College Road']);
        $request->setLaravelSession(session()->driver());
        $data = $controller->index($request)->getData();

        $this->assertSame(['Shop 2'], $data['owners']->pluck('name')->all());
    }

    public function test_dealer_filter_follows_the_sr_contact_and_combines_with_other_filters(): void
    {
        DB::table('contacts')->insert([
            ['id' => 10, 'business_id' => 1, 'name' => 'First Dealer', 'type' => 'customer', 'is_dealer' => true, 'deleted_at' => null],
            ['id' => 20, 'business_id' => 1, 'name' => 'Second Dealer', 'type' => 'both', 'is_dealer' => true, 'deleted_at' => null],
            ['id' => 30, 'business_id' => 2, 'name' => 'Other Business Dealer', 'type' => 'customer', 'is_dealer' => true, 'deleted_at' => null],
            ['id' => 40, 'business_id' => 1, 'name' => 'Retail Customer', 'type' => 'customer', 'is_dealer' => false, 'deleted_at' => null],
            ['id' => 50, 'business_id' => 1, 'name' => 'Deleted Dealer', 'type' => 'customer', 'is_dealer' => true, 'deleted_at' => now()],
            ['id' => 60, 'business_id' => 1, 'name' => 'Supplier', 'type' => 'supplier', 'is_dealer' => true, 'deleted_at' => null],
        ]);
        foreach ([1 => 10, 2 => 20, 3 => 30] as $srId => $contactId) {
            DB::table('customers')->where('id', $srId)->update(['contact_id' => $contactId]);
        }
        DB::table('customers')->insert([
            ['id' => 4, 'name' => 'Another SR', 'business_id' => 1, 'contact_id' => 10],
            ['id' => 5, 'name' => 'Unlinked SR', 'business_id' => 1, 'contact_id' => null],
            ['id' => 6, 'name' => 'Invalid Dealer Link SR', 'business_id' => 1, 'contact_id' => 30],
        ]);
        foreach (range(1, 6) as $srId) {
            ShopOwner::create([
                'distributor_id' => $srId,
                'name' => 'Dealer Shop '.$srId,
                'email' => 'dealer-shop'.$srId.'@example.com',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
                'address' => 'Dhaka',
                'road_name' => 'Mirpur Road',
                'visit_day' => 'sunday',
            ]);
        }

        $controller = new ShopOwnerController;
        $request = Request::create('/', 'GET', ['dealer_id' => 10]);
        $request->setLaravelSession(session()->driver());
        $data = $controller->index($request)->getData();

        $this->assertSame(['Dealer Shop 4', 'Dealer Shop 1'], $data['owners']->pluck('name')->all());
        $this->assertSame([10 => 'First Dealer', 20 => 'Second Dealer'], $data['dealers']->all());
        $this->assertStringContainsString('dealer_id=10', $data['owners']->url(2));

        $request = Request::create('/', 'GET', [
            'dealer_id' => 10,
            'distributor_id' => 1,
            'road_name' => 'Mirpur Road',
            'visit_day' => 'sunday',
        ]);
        $request->setLaravelSession(session()->driver());
        $data = $controller->index($request)->getData();

        $this->assertSame(['Dealer Shop 1'], $data['owners']->pluck('name')->all());

        $request = Request::create('/', 'GET', ['dealer_id' => 30]);
        $request->setLaravelSession(session()->driver());
        $data = $controller->index($request)->getData();

        $this->assertSame([], $data['owners']->items());
    }

    private function ownerRequest(array $overrides = []): Request
    {
        $request = Request::create('/', 'POST', array_merge([
            'distributor_id' => 1,
            'name' => 'Rahim Store',
            'email' => 'rahim@example.com',
            'phones' => ['01712345678'],
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka, Bangladesh',
        ], $overrides));
        $request->setLaravelSession(session()->driver());

        return $request;
    }
}
