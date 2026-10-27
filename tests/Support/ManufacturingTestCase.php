<?php

namespace Tests\Support;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

abstract class ManufacturingTestCase extends TestCase
{
    protected $operator;
    protected array $denied = [];
    protected $locations = 'all';
    protected ?string $testDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MFG_TEST_MYSQL') === '1') {
            // Read connection credentials, never use the application's schema for test DDL.
            $env = \Dotenv\Dotenv::parse(file_get_contents(base_path('.env')));
            $config = config('database.connections.mysql');
            $config['url'] = null; $config['database'] = null;
            foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $name) {
                if (isset($env[$name])) { $config[$key] = $env[$name]; }
            }
            config(['database.connections.mfg_admin' => $config]);
            $this->testDatabase = 'codex_mfg_test_'.getmypid().'_'.bin2hex(random_bytes(5));
            DB::connection('mfg_admin')->statement('CREATE DATABASE `'.$this->testDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $config['database'] = $this->testDatabase; $config['engine'] = 'InnoDB';
            config(['database.default' => 'mfg_test', 'database.connections.mfg_test' => $config]);
            DB::purge('mfg_test');
        } else {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
            DB::purge('sqlite');
        }
        $this->coreSchema();
        (require database_path('migrations/2026_10_25_000001_add_manufacturing_stock_foundation.php'))->up();
        (require base_path('Modules/Manufacturing/Database/Migrations/2026_10_25_000002_create_manufacturing_tables.php'))->up();
        DB::table('business')->insert([['id' => 1, 'name' => 'Factory', 'accounting_method' => 'fifo'], ['id' => 2, 'name' => 'Other', 'accounting_method' => 'fifo']]);
        DB::table('users')->insert([['id' => 1, 'business_id' => 1, 'name' => 'Operator'], ['id' => 2, 'business_id' => 2, 'name' => 'Other']]);
        DB::table('business_locations')->insert([['id' => 1, 'business_id' => 1, 'name' => 'Factory', 'is_active' => 1], ['id' => 2, 'business_id' => 2, 'name' => 'Other', 'is_active' => 1], ['id' => 3, 'business_id' => 1, 'name' => 'Second', 'is_active' => 1]]);
        DB::table('units')->insert([
            ['id' => 1, 'business_id' => 1, 'actual_name' => 'KG', 'allow_decimal' => 1, 'base_unit_id' => null, 'base_unit_multiplier' => null],
            ['id' => 2, 'business_id' => 1, 'actual_name' => 'gram', 'allow_decimal' => 1, 'base_unit_id' => 1, 'base_unit_multiplier' => '0.001'],
            ['id' => 3, 'business_id' => 1, 'actual_name' => 'piece', 'allow_decimal' => 0, 'base_unit_id' => null, 'base_unit_multiplier' => null],
        ]);
        $this->operator = Mockery::mock(User::class)->makePartial();
        $this->operator->forceFill(['id' => 1, 'business_id' => 1, 'user_type' => 'user', 'allow_login' => 1]);
        $this->operator->shouldReceive('can')->andReturnUsing(fn ($ability) => ! in_array($ability, $this->denied, true));
        $this->operator->shouldReceive('permitted_locations')->andReturnUsing(fn () => $this->locations);
        $this->actingAs($this->operator);
        $this->withoutMiddleware([
            \App\Http\Middleware\SetSessionData::class, \App\Http\Middleware\AdminSidebarMenu::class,
            \App\Http\Middleware\Language::class, \App\Http\Middleware\Timezone::class,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testDatabase && preg_match('/^codex_mfg_test_\d+_[a-f0-9]{10}$/D', $this->testDatabase)) {
                DB::purge('mfg_test');
                DB::connection('mfg_admin')->statement('DROP DATABASE `'.$this->testDatabase.'`');
                DB::purge('mfg_admin');
            } else { DB::purge('sqlite'); }
        } finally { parent::tearDown(); }
    }

    protected function product(string $type = 'raw_material', int $unitId = 1, int $businessId = 1): int
    {
        $id = DB::table('products')->insertGetId(['business_id' => $businessId, 'name' => 'Item '.bin2hex(random_bytes(3)), 'type' => 'single', 'manufacturing_type' => $type, 'unit_id' => $unitId, 'enable_stock' => 1, 'is_inactive' => 0, 'sku' => bin2hex(random_bytes(4))]);
        return DB::table('variations')->insertGetId(['product_id' => $id, 'product_variation_id' => $id, 'name' => 'DUMMY', 'default_purchase_price' => '10', 'dpp_inc_tax' => '10']);
    }

    protected function receipt(int $variationId, string $quantity = '100', string $cost = '10', int $locationId = 1, string $date = '2026-01-01'): int
    {
        $productId = DB::table('variations')->where('id', $variationId)->value('product_id');
        $tx = DB::table('transactions')->insertGetId(['business_id' => 1, 'location_id' => $locationId, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => $date, 'created_by' => 1]);
        $line = DB::table('purchase_lines')->insertGetId(['transaction_id' => $tx, 'product_id' => $productId, 'variation_id' => $variationId, 'quantity' => $quantity, 'purchase_price' => $cost, 'purchase_price_inc_tax' => $cost, 'item_tax' => '0']);
        app(\App\Utils\ProductUtil::class)->updateProductQuantity($locationId, $productId, $variationId, $quantity, 0, null, false);
        return $line;
    }

    protected function bom(int $output, int $input, string $quantity = '1', int $unitId = 1): array
    {
        return app(\Modules\Manufacturing\Services\BomService::class)->save($this->operator, ['name' => 'Recipe', 'variation_id' => $output, 'output_quantity' => '1', 'items' => [['variation_id' => $input, 'unit_id' => $unitId, 'quantity' => $quantity]]], 'bom-'.bin2hex(random_bytes(8)));
    }

    protected function order(int $versionId, string $quantity = '10'): int
    {
        return app(\Modules\Manufacturing\Services\ProductionService::class)->create($this->operator, ['bom_version_id' => $versionId, 'location_id' => 1, 'planned_quantity' => $quantity, 'status' => 'planned'], 'order-'.bin2hex(random_bytes(8)))['order_id'];
    }

    protected function balance(int $variation): string
    {
        return app(\Modules\Manufacturing\Services\ManufacturingCatalog::class)->stock($variation, 1);
    }

    private function coreSchema(): void
    {
        Schema::create('business', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('accounting_method')->default('fifo'); $t->text('pos_settings')->nullable(); });
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('name'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('business_locations', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('name'); $t->boolean('is_active')->default(true); });
        Schema::create('units', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('actual_name'); $t->boolean('allow_decimal'); $t->unsignedInteger('base_unit_id')->nullable(); $t->decimal('base_unit_multiplier', 20, 4)->nullable(); $t->softDeletes(); });
        Schema::create('products', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('name'); $t->string('sku'); $t->string('type'); $t->unsignedInteger('unit_id'); $t->boolean('enable_stock'); $t->boolean('is_inactive')->default(false); $t->boolean('not_for_selling')->default(false); $t->timestamps(); });
        Schema::create('variations', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('product_id'); $t->unsignedInteger('product_variation_id'); $t->string('name'); $t->decimal('default_purchase_price', 22, 4)->default(0); $t->decimal('dpp_inc_tax', 22, 4)->default(0); $t->softDeletes(); });
        Schema::create('product_locations', function (Blueprint $t) { $t->unsignedInteger('product_id'); $t->unsignedInteger('location_id'); $t->unique(['product_id', 'location_id']); });
        Schema::create('variation_location_details', function (Blueprint $t) { $t->increments('id'); foreach (['product_id', 'product_variation_id', 'variation_id', 'location_id'] as $c) { $t->unsignedInteger($c); } $t->decimal('qty_available', 22, 4)->default(0); $t->timestamps(); });
        Schema::create('transactions', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('location_id'); $t->string('type'); $t->string('status'); $t->string('payment_status')->nullable(); $t->string('ref_no')->nullable(); $t->string('invoice_no')->nullable(); $t->dateTime('transaction_date'); $t->unsignedInteger('created_by'); $t->text('additional_notes')->nullable(); $t->decimal('total_before_tax', 22, 4)->default(0); $t->decimal('final_total', 22, 4)->default(0); $t->timestamps(); });
        Schema::create('purchase_lines', function (Blueprint $t) { $t->increments('id'); foreach (['transaction_id', 'product_id', 'variation_id'] as $c) { $t->unsignedInteger($c); } foreach (['quantity', 'purchase_price', 'purchase_price_inc_tax', 'pp_without_discount', 'item_tax', 'quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used', 'discount_percent'] as $c) { $t->decimal($c, 22, 4)->default(0); } foreach (['tax_id', 'sub_unit_id', 'purchase_order_line_id', 'purchase_requisition_line_id'] as $c) { $t->unsignedInteger($c)->nullable(); } $t->date('mfg_date')->nullable(); $t->date('exp_date')->nullable(); $t->string('lot_number')->nullable(); $t->timestamps(); });
        Schema::create('transaction_sell_lines', function (Blueprint $t) { $t->increments('id'); foreach (['transaction_id', 'product_id', 'variation_id'] as $c) { $t->unsignedInteger($c); } foreach (['quantity', 'quantity_returned', 'unit_price', 'unit_price_inc_tax', 'item_tax'] as $c) { $t->decimal($c, 22, 4)->default(0); } $t->timestamps(); });
        Schema::create('stock_adjustment_lines', function (Blueprint $t) { $t->increments('id'); foreach (['transaction_id', 'product_id', 'variation_id'] as $c) { $t->unsignedInteger($c); } $t->decimal('quantity', 22, 4); $t->decimal('unit_price', 22, 4); $t->timestamps(); });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('sell_line_id')->nullable(); $t->unsignedInteger('stock_adjustment_line_id')->nullable(); $t->unsignedInteger('purchase_line_id'); $t->decimal('quantity', 22, 4); $t->decimal('qty_returned', 22, 4)->default(0); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('guard_name'); $t->timestamps(); $t->unique(['name', 'guard_name']); });
    }
}
