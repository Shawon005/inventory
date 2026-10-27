<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\ManufacturingAccess;

class ManufacturingPageController extends Controller
{
    public function __construct(private ManufacturingAccess $access) {}

    public function screen(Request $request, string $screen, ?int $id = null)
    {
        $titles = ['dashboard' => 'Manufacturing', 'products' => 'Manufacturing products', 'materials' => 'Raw materials', 'components' => 'Manufactured components', 'boms' => 'Bills of materials', 'orders' => 'Production orders', 'order' => 'Production order', 'history' => 'Production history', 'movements' => 'Stock movements', 'reports' => 'Manufacturing reports', 'product' => 'Manufacturing details'];
        abort_unless(isset($titles[$screen]), 404);
        $businessId = $this->access->authorize($request->user(), 'view');
        if ($screen === 'reports') { $this->access->authorize($request->user(), 'reports.view'); }
        if ($screen === 'order') { app(\Modules\Manufacturing\Services\ProductionService::class)->order($request->user(), $id); }
        if ($screen === 'product') { abort_unless(DB::table('products')->where('business_id', $businessId)->where('id', $id)->exists(), 404); }
        $locations = DB::table('business_locations')->where('business_id', $businessId);
        \App\Support\InventoryChannel::mainLocations($locations, 'id');
        $permitted = $request->user()->permitted_locations($businessId);
        if ($permitted !== 'all') { $locations->whereIn('id', $permitted); }
        $locations = $locations->orderBy('name')->get(['id', 'name', 'is_active']);
        $abilities = [];
        foreach (['view', 'materials.manage', 'components.manage', 'bom.manage', 'orders.create', 'orders.start', 'orders.complete', 'stock.adjust', 'reports.view'] as $ability) { $abilities[$ability] = $request->user()->can('manufacturing.'.$ability); }
        $title = $titles[$screen];
        $config = ['base' => url('manufacturing'), 'screen' => $screen, 'id' => $id, 'abilities' => $abilities, 'warehouses' => $locations->pluck('name', 'id'), 'productCreateUrl' => action([\App\Http\Controllers\ProductController::class, 'create']), 'productBase' => url('products'), 'purchaseCreateUrl' => url('purchases/create')];
        return view('manufacturing::workspace', compact('screen', 'title', 'config', 'locations', 'id'));
    }

    public function materials(Request $request) { return $this->screen($request, 'materials'); }
    public function components(Request $request) { return $this->screen($request, 'components'); }
    public function history(Request $request) { return $this->screen($request, 'history'); }
    public function product(Request $request, int $id) { return $this->screen($request, 'product', $id); }
}
