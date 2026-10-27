<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $dealers = DB::table('contacts')
                ->where('is_dealer', true)
                ->whereIn('type', ['customer', 'both'])
                ->get(['id', 'business_id']);

            foreach ($dealers as $dealer) {
                $srIds = DB::table('customers')
                    ->where('business_id', $dealer->business_id)
                    ->where('contact_id', $dealer->id)
                    ->where('employee_type', 'sr')
                    ->orderBy('id')
                    ->pluck('id');

                if ($srIds->isEmpty()) {
                    continue;
                }

                // Keep the first SR as the internal account for this dealer's stock.
                $ownerId = $srIds->first();
                $stocks = DB::table('customer_stocks')
                    ->whereIn('customer_id', $srIds)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('product_id');

                foreach ($stocks as $productStocks) {
                    $retained = $productStocks->firstWhere('customer_id', $ownerId) ?? $productStocks->first();
                    $quantity = $productStocks->sum('quantity');
                    $value = $productStocks->sum(fn ($stock) => $stock->quantity * $stock->purchase_price);

                    DB::table('customer_stocks')->where('id', $retained->id)->update([
                        'customer_id' => $ownerId,
                        'quantity' => $quantity,
                        'purchase_price' => $quantity > 0 ? $value / $quantity : $retained->purchase_price,
                    ]);
                    DB::table('customer_stocks')
                        ->whereIn('id', $productStocks->pluck('id'))
                        ->where('id', '!=', $retained->id)
                        ->delete();
                }

                DB::table('customer_stock_transfers')->whereIn('customer_id', $srIds)->update([
                    'customer_id' => $ownerId,
                ]);
                DB::table('customers')->where('id', $ownerId)->update(['stock_owner_customer_id' => null]);
                DB::table('customers')->whereIn('id', $srIds)->where('id', '!=', $ownerId)->update([
                    'stock_owner_customer_id' => $ownerId,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Shared balances cannot be split back into individual SR balances reliably.
    }
};
