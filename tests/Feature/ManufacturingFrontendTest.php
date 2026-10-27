<?php

namespace Tests\Feature;

use App\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProductFormService;
use Tests\Support\ManufacturingTestCase;

class ManufacturingFrontendTest extends ManufacturingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('products', function (Blueprint $t) { $t->decimal('alert_quantity', 22, 4)->nullable(); });
        Schema::table('products', function (Blueprint $t) { $t->unsignedInteger('created_by')->nullable(); $t->string('image')->nullable(); $t->unsignedInteger('warranty_id')->nullable(); $t->boolean('free_item')->default(false); });
        Schema::table('variations', function (Blueprint $t) { $t->string('sub_sku')->nullable(); });
        Schema::table('users', function (Blueprint $t) { $t->string('first_name')->default('Operator'); $t->string('last_name')->nullable(); });
        Schema::create('system', function (Blueprint $t) { $t->increments('id'); $t->string('key'); $t->text('value')->nullable(); });
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/manufacturing'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->operator->shouldReceive('hasRole')->andReturn(false);
        $this->operator->shouldReceive('checkPermissionTo')->andReturnUsing(fn ($ability) => ! in_array($ability, $this->denied, true));
        session()->put('user.business_id', 1);
        session()->put('user.id', 1);
    }

    public function test_each_manufacturing_screen_renders_with_its_forms_and_assets(): void
    {
        foreach (['' => 'Manufacturing', '/raw-materials' => 'Raw materials', '/components' => 'Manufactured components', '/products' => 'Manufacturing products', '/boms' => 'Bills of materials', '/production-orders' => 'Production orders', '/history' => 'Production history', '/stock-movements' => 'Stock movements', '/reports' => 'Manufacturing reports'] as $path => $title) {
            $this->get('/manufacturing'.$path, ['Accept' => 'text/html'])->assertOk()->assertSee($title)->assertSee('manufacturing.js')->assertSee('mfg-dialog-form')->assertSee('mfg-location');
        }
    }

    public function test_json_contract_still_works_when_the_same_route_has_a_browser_page(): void
    {
        $this->getJson('/manufacturing')->assertOk()->assertJsonStructure(['orders_by_status', 'wip_cost', 'recent_batches']);
        $this->getJson('/manufacturing/boms')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/manufacturing/products')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_stock_table_and_product_details_respect_warehouse_permissions(): void
    {
        $raw = $this->product(); $this->receipt($raw, '10'); $this->receipt($raw, '3', '10', 3);
        $product = DB::table('variations')->where('id', $raw)->value('product_id'); $this->locations = [3];
        $this->getJson('/manufacturing/products')->assertOk()->assertJsonPath('data.0.stock', '3.0000');
        $this->getJson('/manufacturing/products/'.$product.'/details')->assertOk()->assertJsonCount(1, 'stock')->assertJsonPath('stock.0.location_id', 3)->assertJsonPath('purchases.total', 1);
        $this->getJson('/manufacturing/products?location_id=1')->assertForbidden();
    }

    public function test_lookups_do_not_expose_another_business_or_nonmanufactured_outputs(): void
    {
        $this->product(); $component = $this->product('component', 3); $this->product('component', 3, 2);
        $this->getJson('/manufacturing/lookups?output=1')->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', $component)->assertJsonCount(1, 'operators');
    }

    public function test_order_page_has_snapshot_requirements_and_batch_actions(): void
    {
        $raw = $this->product(); $component = $this->product('component', 3); $this->receipt($raw, '100'); $bom = $this->bom($component, $raw, '1'); $id = $this->order($bom['bom_version_id'], '10');
        $this->get('/manufacturing/production-orders/'.$id, ['Accept' => 'text/html'])->assertOk()->assertSee('mfg-order-header')->assertSee('Production batches');
        $this->getJson('/manufacturing/production-orders/'.$id.'/requirements?quantity=4')->assertOk()->assertJsonPath('materials.0.quantity', '4.0000')->assertJsonPath('materials.0.shortage', '0.0000');
        $this->getJson('/manufacturing/production-orders/'.$id)->assertOk()->assertJsonPath('requirements.materials.0.quantity', '10.0000');
    }

    public function test_classification_and_recipe_template_on_existing_product_form_create_no_stock(): void
    {
        $raw = $this->product(); $source = $this->product('component', 3); $target = $this->product('normal', 3); $bom = $this->bom($source, $raw, '40', 2);
        $product = Product::findOrFail(DB::table('variations')->where('id', $target)->value('product_id'));
        $request = Request::create('/products', 'POST', ['manufacturing_type' => 'finished_good', 'manufacturing_allow_purchase' => '0', 'manufacturing_notes' => 'Assembly line A', 'manufacturing_bom_template_id' => $bom['bom_version_id']]);
        DB::transaction(fn () => app(ProductFormService::class)->save($this->operator, $product, $request));
        $this->assertSame('finished_good', $product->fresh()->manufacturing_type);
        $this->assertSame('0.0000', $this->balance($target)); $this->assertSame(3, Product::count());
        $this->assertSame(2, DB::table('mfg_boms')->count());
        $this->assertSame(0, DB::table('mfg_production_batches')->count());
        $this->assertEquals(0, DB::table('mfg_product_settings')->where('product_id', $product->id)->value('allow_purchase'));
    }

    public function test_product_form_denial_rolls_back_the_existing_product_save(): void
    {
        $variation = $this->product('normal', 3); $product = Product::findOrFail(DB::table('variations')->where('id', $variation)->value('product_id'));
        $this->denied = ['manufacturing.components.manage'];
        try { DB::transaction(fn () => app(ProductFormService::class)->save($this->operator, $product, Request::create('/products', 'POST', ['manufacturing_type' => 'component']))); $this->fail('Expected authorization failure.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertSame('normal', $product->fresh()->manufacturing_type);
    }

    public function test_existing_add_product_controller_saves_manufactured_definition_without_stock(): void
    {
        $products = \Mockery::mock(\App\Utils\ProductUtil::class)->makePartial();
        $products->shouldReceive('uploadFile')->once()->andReturn(null);
        $products->shouldReceive('createSingleProductVariation')->once()->andReturnUsing(function ($productId) { DB::table('variations')->insert(['product_id' => $productId, 'product_variation_id' => $productId, 'name' => 'DUMMY']); });
        $modules = \Mockery::mock(\App\Utils\ModuleUtil::class); $modules->shouldReceive('getModuleFormField')->andReturn([]);
        $request = Request::create('/products', 'POST', ['name' => 'New manufactured bulb', 'sku' => 'MFG-FORM-001', 'type' => 'single', 'unit_id' => 3, 'enable_stock' => 1, 'manufacturing_type' => 'finished_good', 'manufacturing_allow_purchase' => 0]);
        $request->setUserResolver(fn () => $this->operator); $request->setLaravelSession(session()->driver());
        $response = (new \App\Http\Controllers\ProductController($products, $modules))->store($request);
        $this->assertSame(302, $response->getStatusCode()); $this->assertSame(1, Product::count());
        $this->assertSame('finished_good', Product::first()->manufacturing_type);
        $this->assertSame(0, DB::table('variation_location_details')->count());
    }

    public function test_add_product_checks_manufacturing_permission_before_file_uploads_or_inserts(): void
    {
        $products = \Mockery::mock(\App\Utils\ProductUtil::class)->makePartial(); $products->shouldNotReceive('uploadFile');
        $modules = \Mockery::mock(\App\Utils\ModuleUtil::class); $this->denied = ['manufacturing.components.manage'];
        $request = Request::create('/products', 'POST', ['manufacturing_type' => 'component', 'type' => 'single', 'enable_stock' => 1]);
        $request->setUserResolver(fn () => $this->operator); $request->setLaravelSession(session()->driver());
        try { (new \App\Http\Controllers\ProductController($products, $modules))->store($request); $this->fail('Expected manufacturing permission denial.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { $this->assertSame(403, $error->getStatusCode()); }
        $this->assertSame(0, Product::count());
    }

    public function test_report_exports_require_both_report_and_export_permissions(): void
    {
        $this->denied = ['view_export_buttons']; $this->get('/manufacturing/reports/export')->assertForbidden();
        $this->denied = ['manufacturing.reports.view']; $this->get('/manufacturing/reports', ['Accept' => 'text/html'])->assertForbidden();
    }

    public function test_csv_export_uses_existing_excel_utility_and_the_selected_warehouse(): void
    {
        $raw = $this->product(); $component = $this->product('component', 3); $bom = $this->bom($component, $raw); $this->order($bom['bom_version_id']);
        $response = $this->get('/manufacturing/reports/export?location_id=1'); $response->assertOk()->assertDownload('manufacturing-orders.csv');
    }

    public function test_new_and_existing_product_blades_compile_and_the_manufacturing_partial_renders(): void
    {
        foreach (['product/create.blade.php','product/edit.blade.php','product/view-modal.blade.php'] as $path) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/'.$path)));
            $this->assertStringContainsString('manufacturing::', $compiled);
            $file = tempnam(sys_get_temp_dir(), 'mfg-blade-'); file_put_contents($file, $compiled);
            try { exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $status); $this->assertSame(0, $status, implode("\n", $output)); }
            finally { unlink($file); }
        }
        $html = view('manufacturing::product_fields')->render(); $this->assertStringContainsString('name="manufacturing_type"', $html); $this->assertStringContainsString('Manufactured finished product', $html);
    }

    public function test_export_browser_fixture_when_requested(): void
    {
        if (getenv('MFG_EXPORT_FRONTEND_FIXTURE') !== '1') { $this->markTestSkipped('Opt-in browser fixture export.'); }
        \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8769');
        $raw = $this->product(); $component = $this->product('component', 3); $this->receipt($raw, '100');
        DB::table('products')->where('id', DB::table('variations')->where('id', $raw)->value('product_id'))->update(['name' => 'Raw plastic pellets', 'sku' => 'RM-PLASTIC', 'alert_quantity' => '120']);
        DB::table('products')->where('id', DB::table('variations')->where('id', $component)->value('product_id'))->update(['name' => 'Plastic bulb body', 'sku' => 'COMP-BODY']);
        $bom = $this->bom($component, $raw, '40', 2); $id = $this->order($bom['bom_version_id'], '100');
        $path = storage_path('framework/testing/mfg-frontend-preview'); if (!is_dir($path)) mkdir($path, 0777, true);
        foreach (['dashboard' => '', 'materials' => '/raw-materials', 'boms' => '/boms', 'orders' => '/production-orders', 'order' => '/production-orders/'.$id] as $name => $route) { file_put_contents($path.'/'.$name.'.html', $this->get('/manufacturing'.$route, ['Accept' => 'text/html'])->assertOk()->getContent()); }
        $api = []; foreach (['/manufacturing', '/manufacturing/products', '/manufacturing/boms', '/manufacturing/boms/'.$bom['bom_id'], '/manufacturing/lookups', '/manufacturing/bom-versions/'.$bom['bom_version_id'].'/requirements?quantity=100&location_id=1', '/manufacturing/production-orders', '/manufacturing/production-orders/'.$id, '/manufacturing/production-orders/'.$id.'/requirements?quantity=100'] as $route) { $api[$route] = $this->getJson($route)->assertOk()->json(); }
        file_put_contents($path.'/api.json', json_encode($api, JSON_PRETTY_PRINT)); $this->assertFileExists($path.'/dashboard.html');
    }

    public function test_manufacturing_navigation_uses_the_existing_menu_builder_and_permissions(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/AdminSidebarMenu.php'));
        preg_match_all('/->can\(\x27([^\x27]+)\x27\)/', $source, $matches);
        $this->denied = array_values(array_filter($matches[1], fn ($ability) => !str_starts_with($ability, 'manufacturing.')));
        $this->denied[] = 'manufacturing.reports.view';
        $request = Request::create('/manufacturing'); $request->setUserResolver(fn () => $this->operator);
        (new \App\Http\Middleware\AdminSidebarMenu())->handle($request, fn () => response('ok'));
        $html = \Menu::get('admin-sidebar-menu');
        $this->assertStringContainsString('Raw Materials', $html); $this->assertStringNotContainsString('Manufacturing Reports', $html);
        $this->denied[] = 'manufacturing.view';
        (new \App\Http\Middleware\AdminSidebarMenu())->handle($request, fn () => response('ok'));
        $this->assertStringNotContainsString('Raw Materials', \Menu::get('admin-sidebar-menu'));
    }
}
