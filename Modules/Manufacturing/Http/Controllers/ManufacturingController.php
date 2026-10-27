<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Manufacturing\Entities\Bom;
use Modules\Manufacturing\Entities\ProductionOrder;
use Modules\Manufacturing\Http\Requests\ManufacturingRequest;
use Modules\Manufacturing\Services\BomService;
use Modules\Manufacturing\Services\ManufacturingAccess;
use Modules\Manufacturing\Services\ManufacturingCatalog;
use Modules\Manufacturing\Services\ProductionService;

class ManufacturingController extends Controller
{
    public function __construct(private ManufacturingAccess $access, private ManufacturingCatalog $catalog, private BomService $boms, private ProductionService $production) {}

    public function index(Request $request)
    {
        $this->access->authorize($request->user(), 'view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'dashboard'); }
        $batches = DB::table('mfg_production_batches as b')->join('mfg_production_orders as o', 'o.id', '=', 'b.order_id')->where('o.business_id', $request->user()->business_id);
        app(\Modules\Manufacturing\Services\ManufacturingReadService::class)->scopeLocations($batches, $request->user(), 'o.location_id', $request->integer('location_id') ?: null);
        return response()->json(['orders_by_status' => $this->ordersQuery($request)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'wip_cost' => D::value((string) (clone $batches)->where('b.status', 'in_progress')->sum('b.total_cost')),
            'recent_batches' => $batches->orderByDesc('b.id')->limit(8)->get(['b.*', 'o.order_number'])]);
    }

    public function products(Request $request)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'products'); }
        $request->validate(['type' => ['nullable', Rule::in(['normal', 'raw_material', 'component', 'finished_good'])], 'search' => 'nullable|string|max:191', 'location_id' => 'nullable|integer|min:1']);
        return response()->json(app(\Modules\Manufacturing\Services\ManufacturingReadService::class)->products($request->user(), $request->input('type'), $request->input('search'), $request->integer('location_id') ?: null));
    }

    public function configureProduct(Request $request, int $id)
    {
        $businessId = $this->access->business($request->user());
        $data = $request->validate([
            'manufacturing_type' => ['required', Rule::in(['normal', 'raw_material', 'component', 'finished_good'])],
            'allow_purchase' => 'required|boolean', 'production_notes' => 'nullable|string|max:10000', 'is_inactive' => 'sometimes|boolean',
        ]);
        return DB::transaction(function () use ($request, $id, $businessId, $data) {
            DB::table('business')->where('id', $businessId)->lockForUpdate()->first();
            $product = DB::table('products')->where('id', $id)->where('business_id', $businessId)->lockForUpdate()->first();
            abort_unless($product, 404);
            $types = array_diff(array_unique([$product->manufacturing_type, $data['manufacturing_type']]), ['normal']);
            foreach ($types ?: ['normal'] as $type) {
                $ability = $type === 'raw_material' ? 'materials.manage' : 'components.manage';
                $this->access->authorize($request->user(), $ability);
            }
            abort_unless($data['manufacturing_type'] === 'normal' || ($product->enable_stock && in_array($product->type, ['single', 'variable'])), 422, 'Manufacturing requires a stock-tracked single or variable product.');
            if ($product->manufacturing_type !== $data['manufacturing_type']) {
                $ids = DB::table('variations')->where('product_id', $id)->pluck('id');
                abort_if(DB::table('mfg_boms')->whereIn('variation_id', $ids)->exists() || DB::table('mfg_bom_items')->whereIn('variation_id', $ids)->exists(), 422, 'The product classification is used by a BOM and cannot be changed.');
            }
            DB::table('products')->where('id', $id)->update(['manufacturing_type' => $data['manufacturing_type'], 'is_inactive' => $data['is_inactive'] ?? $product->is_inactive, 'updated_at' => now()]);
            DB::table('mfg_product_settings')->updateOrInsert(['business_id' => $businessId, 'product_id' => $id], ['allow_purchase' => $data['allow_purchase'], 'production_notes' => $data['production_notes'] ?? null, 'updated_at' => now()]);
            return response()->json(['product_id' => $id, 'manufacturing_type' => $data['manufacturing_type']]);
        });
    }

    public function bomIndex(Request $request)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'boms'); }
        $request->validate(['search' => 'nullable|string|max:191', 'variation_id' => 'nullable|integer|min:1']);
        $query = Bom::where('business_id', $businessId)->with('versions');
        if ($request->filled('search')) { $query->where('name', 'like', '%'.$request->search.'%'); }
        if ($request->filled('variation_id')) { $query->where('variation_id', $request->variation_id); }
        return response()->json($query->orderByDesc('id')->paginate(25));
    }

    public function bomShow(Request $request, int $id)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        return response()->json(Bom::where('business_id', $businessId)->with('versions.items.variation.product')->findOrFail($id));
    }

    public function bomStore(ManufacturingRequest $request)
    {
        return response()->json($this->boms->save($request->user(), $request->payload(), $request->operationKey()), 201);
    }

    public function bomRevise(ManufacturingRequest $request, int $id)
    {
        return response()->json($this->boms->save($request->user(), $request->payload(), $request->operationKey(), $id), 201);
    }

    public function bomActive(Request $request, int $id)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $this->boms->setActive($request->user(), $id, (bool) $data['is_active']);
        return response()->json(['bom_id' => $id, 'is_active' => (bool) $data['is_active']]);
    }

    public function requirements(Request $request, int $versionId)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        $request->validate(['quantity' => 'required', 'location_id' => 'required|integer|min:1']);
        $this->access->location($request->user(), $request->location_id);
        $quantity = D::positive(D::value($request->quantity));
        $snapshot = $this->boms->snapshot($businessId, $versionId);
        $result = $this->boms->requirements($snapshot, $quantity);
        $result['materials'] = array_map(function ($row) use ($request) {
            $row['available'] = $this->catalog->stock($row['variation_id'], $request->location_id);
            $shortage = D::sub($row['quantity'], $row['available']);
            $row['shortage'] = D::cmp($shortage, '0') > 0 ? $shortage : '0.0000';
            return $row;
        }, $result['materials']);
        $result['exploded_materials'] = $this->boms->explode($businessId, $versionId, $quantity);
        $result['reservation_supported'] = false;
        return response()->json($this->enrichMaterials($result, $businessId));
    }

    public function orderIndex(Request $request)
    {
        $this->access->authorize($request->user(), 'view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'orders'); }
        $request->validate(['status' => 'nullable|in:draft,planned,in_progress,partially_completed,completed,cancelled', 'search' => 'nullable|string|max:191']);
        $q = $this->ordersQuery($request);
        if ($request->filled('status')) { $q->where('status', $request->status); }
        if ($request->filled('search')) { $q->where('order_number', 'like', '%'.$request->search.'%'); }
        return response()->json($q->orderByDesc('id')->paginate(25));
    }

    public function orderShow(Request $request, int $id)
    {
        $this->access->authorize($request->user(), 'view'); $this->production->order($request->user(), $id);
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'order', $id); }
        $order = ProductionOrder::where('business_id', $request->user()->business_id)->with('batches')->findOrFail($id);
        $ids = $order->batches->pluck('id');
        $requirements = $this->boms->requirements($order->bom_snapshot, (string) $order->planned_quantity);
        foreach ($requirements['materials'] as &$material) { $material['available'] = $this->catalog->stock($material['variation_id'], $order->location_id); }
        return response()->json(['order' => $order, 'requirements' => $this->enrichMaterials($requirements, $request->user()->business_id), 'issues' => DB::table('mfg_material_issue_lines')->whereIn('batch_id', $ids)->get(), 'expenses' => DB::table('mfg_production_expenses')->whereIn('batch_id', $ids)->get()]);
    }

    public function orderStore(ManufacturingRequest $r) { return response()->json($this->production->create($r->user(), $r->payload(), $r->operationKey()), 201); }
    public function orderUpdate(ManufacturingRequest $r, int $id) { return response()->json($this->production->update($r->user(), $id, $r->payload(), $r->operationKey())); }
    public function plan(ManufacturingRequest $r, int $id) { return response()->json($this->production->plan($r->user(), $id, $r->operationKey())); }
    public function start(ManufacturingRequest $r, int $id) { return response()->json($this->production->start($r->user(), $id, $r->payload(), $r->operationKey())); }
    public function receive(ManufacturingRequest $r, int $id, int $batchId) { return response()->json($this->production->receive($r->user(), $id, $batchId, $r->payload(), $r->operationKey())); }
    public function scrap(ManufacturingRequest $r, int $id, int $batchId) { return response()->json($this->production->receive($r->user(), $id, $batchId, $r->payload(), $r->operationKey(), true)); }
    public function materialReturn(ManufacturingRequest $r, int $id, int $batchId) { return response()->json($this->production->returnMaterials($r->user(), $id, $batchId, $r->payload(), $r->operationKey())); }
    public function reverse(ManufacturingRequest $r, int $id, int $batchId)
    {
        $r->validate(['reason' => 'required|string|max:2000']);
        return response()->json($this->production->reverse($r->user(), $id, $batchId, $r->reason, $r->operationKey()));
    }
    public function cancel(ManufacturingRequest $r, int $id)
    {
        $r->validate(['reason' => 'required|string|max:2000']);
        return response()->json($this->production->cancel($r->user(), $id, $r->reason, $r->operationKey()));
    }

    public function movements(Request $request)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'movements'); }
        $q = DB::table('inventory_movements')->where('business_id', $businessId);
        $this->locationFilter($request, $q);
        $request->validate(['variation_id' => 'nullable|integer|min:1', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        if ($request->filled('variation_id')) { $q->where('variation_id', $request->variation_id); }
        if ($request->filled('from')) { $q->whereDate('created_at', '>=', $request->from); }
        if ($request->filled('to')) { $q->whereDate('created_at', '<=', $request->to); }
        return response()->json($q->orderByDesc('id')->paginate(50));
    }

    public function adjustStock(ManufacturingRequest $request, \Modules\Manufacturing\Services\StockAdjustmentService $adjustments)
    {
        return response()->json($adjustments->post($request->user(), $request->payload(), $request->operationKey()), 201);
    }

    public function report(Request $request)
    {
        $this->access->authorize($request->user(), 'reports.view');
        if (! $request->expectsJson()) { return app(ManufacturingPageController::class)->screen($request, 'reports'); }
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $q = $this->ordersQuery($request);
        if ($request->filled('from')) { $q->whereDate('created_at', '>=', $request->from); }
        if ($request->filled('to')) { $q->whereDate('created_at', '<=', $request->to); }
        return response()->json(['summary' => (clone $q)->selectRaw('COUNT(*) as orders, SUM(planned_quantity) as planned_quantity, SUM(accepted_quantity) as accepted_quantity, SUM(defective_quantity) as defective_quantity, SUM(expected_cost) as expected_cost, SUM(actual_cost) as actual_cost')->first(), 'orders' => $q->orderByDesc('id')->paginate(25)]);
    }

    public function lookups(Request $request)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        $request->validate(['search' => 'nullable|string|max:191', 'output' => 'nullable|boolean', 'id' => 'nullable|integer|min:1']);
        $q = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('units as u', 'u.id', '=', 'p.unit_id')->where('p.business_id', $businessId)->where('u.business_id', $businessId)->whereNull('v.deleted_at')->whereNull('u.deleted_at')->where('p.is_inactive', 0)->where('p.enable_stock', 1)->whereIn('p.type', ['single', 'variable']);
        if ($request->boolean('output')) { $q->whereIn('p.manufacturing_type', ['component', 'finished_good']); }
        if ($request->filled('id')) { $q->where('v.id', $request->id); }
        if ($request->filled('search')) { $term = '%'.$request->search.'%'; $q->where(fn ($q) => $q->where('p.name', 'like', $term)->orWhere('p.sku', 'like', $term)->orWhere('v.sub_sku', 'like', $term)); }
        $rows = $q->select('v.id', 'v.name as variation', 'v.sub_sku', 'p.name', 'p.sku', 'p.unit_id', 'p.manufacturing_type', 'u.actual_name as unit', 'u.allow_decimal')->orderBy('p.name')->paginate(30);
        $operators = DB::table('users')->where('business_id', $businessId)->whereNull('deleted_at')->get(['id', 'first_name', 'last_name'])->map(fn ($user) => ['id' => $user->id, 'name' => trim($user->first_name.' '.$user->last_name)]);
        return response()->json(['results' => $rows->items(), 'more' => $rows->hasMorePages(), 'units' => DB::table('units')->where('business_id', $businessId)->whereNull('deleted_at')->get(['id', 'actual_name', 'base_unit_id']), 'operators' => $operators]);
    }

    public function productDetails(Request $request, int $id)
    {
        $this->access->authorize($request->user(), 'view');
        return response()->json(app(\Modules\Manufacturing\Services\ManufacturingReadService::class)->product($request->user(), $id));
    }

    public function orderRequirements(Request $request, int $id)
    {
        $this->access->authorize($request->user(), 'view'); $order = $this->production->order($request->user(), $id);
        $request->validate(['quantity' => 'required']);
        $result = $this->boms->requirements(json_decode($order->bom_snapshot, true), D::positive(D::value($request->quantity)));
        foreach ($result['materials'] as &$material) {
            $material['available'] = $this->catalog->stock($material['variation_id'], $order->location_id);
            $shortage = D::sub($material['quantity'], $material['available']); $material['shortage'] = D::cmp($shortage, '0') > 0 ? $shortage : '0.0000';
        }
        return response()->json($this->enrichMaterials($result, $request->user()->business_id));
    }

    public function history(Request $request)
    {
        $businessId = $this->access->authorize($request->user(), 'view');
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $q = DB::table('mfg_production_batches as b')->join('mfg_production_orders as o', 'o.id', '=', 'b.order_id')->where('o.business_id', $businessId);
        app(\Modules\Manufacturing\Services\ManufacturingReadService::class)->scopeLocations($q, $request->user(), 'o.location_id', $request->integer('location_id') ?: null);
        if ($request->filled('from')) { $q->whereDate('b.created_at', '>=', $request->from); }
        if ($request->filled('to')) { $q->whereDate('b.created_at', '<=', $request->to); }
        return response()->json($q->orderByDesc('b.id')->paginate(25, ['b.*', 'o.order_number', 'o.variation_id', 'o.location_id']));
    }

    public function export(Request $request)
    {
        $this->access->authorize($request->user(), 'reports.view'); abort_unless($request->user()->can('view_export_buttons'), 403);
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $q = $this->ordersQuery($request);
        if ($request->filled('from')) { $q->whereDate('created_at', '>=', $request->from); }
        if ($request->filled('to')) { $q->whereDate('created_at', '<=', $request->to); }
        return \Maatwebsite\Excel\Facades\Excel::download(new \Modules\Manufacturing\Exports\ProductionOrdersExport($q->orderBy('id')), 'manufacturing-orders.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    private function ordersQuery(Request $request)
    {
        $q = DB::table('mfg_production_orders')->where('business_id', $this->access->business($request->user()));
        $this->locationFilter($request, $q); return $q;
    }

    private function enrichMaterials(array $result, int $businessId): array
    {
        $ids = array_column($result['materials'], 'variation_id');
        $units = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('units as u', 'u.id', '=', 'p.unit_id')->where('p.business_id', $businessId)->whereIn('v.id', $ids)->pluck('u.actual_name', 'v.id');
        $boms = DB::table('mfg_boms as b')->join('mfg_bom_versions as v', 'v.bom_id', '=', 'b.id')->where('b.business_id', $businessId)->where('b.is_active', 1)->where('v.is_active', 1)->whereIn('b.variation_id', $ids)->pluck('v.id', 'b.variation_id');
        foreach ($result['materials'] as &$material) { $material['inventory_unit'] = $units[$material['variation_id']] ?? ''; $material['bom_version_id'] = $boms[$material['variation_id']] ?? null; }
        return $result;
    }

    private function locationFilter(Request $request, $query): void
    {
        $request->validate(['location_id' => 'nullable|integer|min:1']);
        if ($request->filled('location_id')) {
            $this->access->location($request->user(), $request->location_id, false); $query->where('location_id', $request->location_id);
        } else {
            $permitted = $request->user()->permitted_locations($request->user()->business_id);
            if ($permitted !== 'all') { $query->whereIn('location_id', $permitted); }
        }
    }
}
