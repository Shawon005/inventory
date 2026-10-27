<?php

namespace Modules\Manufacturing\Services;

use App\Support\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManufacturingCatalog
{
    public function variation(int $businessId, int $id, bool $output = false, bool $allowInactive = false): object
    {
        $query = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $businessId)->where('u.business_id', $businessId)
            ->where('v.id', $id)->whereNull('v.deleted_at')
            ->where('p.enable_stock', 1)->whereIn('p.type', ['single', 'variable']);
        if (! $allowInactive) { $query->where('p.is_inactive', 0)->whereNull('u.deleted_at'); }
        $row = $query->select('v.id', 'v.product_id', 'v.product_variation_id', 'v.default_purchase_price', 'v.dpp_inc_tax', 'p.name', 'p.unit_id', 'p.manufacturing_type', 'u.allow_decimal')->first();
        if (! $row || ($output && ! in_array($row->manufacturing_type, ['component', 'finished_good'], true))) {
            throw ValidationException::withMessages(['variation_id' => 'Select an active, stock-tracked variation in this business with a valid manufacturing classification.']);
        }
        return $row;
    }

    public function multiplier(int $businessId, int $fromId, int $toId): string
    {
        $units = DB::table('units')->where('business_id', $businessId)->whereNull('deleted_at')->whereIn('id', [$fromId, $toId])->get()->keyBy('id');
        $from = $units->get($fromId); $to = $units->get($toId);
        if (! $from || ! $to || ($from->base_unit_id ?: $from->id) != ($to->base_unit_id ?: $to->id)) {
            throw ValidationException::withMessages(['unit_id' => 'Only units in the configured product unit family can be converted.']);
        }
        $fromFactor = $from->base_unit_id ? (string) $from->base_unit_multiplier : '1';
        $toFactor = $to->base_unit_id ? (string) $to->base_unit_multiplier : '1';
        D::positive($fromFactor, 'unit_id'); D::positive($toFactor, 'unit_id');
        return bcdiv($fromFactor, $toFactor, 8);
    }

    public function quantity(string $base, string $quantity, string $output, bool $fractional = true): string
    {
        $result = D::value(bcdiv(bcmul($base, $quantity, 12), $output, 12));
        if (! $fractional && D::cmp($result, bcadd($result, '0', 0)) !== 0) {
            $result = D::value(bcadd($result, '1', 0));
        }
        return $result;
    }

    public function stock(int $variationId, int $locationId): string
    {
        return D::value((string) (DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $locationId)->sum('qty_available') ?: '0'));
    }
}
