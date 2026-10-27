<?php

namespace Modules\B2B\Services;

use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Catalog
{
    public function __construct(private Access $access) {}

    public function variation(User $user, int $id, bool $enrolled = false, bool $allowInactive = false): object
    {
        $q = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->join('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('b2b_product_settings as b', 'b.product_id', '=', 'p.id')
            ->where('v.id', $id)->where('p.business_id', $user->business_id)->where('u.business_id', $user->business_id)
            ->whereNull('v.deleted_at')->where('p.enable_stock', 1)->whereIn('p.type', ['single', 'variable']);
        if (! $allowInactive) { $q->where('p.is_inactive', 0)->whereNull('u.deleted_at'); }
        if ($enrolled) { $q->where('b.business_id', $user->business_id)->where('b.is_active', 1); }
        $v = $q->first(['v.id', 'v.product_id', 'v.product_variation_id', 'v.name as variation_name', 'v.sub_sku', 'p.name', 'p.sku', 'p.unit_id', 'u.actual_name as unit_name', 'u.allow_decimal', 'b.is_active as b2b_active']);
        if (! $v) { throw ValidationException::withMessages(['variation_id' => 'Choose an active, stock-tracked product in this business that is enabled for the selected B2B action.']); }
        return $v;
    }

    public function quantity($value, object $variation): string
    {
        $qty = D::positive(D::value($value));
        if (! $variation->allow_decimal && D::cmp($qty, bcadd($qty, '0', 0)) !== 0) { throw ValidationException::withMessages(['quantity' => 'This product requires whole quantities.']); }
        return $qty;
    }

    public function bucket(User $user, int $mainId, bool $create = false): ?int
    {
        $parent = $this->access->location($user, $mainId);
        $id = DB::table('b2b_locations')->where('business_id', $user->business_id)->where('main_location_id', $mainId)->value('stock_location_id');
        if (! $id && $create) {
            if (DB::transactionLevel() < 1) { throw new \LogicException('Creating a stock bucket requires the posting transaction.'); }
            // Internal locations are never ordinary active retail/manufacturing locations.
            $fields = ['business_id' => $user->business_id, 'name' => mb_substr($parent->name, 0, 180).' / B2B stock', 'is_active' => 0];
            foreach (['invoice_scheme_id', 'invoice_layout_id', 'country', 'state', 'city', 'zip_code', 'landmark'] as $column) {
                if (property_exists($parent, $column)) { $fields[$column] = $parent->$column; }
            }
            $id = DB::table('business_locations')->insertGetId($fields);
            DB::table('b2b_locations')->insert(['business_id' => $user->business_id, 'main_location_id' => $mainId, 'stock_location_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
        return $id ? (int) $id : null;
    }

    public function stock(int $variationId, ?int $locationId): string
    {
        return $locationId ? D::value((string) (DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $locationId)->sum('qty_available') ?: '0')) : '0.0000';
    }

    public function customer(User $user, int $id, bool $active = true): object
    {
        $q = DB::table('contacts as c')->leftJoin('b2b_customer_profiles as b', function ($join) use ($user) {
                $join->on('b.contact_id', '=', 'c.id')->where('b.business_id', '=', $user->business_id);
            })
            ->where('c.id', $id)->where('c.business_id', $user->business_id)
            ->whereIn('c.type', ['customer', 'both'])->whereNull('c.deleted_at');
        if ($active) { $q->where('c.contact_status', 'active')->where(function ($w) { $w->whereNull('b.contact_id')->orWhere('b.is_active', 1); }); }
        $this->access->contactScope($q, $user);
        $customer = $q->first(['c.*', DB::raw("COALESCE(b.credit_limit, '0') as b2b_credit_limit"), DB::raw('COALESCE(b.payment_term_days, 0) as payment_term_days')]); abort_unless($customer, 404);
        return $customer;
    }
}
