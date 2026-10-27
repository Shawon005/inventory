<?php

namespace Modules\Manufacturing\Services;

use App\Support\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Audit manufacturing purchases through the existing purchase-line workflow. */
class PurchaseInventoryAudit
{
    public function capture($transaction, array $inputs, ?string $beforeStatus = null): array
    {
        if (! Schema::hasTable('inventory_movements') || ! Schema::hasTable('mfg_product_settings')) { return []; }
        if (in_array($transaction->type, ['purchase', 'purchase_order'], true) && $transaction->status === 'received' && $beforeStatus !== 'received' &&
            DB::table('mfg_product_settings')->where('business_id', $transaction->business_id)->whereIn('product_id', array_column($inputs, 'product_id'))->where('allow_purchase', false)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['purchases' => 'This product is configured for internal manufacturing only and cannot be externally received.']);
        }
        $ids = array_column($inputs, 'variation_id');
        $ids = array_merge($ids, DB::table('purchase_lines')->where('transaction_id', $transaction->id)->pluck('variation_id')->all());
        $variations = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $transaction->business_id)->where('p.manufacturing_type', '!=', 'normal')->whereIn('v.id', $ids)
            ->orderBy('v.id')->get(['v.id', 'v.product_id']);
        $snapshot = [];
        foreach ($variations as $v) {
            DB::table('variations')->where('id', $v->id)->lockForUpdate()->first();
            $snapshot[$v->id] = ['product_id' => $v->product_id, 'quantity' => $this->balance($v->id, $transaction->location_id), 'purchase_quantity' => $this->quantity($transaction->id, $v->id), 'value' => $this->value($transaction->id, $v->id)];
        }
        return $snapshot;
    }

    public function record($transaction, array $before, ?string $beforeStatus): void
    {
        if (! $before) { return; }
        $actor = auth()->check() && (int) auth()->user()->business_id === (int) $transaction->business_id ? (int) auth()->id() : (int) $transaction->created_by;
        $postingId = null;
        foreach ($before as $variationId => $row) {
            $delta = D::sub($this->balance($variationId, $transaction->location_id), $row['quantity']);
            $oldValue = $beforeStatus === 'received' ? $row['value'] : '0.0000';
            $newValue = $transaction->status === 'received' ? $this->value($transaction->id, $variationId) : '0.0000';
            $cost = D::sub($newValue, $oldValue);
            if (D::cmp($delta, '0') === 0 && D::cmp($cost, '0') === 0) { continue; }
            if (! $postingId) {
                $payload = ['transaction_id' => $transaction->id, 'before' => $before, 'status' => $transaction->status, 'before_status' => $beforeStatus];
                $json = json_encode($payload, JSON_THROW_ON_ERROR);
                $postingId = DB::table('inventory_postings')->insertGetId([
                    'business_id' => $transaction->business_id, 'operation' => 'purchase.stock_change', 'idempotency_key' => (string) Str::uuid(),
                    'payload_hash' => hash('sha256', $json), 'request_payload' => $json, 'result' => json_encode(['transaction_id' => $transaction->id]),
                    'created_by' => $actor, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $unitCost = '0.0000';
            if (D::cmp($delta, '0') > 0) {
                $newQuantity = $this->quantity($transaction->id, $variationId);
                $unitCost = D::cmp($newQuantity, '0') > 0 ? D::div($newValue, $newQuantity) : '0.0000';
            } elseif (D::cmp($delta, '0') < 0 && D::cmp($row['purchase_quantity'], '0') > 0) {
                $unitCost = D::div($oldValue, $row['purchase_quantity']);
            }
            $quantityCost = D::mul($unitCost, $delta);
            $movement = [
                'business_id' => $transaction->business_id, 'location_id' => $transaction->location_id,
                'product_id' => $row['product_id'], 'variation_id' => $variationId, 'posting_id' => $postingId,
                'movement_type' => 'purchase_stock_change', 'reference_type' => 'transaction', 'reference_id' => $transaction->id,
                'transaction_id' => $transaction->id, 'quantity' => $delta, 'unit_cost' => $unitCost, 'total_cost' => $quantityCost,
                'created_by' => $actor, 'created_at' => now(),
            ];
            if (D::cmp($delta, '0') !== 0) { DB::table('inventory_movements')->insert($movement); }
            $revaluation = D::sub($cost, $quantityCost);
            if (D::cmp($revaluation, '0') !== 0) {
                $movement['movement_type'] = 'purchase_value_change'; $movement['quantity'] = '0.0000'; $movement['unit_cost'] = '0.0000'; $movement['total_cost'] = $revaluation;
                DB::table('inventory_movements')->insert($movement);
            }
        }
    }

    private function balance(int $variationId, int $locationId): string
    {
        return D::value((string) (DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $locationId)->sum('qty_available') ?: '0'));
    }

    private function value(int $transactionId, int $variationId): string
    {
        $total = '0.0000';
        foreach (DB::table('purchase_lines')->where('transaction_id', $transactionId)->where('variation_id', $variationId)->get(['quantity', 'purchase_price_inc_tax']) as $line) {
            $total = D::add($total, D::mul((string) $line->quantity, (string) $line->purchase_price_inc_tax));
        }
        return $total;
    }

    private function quantity(int $transactionId, int $variationId): string
    {
        return D::value((string) (DB::table('purchase_lines')->where('transaction_id', $transactionId)->where('variation_id', $variationId)->sum('quantity') ?: '0'));
    }
}
