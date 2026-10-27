<?php

namespace Tests\Feature;

use App\Customer;
use App\CustomerSale;
use App\Http\Controllers\Distribution\CustomerController;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Repositories\CustomerStockRepository;
use App\Services\CustomerSaleService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SrDealerStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('name')->default('Dealer');
            $table->string('type')->default('customer');
            $table->boolean('is_dealer')->default(true);
            $table->string('email')->default('dealer@example.com');
            $table->softDeletes();
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('stock_owner_customer_id')->nullable();
            $table->unsignedInteger('assigned_sr_customer_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->string('employee_type')->default('sr');
            $table->string('name');
            $table->string('email')->nullable()->unique();
            foreach (['phone', 'password', 'address', 'status', 'shift_start_time', 'shift_end_time', 'attendance_location'] as $column) {
                $table->string($column)->nullable();
            }
            foreach (['work_latitude', 'work_longitude', 'allowed_radius_meters', 'base_salary', 'late_deduction_per_hour', 'overtime_pay_per_hour', 'tada_bill_amount', 'monthly_target_amount', 'commission_percent'] as $column) {
                $table->decimal($column, 22, 4)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('customer_stocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variation_id')->nullable();
            $table->decimal('quantity', 22, 4);
            $table->decimal('purchase_price', 22, 4);
            $table->unique(['customer_id', 'product_id']);
            $table->timestamps();
        });
        Schema::create('customer_stock_transfers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id');
        });
        (require database_path('migrations/2026_05_11_180000_create_customer_work_locations_table.php'))->up();

        DB::table('contacts')->insert([
            ['id' => 10, 'business_id' => 1, 'is_dealer' => true, 'type' => 'customer', 'deleted_at' => null],
            ['id' => 20, 'business_id' => 1, 'is_dealer' => true, 'type' => 'both', 'deleted_at' => null],
            ['id' => 30, 'business_id' => 2, 'is_dealer' => true, 'type' => 'customer', 'deleted_at' => null],
            ['id' => 40, 'business_id' => 1, 'is_dealer' => false, 'type' => 'customer', 'deleted_at' => null],
            ['id' => 50, 'business_id' => 1, 'is_dealer' => true, 'type' => 'supplier', 'deleted_at' => null],
            ['id' => 60, 'business_id' => 1, 'is_dealer' => true, 'type' => 'customer', 'deleted_at' => now()],
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturn(true);
        $this->actingAs($user);
        session()->put('user.business_id', 1);
        app('request')->setLaravelSession(session()->driver());
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_multiple_srs_keep_their_own_logins_and_share_only_their_dealers_stock(): void
    {
        $controller = app(CustomerController::class);
        $controller->store($this->srRequest('first'));
        $first = Customer::where('email', 'first@example.com')->firstOrFail();
        $controller->store($this->srRequest('second', ['stock_owner_customer_id' => 999]));
        $second = Customer::where('email', 'second@example.com')->firstOrFail();
        $controller->store($this->srRequest('other', ['contact_id' => 20]));
        $other = Customer::where('email', 'other@example.com')->firstOrFail();

        $this->assertSame('first SR', $first->name);
        $this->assertSame('second SR', $second->name);
        $this->assertNull($first->stock_owner_customer_id);
        $this->assertSame($first->id, $second->resolved_stock_owner_id);
        $this->assertSame($other->id, $other->resolved_stock_owner_id);

        DB::table('customer_stocks')->insert([
            'customer_id' => $first->id, 'product_id' => 100, 'quantity' => 10, 'purchase_price' => 50,
        ]);
        $stocks = new CustomerStockRepository;
        $this->assertSame(10.0, (float) $stocks->getStockByCustomerAndProduct($second->id, 100)->quantity);
        $this->assertNull($stocks->getStockByCustomerAndProduct($other->id, 100));

        $sales = Mockery::mock(CustomerSaleRepositoryInterface::class);
        $sales->shouldReceive('create')->once()->andReturnUsing(fn ($data) => new CustomerSale($data));
        $sale = (new CustomerSaleService($stocks, $sales))->sell([
            'customer_id' => $second->id,
            'items' => [['product_id' => 100, 'quantity' => 3]],
            'sale_price' => 200, 'discount' => 0, 'status' => 'delivered',
        ]);
        $this->assertSame($second->id, $sale->customer_id);
        $this->assertSame(7.0, (float) $stocks->getStockByCustomerAndProduct($first->id, 100)->quantity);
        $this->assertSame(7.0, (float) $stocks->getStockByCustomerAndProduct($second->id, 100)->quantity);

        $controller->update($this->srRequest('second', ['stock_owner_customer_id' => $other->id]), $second->id);
        $this->assertSame($first->id, $second->fresh()->resolved_stock_owner_id);
    }

    public function test_sr_requires_a_valid_dealer_in_the_current_business(): void
    {
        foreach ([null, 30, 40, 50, 60, 999] as $dealerId) {
            try {
                app(CustomerController::class)->store($this->srRequest('invalid', ['contact_id' => $dealerId]));
                $this->fail('Invalid dealer was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('contact_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_dsr_creation_still_uses_an_assigned_sr_without_a_dealer_or_stock_account(): void
    {
        $controller = app(CustomerController::class);
        $controller->store($this->srRequest('first'));
        $sr = Customer::firstOrFail();
        $controller->store($this->srRequest('delivery', [
            'employee_type' => 'dsr', 'contact_id' => null,
            'assigned_sr_customer_id' => $sr->id,
            'email' => null, 'password' => null, 'password_confirmation' => null,
        ]));

        $this->assertTrue(session('status.success'));
        $dsr = Customer::where('employee_type', 'dsr')->firstOrFail();
        $this->assertSame($sr->id, $dsr->assigned_sr_customer_id);
        $this->assertNull($dsr->contact_id);
        $this->assertNull($dsr->stock_owner_customer_id);
        $this->assertNull($dsr->email);
    }

    public function test_existing_balances_and_transfer_history_are_combined_per_dealer(): void
    {
        foreach ([1 => 10, 2 => 10, 3 => 20, 4 => 30] as $id => $contactId) {
            DB::table('customers')->insert([
                'id' => $id, 'business_id' => $id === 4 ? 2 : 1,
                'name' => 'SR '.$id, 'contact_id' => $contactId,
            ]);
        }
        DB::table('customer_stocks')->insert([
            ['customer_id' => 1, 'product_id' => 100, 'quantity' => 10, 'purchase_price' => 50],
            ['customer_id' => 2, 'product_id' => 100, 'quantity' => 5, 'purchase_price' => 80],
            ['customer_id' => 2, 'product_id' => 200, 'quantity' => 4, 'purchase_price' => 20],
            ['customer_id' => 3, 'product_id' => 100, 'quantity' => 9, 'purchase_price' => 30],
            ['customer_id' => 4, 'product_id' => 100, 'quantity' => 8, 'purchase_price' => 40],
        ]);
        DB::table('customer_stock_transfers')->insert(['customer_id' => 2]);
        $migration = require database_path('migrations/2026_10_19_000001_share_sr_stock_by_dealer.php');
        $migration->up();
        $migration->up(); // Re-running must not duplicate stock or change its value.

        $this->assertDatabaseHas('customer_stocks', ['customer_id' => 1, 'product_id' => 100, 'quantity' => 15, 'purchase_price' => 60]);
        $this->assertDatabaseHas('customer_stocks', ['customer_id' => 1, 'product_id' => 200, 'quantity' => 4]);
        $this->assertDatabaseHas('customer_stocks', ['customer_id' => 3, 'product_id' => 100, 'quantity' => 9]);
        $this->assertDatabaseHas('customer_stocks', ['customer_id' => 4, 'product_id' => 100, 'quantity' => 8]);
        $this->assertDatabaseMissing('customer_stocks', ['customer_id' => 2]);
        $this->assertDatabaseHas('customer_stock_transfers', ['customer_id' => 1]);
        $this->assertSame(1, Customer::findOrFail(2)->resolved_stock_owner_id);
    }

    private function srRequest(string $name, array $overrides = []): Request
    {
        $request = Request::create('/', 'POST', array_merge([
            'employee_type' => 'sr', 'contact_id' => 10,
            'name' => $name.' SR', 'phone' => '01712345678', 'email' => $name.'@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ], $overrides));
        $request->setLaravelSession(session()->driver());

        return $request;
    }
}
