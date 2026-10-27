<?php

namespace Modules\Manufacturing\Services;

use App\Services\InventoryStockService;
use App\Services\PurchaseLayerService;
use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(private ManufacturingAccess $access, private ManufacturingCatalog $catalog, private PostingService $postings, private InventoryStockService $stock, private PurchaseLayerService $layers) {}

    public function post(User $user, array $data, string $key): array
    {
        $businessId = $this->access->authorize($user, 'stock.adjust');
        Validator::make($data, ['variation_id' => 'required|integer|min:1', 'location_id' => 'required|integer|min:1', 'quantity' => 'required', 'reason' => 'required|string|max:2000', 'lot_number' => 'nullable|string|max:191', 'expiry_date' => 'nullable|date_format:Y-m-d|after_or_equal:today'])->validate();
        $this->access->location($user, $data['location_id']);
        return $this->postings->run($user, 'manufacturing.adjustment', $key, $data, function ($postingId) use ($user, $businessId, $data) {
            $variation = $this->catalog->variation($businessId, $data['variation_id']);
            $quantity = D::value($data['quantity']); $positive = D::cmp($quantity, '0') > 0;
            $absolute = $positive ? $quantity : D::sub('0', $quantity); D::positive($absolute);
            if ($variation->manufacturing_type === 'normal' || (! $variation->allow_decimal && D::cmp($absolute, bcadd($absolute, '0', 0)) !== 0)) {
                throw ValidationException::withMessages(['quantity' => 'Select a manufacturing product and a valid quantity in its base unit.']);
            }
            $this->stock->change($businessId, $variation->product_id, $variation->id, $data['location_id'], $quantity);
            $transactionId = DB::table('transactions')->insertGetId([
                'business_id' => $businessId, 'location_id' => $data['location_id'], 'type' => $positive ? 'opening_stock' : 'stock_adjustment',
                'status' => $positive ? 'received' : 'final', 'payment_status' => 'paid',
                'transaction_date' => now(), 'created_by' => $user->id, 'additional_notes' => 'Manufacturing adjustment: '.$data['reason'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $cost = '0.0000';
            if ($positive) {
                if (! array_key_exists('unit_cost', $data)) { throw ValidationException::withMessages(['unit_cost' => 'A positive adjustment requires an explicit unit cost.']); }
                $unitCost = D::positive(D::value($data['unit_cost']), 'unit_cost', true); $cost = D::mul($absolute, $unitCost);
                DB::table('purchase_lines')->insert([
                    'transaction_id' => $transactionId, 'product_id' => $variation->product_id, 'variation_id' => $variation->id,
                    'quantity' => $absolute, 'purchase_price' => $unitCost, 'purchase_price_inc_tax' => $unitCost, 'pp_without_discount' => $unitCost, 'item_tax' => '0',
                    'lot_number' => $data['lot_number'] ?? null, 'exp_date' => $data['expiry_date'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_locations')->updateOrInsert(['product_id' => $variation->product_id, 'location_id' => $data['location_id']], []);
            } else {
                $allocations = $this->layers->allocate($businessId, $data['location_id'], $variation->product_id, $variation->id, $absolute, 'quantity_adjusted');
                foreach ($allocations as $allocation) { $cost = D::add($cost, $allocation['total_cost']); }
                $unitCost = D::div($cost, $absolute);
                $lineId = DB::table('stock_adjustment_lines')->insertGetId([
                    'transaction_id' => $transactionId, 'product_id' => $variation->product_id, 'variation_id' => $variation->id,
                    'quantity' => $absolute, 'unit_price' => $unitCost, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($allocations as $allocation) {
                    DB::table('transaction_sell_lines_purchase_lines')->insert(['stock_adjustment_line_id' => $lineId, 'purchase_line_id' => $allocation['purchase_line_id'], 'quantity' => $allocation['quantity'], 'qty_returned' => '0', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('transactions')->where('id', $transactionId)->update(['ref_no' => 'MFG-ADJ-'.$transactionId, 'total_before_tax' => $cost, 'final_total' => $cost]);
            DB::table('inventory_movements')->insert([
                'business_id' => $businessId, 'location_id' => $data['location_id'], 'product_id' => $variation->product_id,
                'variation_id' => $variation->id, 'posting_id' => $postingId, 'movement_type' => 'manufacturing_adjustment',
                'reference_type' => 'transaction', 'reference_id' => $transactionId, 'transaction_id' => $transactionId,
                'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $positive ? $cost : D::sub('0', $cost), 'created_by' => $user->id, 'created_at' => now(),
            ]);
            return ['transaction_id' => $transactionId, 'quantity' => $quantity, 'total_cost' => $cost];
        });
    }

    public function reconcile(User $user, int $variationId, int $locationId, string $desired): void
    {
        abort_unless($user->can('superadmin') || $user->can('manufacturing.stock.adjust'), 403);
        $this->access->location($user, $locationId, false);
        D::positive($desired, 'stock', true);
        $this->postings->run($user, 'stock.reconcile', 'reconcile-'.\Illuminate\Support\Str::uuid(), ['variation_id' => $variationId, 'location_id' => $locationId, 'desired' => $desired], function ($postingId) use ($user, $variationId, $locationId, $desired) {
            $v = $this->catalog->variation($user->business_id, $variationId, false, true);
            if (! $v->allow_decimal && D::cmp($desired, bcadd($desired, '0', 0)) !== 0) {
                throw ValidationException::withMessages(['stock' => 'This stock unit does not allow a fractional count.']);
            }
            DB::table('variations')->where('id', $variationId)->lockForUpdate()->first();
            $delta = D::sub($desired, $this->catalog->stock($variationId, $locationId));
            if (D::cmp($delta, '0') !== 0) {
                $this->stock->change($user->business_id, $v->product_id, $variationId, $locationId, $delta);
                DB::table('inventory_movements')->insert([
                    'business_id' => $user->business_id, 'location_id' => $locationId, 'product_id' => $v->product_id, 'variation_id' => $variationId,
                    'posting_id' => $postingId, 'movement_type' => 'stock_reconciliation', 'reference_type' => 'inventory_posting', 'reference_id' => $postingId,
                    'quantity' => $delta, 'unit_cost' => '0', 'total_cost' => '0', 'created_by' => $user->id, 'created_at' => now(),
                ]);
            }
            return ['balance' => $desired];
        });
    }
}
