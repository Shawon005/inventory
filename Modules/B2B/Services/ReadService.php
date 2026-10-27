<?php

namespace Modules\B2B\Services;

use App\Support\Decimal as D;
use App\Support\InventoryChannel;
use App\User;
use Illuminate\Support\Facades\DB;

class ReadService
{
    public function __construct(private Access $access, private Catalog $catalog, private SalesService $sales) {}

    public function locations(User $user)
    {
        $q = DB::table('business_locations')->where('business_id', $user->business_id)->where('is_active', 1);
        $this->access->scope($q, $user, 'id');
        return $q->orderBy('name')->get(['id', 'name', 'default_payment_accounts']);
    }

    public function products(User $user, array $filters, bool $pos = false)
    {
        $this->access->read($user);
        $q = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('b2b_product_settings as b', 'b.product_id', '=', 'p.id')->where('p.business_id', $user->business_id)->whereNull('v.deleted_at')->whereNull('u.deleted_at')->where('p.enable_stock', 1)->whereIn('p.type', ['single', 'variable']);
        if ($pos || empty($filters['include_inactive'])) { $q->where('p.is_inactive', 0); }
        if ($pos) { $q->where('b.is_active', 1); }
        if (! empty($filters['enrolled'])) { $q->whereNotNull('b.id'); }
        if (! empty($filters['search'])) { $term = '%'.$filters['search'].'%'; $q->where(fn ($q) => $q->where('p.name', 'like', $term)->orWhere('p.sku', 'like', $term)->orWhere('v.sub_sku', 'like', $term)); }
        if (! empty($filters['id'])) { $q->where('v.id', $filters['id']); }
        $rows = $q->select('v.id', 'v.product_id', 'v.name as variation_name', 'v.sub_sku', 'p.name', 'p.sku', 'p.is_inactive as product_inactive', 'u.actual_name as unit_name', 'u.allow_decimal', 'b.is_active as b2b_active')->orderBy('p.name')->orderBy('v.id')->paginate(25);
        $main = DB::table('variation_location_details')->whereIn('variation_id', $rows->pluck('id')); $this->access->scope($main, $user, 'location_id');
        $b2b = DB::table('variation_location_details as s')->join('b2b_locations as l', 'l.stock_location_id', '=', 's.location_id')->where('l.business_id', $user->business_id)->whereIn('s.variation_id', $rows->pluck('id')); $this->access->scope($b2b, $user, 'l.main_location_id');
        if (! empty($filters['location_id'])) { $this->access->location($user, (int) $filters['location_id']); $main->where('location_id', $filters['location_id']); $b2b->where('l.main_location_id', $filters['location_id']); }
        $mainQty = $main->groupBy('variation_id')->selectRaw('variation_id, SUM(qty_available) as quantity')->pluck('quantity', 'variation_id');
        $b2bQty = $b2b->groupBy('s.variation_id')->selectRaw('s.variation_id, SUM(s.qty_available) as quantity')->pluck('quantity', 'variation_id');
        foreach ($rows as $r) { $r->main_quantity = D::value((string) ($mainQty[$r->id] ?? '0')); $r->b2b_quantity = D::value((string) ($b2bQty[$r->id] ?? '0')); $r->b2b_active = (bool) $r->b2b_active; }
        return $rows;
    }

    public function customers(User $user, array $filters, bool $allContacts = false, bool $pos = false)
    {
        $q = $this->customerQuery($user, $allContacts, $pos);
        if (! empty($filters['search'])) { $term = '%'.$filters['search'].'%'; $q->where(fn ($q) => $q->where('c.name', 'like', $term)->orWhere('c.supplier_business_name', 'like', $term)->orWhere('c.mobile', 'like', $term)); }
        $rows = $q->orderBy('c.name')->select('c.id', 'c.name', 'c.supplier_business_name', 'c.mobile', 'c.email', 'c.address_line_1', 'b.is_active as b2b_active', 'b.credit_limit', 'b.payment_term_days')->paginate(25);
        // Restricted operators see exposure for their permitted warehouses; credit checks use full exposure privately.
        foreach ($rows as $c) {
            $c->outstanding = '0.0000';
            $sales = $this->salesQuery($user, [])->where('t.contact_id', $c->id)->get(['t.id', 't.final_total', 'b.returned_total']);
            foreach ($sales as $s) { $c->outstanding = D::add($c->outstanding, $this->sales->due($s)); }
        }
        return $rows;
    }

    public function posCustomers(User $user)
    {
        $this->access->authorize($user, 'pos.access');
        return $this->customerQuery($user, false, true)
            ->orderByRaw("COALESCE(NULLIF(c.supplier_business_name, ''), c.name)")
            ->get(['c.id', 'c.name', 'c.supplier_business_name', 'c.mobile']);
    }

    private function customerQuery(User $user, bool $allContacts, bool $pos)
    {
        $this->access->read($user);
        if ($allContacts) { $this->access->authorize($user, 'customers.manage'); }
        $q = DB::table('contacts as c')->leftJoin('b2b_customer_profiles as b', 'b.contact_id', '=', 'c.id')->where('c.business_id', $user->business_id)->whereIn('c.type', ['customer', 'both'])->whereNull('c.deleted_at');
        $this->access->contactScope($q, $user);
        if (! $allContacts) {
            $q->where('c.contact_status', 'active');
            if ($pos) {
                // Existing customers are valid for cash B2B sales even before terms are configured.
                // An explicitly disabled B2B profile remains unavailable.
                $q->where(function ($w) use ($user) {
                    $w->whereNull('b.contact_id')
                        ->orWhere(function ($active) use ($user) {
                            $active->where('b.business_id', $user->business_id)->where('b.is_active', 1);
                        });
                });
            } else {
                $q->where('b.business_id', $user->business_id)->where('b.is_active', 1);
            }
        }
        return $q;
    }

