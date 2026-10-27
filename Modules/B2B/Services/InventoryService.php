<?php

namespace Modules\B2B\Services;

use App\Services\InventoryStockService;
use App\Services\PurchaseLayerService;
use App\Support\Decimal as D;
use App\Support\InventoryChannel;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Manufacturing\Services\PostingService;

class InventoryService
{
    public function __construct(private Access $access, private Catalog $catalog, private PostingService $postings, private InventoryStockService $stock, private PurchaseLayerService $layers) {}

    public function transfer(User $user, array $data, string $key): array
    {
        $this->access->authorize($user, 'stock.transfer');
        Validator::make($data, ['location_id' => 'required|integer|min:1', 'direction' => 'required|in:to_b2b,to_main', 'notes' => 'nullable|string|max:2000', 'items' => 'required|array|min:1|max:100', 'items.*.variation_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required'])->validate();
        $main = (int) $data['location_id']; $this->access->location($user, $main);
        foreach ($data['items'] as $item) { $this->catalog->variation($user, (int) $item['variation_id'], false, $data['direction'] === 'to_main'); }
        return $this->postings->run($user, 'b2b.transfer', $key, $data, function ($postingId) use ($user, $data, $main) {
            $bucket = $this->catalog->bucket($user, $main, $data['direction'] === 'to_b2b');
            abort_unless($bucket, 422, 'There is no B2B stock at this warehouse.');
            $source = $data['direction'] === 'to_b2b' ? $main : $bucket;
            $destination = $source === $main ? $bucket : $main;
            $reference = 'B2B-T-'.$postingId;
            $outId = $this->transaction($user, $source, 'sell_transfer', 'final', $reference, 'b2b');
            // A transfer back is a normal costed main receipt, available to existing stock utilities.
            $inId = $this->transaction($user, $destination, 'purchase_transfer', 'received', $reference, $destination === $main ? 'retail' : 'b2b');
            $id = DB::table('b2b_transfers')->insertGetId(['business_id' => $user->business_id, 'main_location_id' => $main,
                'source_location_id' => $source, 'destination_location_id' => $destination, 'out_transaction_id' => $outId, 'in_transaction_id' => $inId,
                'posting_id' => $postingId, 'direction' => $data['direction'], 'reference' => $reference, 'notes' => $data['notes'] ?? null, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            $items = $data['items']; usort($items, fn ($a, $b) => $a['variation_id'] <=> $b['variation_id']);
            $total = '0.0000';
            foreach ($items as $item) {
                $v = $this->catalog->variation($user, (int) $item['variation_id'], false, $data['direction'] === 'to_main');
                $qty = $this->catalog->quantity($item['quantity'], $v);
                if ($data['direction'] === 'to_b2b' && ! $v->b2b_active) {
                    $this->access->authorize($user, 'products.manage');
                    DB::table('b2b_product_settings')->updateOrInsert(['business_id' => $user->business_id, 'product_id' => $v->product_id], ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
                }
                // Both balances are locked by the common variation lock, in sorted variation order.
                $this->change($user, $v, $source, D::sub('0', $qty));
                $allocations = $this->layers->allocate($user->business_id, $source, $v->product_id, $v->id, $qty, 'quantity_sold');
                $sellId = $this->sellLine($outId, $v, $qty, '0'); $cost = '0.0000';
                foreach ($allocations as $a) {
                    $layer = DB::table('purchase_lines')->where('id', $a['purchase_line_id'])->first();
                    $newLayer = $this->receiptLine($inId, $v, $a['quantity'], $a['unit_cost'], $layer);
                    DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $sellId, 'purchase_line_id' => $a['purchase_line_id'], 'quantity' => $a['quantity'], 'qty_returned' => '0', 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('b2b_transfer_layers')->insert(['transfer_id' => $id, 'source_purchase_line_id' => $a['purchase_line_id'], 'destination_purchase_line_id' => $newLayer, 'sell_line_id' => $sellId, 'quantity' => $a['quantity'], 'unit_cost' => $a['unit_cost']]);
                    $cost = D::add($cost, $a['total_cost']);
                }
                $this->change($user, $v, $destination, $qty);
                DB::table('product_locations')->updateOrInsert(['product_id' => $v->product_id, 'location_id' => $destination], []);
                $unit = D::div($cost, $qty);
                $this->movement($user, $v, $source, $postingId, 'b2b_transfer_out', D::sub('0', $qty), $unit, D::sub('0', $cost), 'b2b_transfer', $id);
                $this->movement($user, $v, $destination, $postingId, 'b2b_transfer_in', $qty, $unit, $cost, 'b2b_transfer', $id);
                $total = D::add($total, $cost);
            }
            DB::table('transactions')->whereIn('id', [$outId, $inId])->update(['total_before_tax' => $total, 'final_total' => $total, 'updated_at' => now()]);
            return ['transfer_id' => $id, 'reference' => $reference, 'direction' => $data['direction'], 'cost_total' => $total];
        });
    }

    public function change(User $user, object $v, int $locationId, string $delta): void
    {
        $this->stock->change($user->business_id, $v->product_id, $v->id, $locationId, $delta, false, InventoryChannel::internal($locationId) ? 'b2b' : 'main');
    }

    public function transaction(User $user, int $locationId, string $type, string $status, string $reference, string $channel = 'b2b', array $extra = []): int
    {
        return DB::table('transactions')->insertGetId(array_merge(['business_id' => $user->business_id, 'location_id' => $locationId, 'type' => $type, 'status' => $status, 'sales_channel' => $channel,
            'payment_status' => 'paid', 'ref_no' => $reference, 'transaction_date' => now(), 'created_by' => $user->id, 'total_before_tax' => '0', 'final_total' => '0', 'created_at' => now(), 'updated_at' => now()], $extra));
    }

    public function sellLine(int $transactionId, object $v, string $qty, string $price): int
    {
        return DB::table('transaction_sell_lines')->insertGetId(['transaction_id' => $transactionId, 'product_id' => $v->product_id, 'variation_id' => $v->id, 'quantity' => $qty, 'quantity_returned' => '0', 'unit_price' => $price, 'unit_price_inc_tax' => $price, 'item_tax' => '0', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function receiptLine(int $transactionId, object $v, string $qty, string $cost, ?object $source = null): int
    {
        return DB::table('purchase_lines')->insertGetId(['transaction_id' => $transactionId, 'product_id' => $v->product_id, 'variation_id' => $v->id, 'quantity' => $qty, 'purchase_price' => $cost, 'purchase_price_inc_tax' => $cost, 'pp_without_discount' => $cost,
            'item_tax' => '0', 'lot_number' => $source->lot_number ?? null, 'mfg_date' => $source->mfg_date ?? null, 'exp_date' => $source->exp_date ?? null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function movement(User $user, object $v, int $locationId, int $postingId, string $type, string $quantity, string $unitCost, string $cost, string $referenceType, int $referenceId): void
    {
        DB::table('inventory_movements')->insert(['business_id' => $user->business_id, 'location_id' => $locationId, 'product_id' => $v->product_id, 'variation_id' => $v->id, 'posting_id' => $postingId,
            'movement_type' => $type, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $cost, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'created_by' => $user->id, 'created_at' => now()]);
    }
}
