<?php

namespace App\Services;

use App\Support\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Allocate actual cost once from eligible existing purchase layers. Caller holds stock locks. */
class PurchaseLayerService
{
    public function allocate(int $businessId, int $locationId, int $productId, int $variationId, string $quantity, string $counter): array
    {
        if (DB::transactionLevel() < 1) { throw new \LogicException('Cost allocation requires an enclosing inventory transaction.'); }
        if (! in_array($counter, ['mfg_quantity_used', 'quantity_adjusted', 'quantity_sold'], true)) { throw new \InvalidArgumentException('Invalid stock usage counter.'); }
        $method = DB::table('business')->where('id', $businessId)->value('accounting_method');
        if (! in_array($method, ['fifo', 'lifo'], true)) {
            throw ValidationException::withMessages(['stock' => 'Posting requires the configured FIFO or LIFO valuation method. AVCO requires an explicit implementation.']);
        }
        $layers = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.location_id', $locationId)
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])->where('t.status', 'received')
            ->where('pl.variation_id', $variationId)->where('pl.product_id', $productId)
            ->whereRaw('pl.quantity > pl.quantity_sold + pl.quantity_adjusted + pl.quantity_returned + pl.mfg_quantity_used')
            ->where(fn ($q) => $q->whereNull('pl.exp_date')->orWhere('pl.exp_date', '>=', now()->toDateString()))
            ->orderBy('t.transaction_date', $method === 'lifo' ? 'desc' : 'asc')->orderBy('pl.id', $method === 'lifo' ? 'desc' : 'asc')
            ->select('pl.*')->lockForUpdate()->get();
        $remaining = D::positive($quantity); $allocations = [];
        foreach ($layers as $layer) {
            $available = (string) $layer->quantity;
            foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) { $available = D::sub($available, (string) $layer->$field); }
            $take = D::cmp($remaining, $available) <= 0 ? $remaining : $available;
            if (D::cmp($take, '0') <= 0) { continue; }
            // Existing stock valuation and retail COGS use purchase_price_inc_tax.
            $unitCost = D::positive(D::value((string) $layer->purchase_price_inc_tax), 'purchase_price_inc_tax', true);
            DB::table('purchase_lines')->where('id', $layer->id)->update([$counter => D::add((string) $layer->$counter, $take)]);
            $allocations[] = ['purchase_line_id' => $layer->id, 'quantity' => $take, 'unit_cost' => $unitCost, 'total_cost' => D::mul($take, $unitCost)];
            $remaining = D::sub($remaining, $take);
            if (D::cmp($remaining, '0') === 0) { break; }
        }
        if (D::cmp($remaining, '0') !== 0) {
            throw ValidationException::withMessages(['stock' => 'Insufficient eligible purchase-layer stock. Check warehouse, lots, expiry and stock reconciliation.']);
        }
        return $allocations;
    }
}
