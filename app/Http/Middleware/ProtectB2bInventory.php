<?php

namespace App\Http\Middleware;

use App\Support\InventoryChannel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** A main POS, purchase or transfer request cannot name a hidden B2B stock location. */
class ProtectB2bInventory
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->routeIs('b2b.*') && InventoryChannel::installed()) {
            $ids = []; $collect = function (array $values) use (&$collect, &$ids): void {
                foreach ($values as $key => $value) {
                    if (in_array($key, ['location_id', 'transfer_location_id', 'from_location_id', 'to_location_id', 'purchase_location_id', 'product_locations', 'location_ids'], true)) {
                        foreach ((array) $value as $id) { if (is_scalar($id) && ctype_digit((string) $id)) { $ids[] = (int) $id; } }
                    } elseif (is_array($value)) { $collect($value); }
                }
            };
            $collect($request->all());
            abort_if($ids && DB::table('b2b_locations')->whereIn('stock_location_id', $ids)->exists(), 403, 'Use the dedicated B2B inventory workflow.');
        }
        return $next($request);
    }
}