    public function salesQuery(User $user, array $filters)
    {
        $q = DB::table('transactions as t')->join('b2b_sale_details as b', 'b.transaction_id', '=', 't.id')->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->join('business_locations as l', 'l.id', '=', 'b.main_location_id')->where('t.business_id', $user->business_id)->where('t.sales_channel', 'b2b')->where('t.type', 'sell');
        $this->access->scope($q, $user, 'b.main_location_id'); $this->access->contactScope($q, $user);
        if (! empty($filters['location_id'])) { $this->access->location($user, (int) $filters['location_id'], false); $q->where('b.main_location_id', $filters['location_id']); }
        if (! empty($filters['contact_id'])) { $q->where('t.contact_id', $filters['contact_id']); }
        if (! empty($filters['search'])) { $q->where('t.invoice_no', 'like', '%'.$filters['search'].'%'); }
        if (! empty($filters['from'])) { $q->where('t.transaction_date', '>=', $filters['from'].' 00:00:00'); }
        if (! empty($filters['to'])) { $q->where('t.transaction_date', '<=', $filters['to'].' 23:59:59'); }
        if (! empty($filters['unpaid'])) { $q->where('t.payment_status', '!=', 'paid'); }
        return $q;
    }

    public function sales(User $user, array $filters)
    {
        $this->access->read($user); $rows = $this->salesQuery($user, $filters)->orderByDesc('t.id')->paginate(25, ['t.id', 't.invoice_no', 't.transaction_date', 't.final_total', 't.payment_status', 'b.due_date', 'b.returned_total', 'b.cost_total', 'b.returned_cost', 'c.name as customer_name', 'l.name as warehouse']);
        foreach ($rows as $r) { $r->net_total = D::sub((string) $r->final_total, (string) $r->returned_total); $r->paid = $this->sales->paid($r->id); $r->due = $this->sales->due($r); $r->overdue = D::cmp($r->due, '0') > 0 && $r->due_date < now()->toDateString(); }
        return $rows;
    }

    public function summary(User $user, array $filters): array
    {
        $this->access->read($user);
        $sales = $this->salesQuery($user, $filters)->get(['t.id', 't.final_total', 'b.returned_total', 'b.cost_total', 'b.returned_cost']);
        $total = '0.0000'; $cost = '0.0000'; $due = '0.0000';
        foreach ($sales as $s) { $total = D::add($total, D::sub((string) $s->final_total, (string) $s->returned_total)); $cost = D::add($cost, D::sub((string) $s->cost_total, (string) $s->returned_cost)); $due = D::add($due, $this->sales->due($s)); }
        $layers = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')->join('b2b_locations as l', 'l.stock_location_id', '=', 't.location_id')->where('t.business_id', $user->business_id)->where('t.status', 'received');
        $this->access->scope($layers, $user, 'l.main_location_id');
        if (! empty($filters['location_id'])) { $layers->where('l.main_location_id', $filters['location_id']); }
        $stockValue = '0.0000';
        foreach ($layers->get(['p.quantity', 'p.quantity_sold', 'p.quantity_adjusted', 'p.quantity_returned', 'p.mfg_quantity_used', 'p.purchase_price_inc_tax']) as $p) {
            $remaining = (string) $p->quantity; foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) { $remaining = D::sub($remaining, (string) $p->$field); }
            $stockValue = D::add($stockValue, D::mul($remaining, (string) $p->purchase_price_inc_tax));
        }
        return ['invoices' => $sales->count(), 'net_sales' => $total, 'cost_of_goods' => $cost, 'gross_profit' => D::sub($total, $cost), 'outstanding' => $due, 'current_stock_value' => $stockValue];
    }

    public function transfers(User $user, array $filters)
    {
        $this->access->read($user); $q = DB::table('b2b_transfers as t')->join('business_locations as l', 'l.id', '=', 't.main_location_id')->where('t.business_id', $user->business_id);
        $this->access->scope($q, $user, 't.main_location_id');
        if (! empty($filters['location_id'])) { $this->access->location($user, (int) $filters['location_id'], false); $q->where('t.main_location_id', $filters['location_id']); }
        return $q->orderByDesc('t.id')->paginate(25, ['t.id', 't.reference', 't.direction', 'l.name as warehouse', 't.created_at', 't.notes']);
    }

    public function movements(User $user, array $filters)
    {
        $this->access->read($user);
        $q = DB::table('inventory_movements as m')->leftJoin('b2b_locations as bl', 'bl.stock_location_id', '=', 'm.location_id')
            ->join('products as p', 'p.id', '=', 'm.product_id')->where('m.business_id', $user->business_id)->where('m.movement_type', 'like', 'b2b_%');
        $this->access->scope($q, $user, DB::raw('COALESCE(bl.main_location_id, m.location_id)'));
        if (! empty($filters['location_id'])) { $this->access->location($user, (int) $filters['location_id'], false); $q->whereRaw('COALESCE(bl.main_location_id, m.location_id) = ?', [(int) $filters['location_id']]); }
        if (! empty($filters['from'])) { $q->where('m.created_at', '>=', $filters['from'].' 00:00:00'); }
        if (! empty($filters['to'])) { $q->where('m.created_at', '<=', $filters['to'].' 23:59:59'); }
        return $q->orderByDesc('m.id')->paginate(25, ['m.*', 'p.name as product_name', 'bl.main_location_id as b2b_warehouse_id']);
    }
}
