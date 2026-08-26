<?php

namespace Tests\Unit;

use App\CustomerSale;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;
use App\Services\CustomerSaleService;
use App\ShopOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CustomerSaleShopOwnerTest extends TestCase
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
            $table->string('employee_type')->default('sr');
            $table->unsignedInteger('stock_owner_customer_id')->nullable();
        });
        DB::table('customers')->insert(['id' => 1, 'name' => 'Sales Rep', 'employee_type' => 'sr']);

        $this->shopOwnerMigration = require database_path('migrations/2026_08_13_000001_create_shop_owners_table.php');
        $this->shopOwnerMigration->up();
        $this->distributorMigration = require database_path('migrations/2026_08_13_000002_add_distributor_id_to_shop_owners_table.php');
        $this->distributorMigration->up();
    }

    protected function tearDown(): void
    {
        $this->shopOwnerMigration->down();
        Schema::dropIfExists('customers');
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_sale_is_linked_to_existing_owner_by_legacy_shop_name(): void
    {
        $owner = ShopOwner::create([
            'distributor_id' => 1,
            'name' => 'Rahim Store',
            'email' => 'rahim@example.com',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $owner->phones()->create(['phone' => '01712345678']);

        $stockRepository = Mockery::mock(CustomerStockRepositoryInterface::class);
        $saleRepository = Mockery::mock(CustomerSaleRepositoryInterface::class);
        $saleRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($owner) {
                return $data['shop_owner_id'] === $owner->id
                    && $data['sold_to'] === $owner->name
                    && $data['resaler_phone'] === '01712345678';
            }))
            ->andReturnUsing(fn (array $data) => new CustomerSale($data));

        $sale = (new CustomerSaleService($stockRepository, $saleRepository))->sell([
            'customer_id' => 1,
            'items' => [['product_id' => 10, 'quantity' => 2, 'name' => 'Soap']],
            'sale_price' => 500,
            'discount' => 0,
            'sold_to' => 'Rahim Store',
            'status' => 'ordered',
        ]);

        $this->assertSame($owner->id, $sale->shop_owner_id);
    }
}
