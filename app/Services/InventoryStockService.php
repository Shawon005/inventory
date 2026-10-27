<?php

namespace App\Services;

use App\Support\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared stock balances. Caller owns document/ledger posting in the same transaction. */
class InventoryStockService
{
    public function change(int $businessId, int $productId, int $variationId, int $locationId, string $delta, bool $allowNegative = false, string $channel = 'main'): string
    {
        \App\Support\InventoryChannel::assertLocation($locationId, $channel);
        return DB::transaction(function () use ($businessId, $productId, $variationId, $locationId, $delta, $allowNegative) {
            $variation = DB::table('variations')->where('id', $variationId)->where('product_id', $productId)->whereNull('deleted_at')->lockForUpdate()->first();
            $product = DB::table('products')->where('id', $productId)->where('business_id', $businessId)->first();
            $location = DB::table('business_locations')->where('id', $locationId)->where('business_id', $businessId)->first();
            if (! $variation || ! $product || ! $location) {
                throw ValidationException::withMessages(['stock' => 'Invalid product, variation or warehouse for this business.']);
            }
            if (! $product->enable_stock) {
                return '0.0000';
            }
            // Locking the variation also serializes first receipts when no balance exists yet.
            $query = DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $locationId);
            $rows = (clone $query)->lockForUpdate()->get();
            if ($rows->count() > 1) {
                throw ValidationException::withMessages(['stock' => 'Duplicate warehouse balances require reconciliation before posting.']);
            }
            $row = $rows->first();
            if ($row && (int) $row->product_id !== $productId) {
                throw ValidationException::withMessages(['stock' => 'The warehouse balance references a different product.']);
            }
            $balance = D::add($row ? (string) $row->qty_available : '0', D::value($delta));
            if (! $allowNegative && D::cmp($balance, '0') < 0) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock in the selected warehouse.']);
            }
            if ($row) {
                $query->update(['qty_available' => $balance, 'updated_at' => now()]);
            } else {
                DB::table('variation_location_details')->insert([
                    'product_id' => $productId, 'variation_id' => $variationId,
                    'product_variation_id' => $variation->product_variation_id, 'location_id' => $locationId,
                    'qty_available' => $balance, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $balance;
        });
    }
}
