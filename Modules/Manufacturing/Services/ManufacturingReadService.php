<?php

namespace Modules\Manufacturing\Services;

use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;

class ManufacturingReadService
{
    public function __construct(private ManufacturingAccess $access) {}

    public function scopeLocations($query, User $user, string $column, ?int $locationId = null): void
    {
        \App\Support\InventoryChannel::mainLocations($query, $column);
        if ($locationId) { $this->access->location($user, $locationId, false); $query->where($column, $locationId); }
        else { $ids = $user->permitted_locations($user->business_id); if ($ids !== 'all') { $query->whereIn($column, $ids); } }
    }

    public function products(User $user, ?string $type, ?string $search, ?int $locationId)
    {
        $q = DB::table('products as p')->leftJoin('units as u', 'u.id', '=', 'p.unit_id')->leftJoin('mfg_product_settings as s', 's.product_id', '=', 'p.id')->where('p.business_id', $user->business_id);
        if ($type) { $q->where('p.manufacturing_type', $type); } else { $q->where('p.manufacturing_type', '!=', 'normal'); }
        if ($search) { $q->where(fn ($q) => $q->where('p.name', 'like', '%'.$search.'%')->orWhere('p.sku', 'like', '%'.$search.'%')); }
        $rows = $q->select('p.id', 'p.name', 'p.sku', 'p.manufacturing_type', 'p.is_inactive', 'p.alert_quantity', 'u.actual_name as unit', 's.allow_purchase', 's.production_notes')->orderBy('p.name')->paginate(25);
        $stock = DB::table('variation_location_details')->whereIn('product_id', $rows->pluck('id'));
        $this->scopeLocations($stock, $user, 'location_id', $locationId);
        $balances = $stock->groupBy('product_id')->selectRaw('product_id, SUM(qty_available) as quantity')->pluck('quantity', 'product_id');
        foreach ($rows as $row) {
            $row->stock = D::value((string) ($balances[$row->id] ?? '0'));
            $row->low_stock = $row->alert_quantity !== null && D::cmp($row->stock, (string) $row->alert_quantity) <= 0;
            $row->allow_purchase = $row->allow_purchase === null ? true : (bool) $row->allow_purchase;
        }
        return $rows;
    }

    public function product(User $user, int $id): array
    {
        $product = DB::table('products')->where('business_id', $user->business_id)->where('id', $id)->first(); abort_unless($product, 404);
        $variations = DB::table('variations')->where('product_id', $id)->whereNull('deleted_at')->get(['id', 'name', 'sub_sku', 'dpp_inc_tax']);
        $ids = $variations->pluck('id');
        $stock = DB::table('variation_location_details as s')->join('business_locations as l', 'l.id', '=', 's.location_id')->where('s.product_id', $id);
        $this->scopeLocations($stock, $user, 's.location_id');
        $orders = DB::table('mfg_production_orders as o')->where('o.business_id', $user->business_id)->whereIn('o.variation_id', $ids);
        $this->scopeLocations($orders, $user, 'o.location_id');
        $totals = (clone $orders)->selectRaw('SUM(accepted_quantity) as produced_quantity, SUM(actual_cost) as actual_cost')->first();
        $usage = DB::table('mfg_material_issue_lines as i')->join('mfg_production_batches as b', 'b.id', '=', 'i.batch_id')->join('mfg_production_orders as o', 'o.id', '=', 'b.order_id')->where('o.business_id', $user->business_id)->whereIn('i.variation_id', $ids);
        $this->scopeLocations($usage, $user, 'o.location_id');
        $purchases = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')->where('t.business_id', $user->business_id)->where('pl.product_id', $id)->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_return']);
        $this->scopeLocations($purchases, $user, 't.location_id');
        return ['product' => $product, 'settings' => DB::table('mfg_product_settings')->where('business_id', $user->business_id)->where('product_id', $id)->first(), 'variations' => $variations,
            'stock' => $stock->get(['s.variation_id', 's.location_id', 'l.name as warehouse', 's.qty_available']), 'totals' => $totals,
            'boms' => DB::table('mfg_boms as b')->join('mfg_bom_versions as v', 'v.bom_id', '=', 'b.id')->where('b.business_id', $user->business_id)->whereIn('b.variation_id', $ids)->where('v.is_active', 1)->get(['b.id', 'b.variation_id', 'b.is_active', 'v.id as version_id', 'v.version']),
            'orders' => $orders->orderByDesc('o.id')->paginate(10, ['o.*'], 'production_page'),
            'usage' => $usage->orderByDesc('i.id')->paginate(10, ['i.*', 'o.order_number', 'o.id as order_id', 'b.status'], 'usage_page'),
            'purchases' => $purchases->orderByDesc('pl.id')->paginate(10, ['pl.quantity', 'pl.purchase_price_inc_tax', 'pl.lot_number', 't.ref_no', 't.id as transaction_id', 't.transaction_date', 't.status'], 'purchase_page')];
    }
}
