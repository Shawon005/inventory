<?php

namespace Modules\Manufacturing\Services;

use App\Services\InventoryStockService;
use App\Support\Decimal as D;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(
        private ManufacturingAccess $access,
        private ManufacturingCatalog $catalog,
        private BomService $boms,
        private PostingService $postings,
        private InventoryStockService $stock
    ) {}

    public function create(User $user, array $data, string $key): array
    {
        $businessId = $this->access->authorize($user, 'orders.create');
        Validator::make($data, [
            'bom_version_id' => 'required|integer|min:1', 'location_id' => 'required|integer|min:1',
            'planned_quantity' => 'required', 'status' => 'sometimes|in:draft,planned',
            'planned_start_date' => 'nullable|date_format:Y-m-d',
            'planned_completion_date' => 'nullable|date_format:Y-m-d|after_or_equal:planned_start_date',
            'assigned_to' => 'nullable|integer|min:1', 'notes' => 'nullable|string|max:10000',
            'parent_order_id' => 'nullable|integer|min:1',
        ])->validate();
        $this->access->location($user, $data['location_id']);
        return $this->postings->run($user, 'production.create', $key, $data, function () use ($user, $data, $businessId) {
            $snapshot = $this->boms->snapshot($businessId, $data['bom_version_id']);
            $output = $this->catalog->variation($businessId, $snapshot['variation_id'], true);
            $quantity = $this->validQuantity($data['planned_quantity'], $output);
            if (! empty($data['assigned_to'])) {
                abort_unless(DB::table('users')->where('id', $data['assigned_to'])->where('business_id', $businessId)->exists(), 422);
            }
            if (! empty($data['parent_order_id'])) {
                $parent = $this->order($user, $data['parent_order_id']);
                $inputs = array_column(json_decode($parent->bom_snapshot, true)['items'], 'variation_id');
                $this->state(in_array($output->id, $inputs) && ! in_array($parent->status, ['completed', 'cancelled']), 'A child order must produce an input required by an open parent order.');
            }
            $id = DB::table('mfg_production_orders')->insertGetId([
                'business_id' => $businessId, 'location_id' => $data['location_id'], 'variation_id' => $output->id,
                'bom_version_id' => $snapshot['id'], 'order_number' => 'MO-'.Str::uuid(),
                'status' => $data['status'] ?? 'draft', 'planned_quantity' => $quantity,
                'expected_cost' => $this->boms->requirements($snapshot, $quantity)['expected_cost'],
                'bom_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'planned_start_date' => $data['planned_start_date'] ?? null, 'planned_completion_date' => $data['planned_completion_date'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null, 'notes' => $data['notes'] ?? null,
                'parent_order_id' => $data['parent_order_id'] ?? null,
                'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('mfg_production_orders')->where('id', $id)->update(['order_number' => 'MO-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
            return ['order_id' => $id];
        });
    }

    public function plan(User $user, int $id, string $key): array
    {
        $this->access->authorize($user, 'orders.create');
        $this->order($user, $id);
        return $this->postings->run($user, 'production.plan', $key, ['order_id' => $id], function () use ($user, $id) {
            $order = $this->order($user, $id);
            $this->state($order->status === 'draft', 'Only draft orders can be planned.');
            DB::table('mfg_production_orders')->where('id', $id)->update(['status' => 'planned', 'updated_at' => now()]);
            return ['order_id' => $id, 'status' => 'planned'];
        });
    }

    public function update(User $user, int $id, array $data, string $key): array
    {
        $this->access->authorize($user, 'orders.create'); $this->order($user, $id);
        Validator::make($data, ['planned_quantity' => 'sometimes|required', 'notes' => 'nullable|string|max:10000', 'planned_start_date' => 'nullable|date_format:Y-m-d', 'planned_completion_date' => 'nullable|date_format:Y-m-d', 'assigned_to' => 'nullable|integer|min:1', 'status' => 'prohibited', 'bom_version_id' => 'prohibited', 'location_id' => 'prohibited', 'variation_id' => 'prohibited'])->validate();
        return $this->postings->run($user, 'production.update', $key, ['order_id' => $id, 'data' => $data], function () use ($user, $id, $data) {
            $order = $this->order($user, $id);
            $this->state(in_array($order->status, ['draft', 'planned']) && ! DB::table('mfg_production_batches')->where('order_id', $id)->exists(), 'Only unstarted draft or planned orders can be edited.');
            $output = $this->catalog->variation($user->business_id, $order->variation_id, true);
            $quantity = isset($data['planned_quantity']) ? $this->validQuantity($data['planned_quantity'], $output) : (string) $order->planned_quantity;
            $fields = ['planned_quantity' => $quantity, 'expected_cost' => $this->boms->requirements(json_decode($order->bom_snapshot, true), $quantity)['expected_cost'], 'updated_at' => now()];
            foreach (['notes', 'planned_start_date', 'planned_completion_date', 'assigned_to'] as $field) { if (array_key_exists($field, $data)) { $fields[$field] = $data[$field]; } }
            if (! empty($fields['assigned_to'])) { abort_unless(DB::table('users')->where('id', $fields['assigned_to'])->where('business_id', $user->business_id)->exists(), 422); }
            $start = array_key_exists('planned_start_date', $fields) ? $fields['planned_start_date'] : $order->planned_start_date;
            $end = array_key_exists('planned_completion_date', $fields) ? $fields['planned_completion_date'] : $order->planned_completion_date;
            $this->state(! $start || ! $end || $end >= $start, 'The completion date must be on or after the start date.');
            DB::table('mfg_production_orders')->where('id', $id)->update($fields);
            return ['order_id' => $id, 'planned_quantity' => $quantity];
        });
    }

    public function start(User $user, int $id, array $data, string $key): array
    {
        $this->access->authorize($user, 'orders.start');
        $this->order($user, $id);
        Validator::make($data, ['quantity' => 'required', 'materials' => 'sometimes|array|min:1|max:200', 'materials.*.variation_id' => 'required|integer|distinct', 'materials.*.quantity' => 'required'])->validate();
        return $this->postings->run($user, 'production.start', $key, ['order_id' => $id, 'data' => $data], function ($postingId) use ($user, $id, $data) {
            $order = $this->order($user, $id);
            $this->state(in_array($order->status, ['planned', 'partially_completed', 'in_progress']), 'Plan the order before starting production.');
            $this->access->location($user, $order->location_id);
            $this->state(! DB::table('mfg_production_batches')->where('order_id', $id)->where('status', 'in_progress')->exists(), 'Complete or reverse the open batch first.');
            $output = $this->catalog->variation($user->business_id, $order->variation_id, true);
            $quantity = $this->validQuantity($data['quantity'], $output);
            $this->state(D::cmp($quantity, D::sub($order->planned_quantity, $order->accepted_quantity)) <= 0, 'Batch output exceeds the remaining order quantity.');
            $snapshot = json_decode($order->bom_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $requirements = $this->boms->requirements($snapshot, $quantity)['materials'];
            $actual = collect($data['materials'] ?? $requirements)->keyBy('variation_id');
            $expectedIds = array_column($requirements, 'variation_id');
            $this->state(count($actual) === count($expectedIds) && ! array_diff($actual->keys()->all(), $expectedIds), 'Actual issues must list exactly the BOM input variations.');
            // Deterministic locks serialize stock and purchase-layer access against retail.
            $ids = $expectedIds; $ids[] = $output->id; sort($ids, SORT_NUMERIC);
            DB::table('variations')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $transactionId = $this->transaction($user, $order, 'production_sell', 'final');
            $batchId = DB::table('mfg_production_batches')->insertGetId([
                'order_id' => $id, 'issue_transaction_id' => $transactionId, 'planned_quantity' => $quantity,
                'material_cost' => '0', 'total_cost' => '0', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $materialCost = '0.0000';
            foreach ($requirements as $required) {
                $input = $actual->get($required['variation_id']);
                $variation = $this->catalog->variation($user->business_id, $required['variation_id']);
                $issued = $this->validQuantity($input['quantity'], $variation);
                $waste = D::positive(D::value($input['wastage_quantity'] ?? '0'), 'wastage_quantity', true);
                $this->state(D::cmp($waste, $issued) <= 0, 'Material wastage cannot exceed the issued quantity.');
                $this->stock->change($user->business_id, $variation->product_id, $variation->id, $order->location_id, D::sub('0', $issued));
                $sellLineId = DB::table('transaction_sell_lines')->insertGetId([
                    'transaction_id' => $transactionId, 'product_id' => $variation->product_id,
                    'variation_id' => $variation->id, 'quantity' => $issued, 'unit_price' => '0', 'unit_price_inc_tax' => '0', 'item_tax' => '0',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $cost = $this->consumeLayers($user, $order, $batchId, $variation, $sellLineId, $issued, $waste);
                $materialCost = D::add($materialCost, $cost);
                $this->movement($user, $order, $postingId, $batchId, $variation, 'material_issue', D::sub('0', $issued), D::div($cost, $issued), D::sub('0', $cost), $transactionId);
            }
            DB::table('mfg_production_batches')->where('id', $batchId)->update(['material_cost' => $materialCost, 'total_cost' => $materialCost]);
            DB::table('transactions')->where('id', $transactionId)->update(['ref_no' => $order->order_number.'-ISSUE-'.$batchId, 'total_before_tax' => $materialCost, 'final_total' => $materialCost]);
            $this->refreshOrder($id);
            return ['order_id' => $id, 'batch_id' => $batchId, 'material_cost' => $materialCost, 'status' => 'in_progress'];
        });
    }

    public function receive(User $user, int $orderId, int $batchId, array $data, string $key, bool $scrap = false): array
    {
        $this->access->authorize($user, $scrap ? 'stock.adjust' : 'orders.complete');
        $this->order($user, $orderId);
        Validator::make($data, [
            'accepted_quantity' => $scrap ? 'prohibited' : 'required', 'defective_quantity' => 'sometimes',
            'expenses' => 'sometimes|array:labor_cost,machine_cost,overhead_cost,other_cost',
            'lot_number' => 'nullable|string|max:191', 'expiry_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'reason' => $scrap ? 'required|string|max:2000' : 'nullable|string|max:2000',
        ])->validate();
        return $this->postings->run($user, $scrap ? 'production.scrap' : 'production.receive', $key, ['order_id' => $orderId, 'batch_id' => $batchId, 'data' => $data], function ($postingId) use ($user, $orderId, $batchId, $data, $scrap) {
            $order = $this->order($user, $orderId); $batch = $this->batch($orderId, $batchId);
            $this->state($batch->status === 'in_progress' && $order->status !== 'cancelled', 'This batch is no longer open for receipt.');
            $output = $this->catalog->variation($user->business_id, $order->variation_id, true, true);
            $accepted = $scrap ? '0.0000' : $this->validQuantity($data['accepted_quantity'], $output);
            $defective = $scrap ? (string) $batch->planned_quantity : D::positive(D::value($data['defective_quantity'] ?? '0'), 'defective_quantity', true);
            $this->state(D::cmp(D::add($accepted, $defective), $batch->planned_quantity) === 0, 'Accepted plus defective quantity must equal the batch planned quantity.');
            $expenseCost = '0.0000';
            foreach (['labor_cost', 'machine_cost', 'overhead_cost', 'other_cost'] as $field) {
                $amount = D::positive(D::value($data['expenses'][$field] ?? '0'), $field, true);
                DB::table('mfg_production_expenses')->insert(['batch_id' => $batchId, 'type' => $field, 'amount' => $amount, 'created_by' => $user->id, 'created_at' => now()]);
                $expenseCost = D::add($expenseCost, $amount);
            }
            $totalCost = D::add($batch->material_cost, $expenseCost);
            $unitCost = $scrap ? '0.0000' : D::div($totalCost, $accepted);
            $receivedValue = D::mul($unitCost, $accepted);
            $rounding = $scrap ? '0.0000' : D::sub($totalCost, $receivedValue);
            $transactionId = null; $purchaseLineId = null;
            if (! $scrap) {
                $transactionId = $this->transaction($user, $order, 'production_purchase', 'received');
                $purchaseLineId = DB::table('purchase_lines')->insertGetId([
                    'transaction_id' => $transactionId, 'product_id' => $output->product_id, 'variation_id' => $output->id,
                    'quantity' => $accepted, 'purchase_price' => $unitCost, 'purchase_price_inc_tax' => $unitCost,
                    'pp_without_discount' => $unitCost, 'item_tax' => '0', 'mfg_date' => now()->toDateString(),
                    'lot_number' => $data['lot_number'] ?? null, 'exp_date' => $data['expiry_date'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->stock->change($user->business_id, $output->product_id, $output->id, $order->location_id, $accepted);
                DB::table('product_locations')->updateOrInsert(['product_id' => $output->product_id, 'location_id' => $order->location_id], []);
                $this->movement($user, $order, $postingId, $batchId, $output, 'production_receipt', $accepted, $unitCost, $receivedValue, $transactionId);
                DB::table('transactions')->where('id', $transactionId)->update(['ref_no' => $order->order_number.'-RECEIPT-'.$batchId, 'total_before_tax' => $receivedValue, 'final_total' => $receivedValue]);
            }
            if ($scrap || D::cmp($rounding, '0') !== 0) {
                $this->movement($user, $order, $postingId, $batchId, $output, $scrap ? 'production_write_off' : 'production_rounding', '0.0000', '0.0000', $scrap ? $totalCost : $rounding, null);
            }
            DB::table('mfg_production_batches')->where('id', $batchId)->update([
                'status' => $scrap ? 'scrapped' : 'completed', 'accepted_quantity' => $accepted, 'defective_quantity' => $defective,
                'expense_cost' => $expenseCost, 'total_cost' => $totalCost, 'unit_cost' => $unitCost,
                'valuation_rounding' => $rounding, 'receipt_transaction_id' => $transactionId, 'output_purchase_line_id' => $purchaseLineId,
                'lot_number' => $data['lot_number'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null,
                'reversal_reason' => $scrap ? $data['reason'] : null, 'updated_at' => now(),
            ]);
            $this->refreshOrder($orderId);
            return ['order_id' => $orderId, 'batch_id' => $batchId, 'accepted_quantity' => $accepted, 'total_cost' => $totalCost, 'unit_cost' => $unitCost, 'valuation_rounding' => $rounding];
        });
    }

    public function returnMaterials(User $user, int $orderId, int $batchId, array $data, string $key): array
    {
        $this->access->authorize($user, 'stock.adjust'); $this->order($user, $orderId);
        Validator::make($data, ['variation_id' => 'required|integer|min:1', 'quantity' => 'required', 'reason' => 'required|string|max:2000'])->validate();
        return $this->postings->run($user, 'production.material_return', $key, ['order_id' => $orderId, 'batch_id' => $batchId, 'data' => $data], function ($postingId) use ($user, $orderId, $batchId, $data) {
            $order = $this->order($user, $orderId); $batch = $this->batch($orderId, $batchId);
            $this->state($batch->status === 'in_progress', 'Materials can only be returned from an open batch.');
            $variation = $this->catalog->variation($user->business_id, $data['variation_id']);
            $quantity = $this->validQuantity($data['quantity'], $variation); $remaining = $quantity; $cost = '0.0000';
            DB::table('variations')->where('id', $variation->id)->lockForUpdate()->first();
            $lines = DB::table('mfg_material_issue_lines')->where('batch_id', $batchId)->where('variation_id', $variation->id)->orderByDesc('id')->lockForUpdate()->get();
            foreach ($lines as $line) {
                $available = D::sub(D::sub($line->quantity, $line->returned_quantity), $line->wastage_quantity);
                $take = D::cmp($remaining, $available) < 0 ? $remaining : $available;
                if (D::cmp($take, '0') <= 0) { continue; }
                $totalReturned = D::add($line->returned_quantity, $take);
                $returnedCost = D::cmp($totalReturned, $line->quantity) === 0 ? D::sub($line->total_cost, $line->returned_cost) : D::mul($take, $line->unit_cost);
                $layer = DB::table('purchase_lines')->where('id', $line->purchase_line_id)->lockForUpdate()->first();
                $this->state(D::cmp($layer->mfg_quantity_used, $take) >= 0, 'The material layer cannot be reversed safely.');
                DB::table('purchase_lines')->where('id', $layer->id)->update(['mfg_quantity_used' => D::sub($layer->mfg_quantity_used, $take)]);
                DB::table('mfg_material_issue_lines')->where('id', $line->id)->update(['returned_quantity' => $totalReturned, 'returned_cost' => D::add($line->returned_cost, $returnedCost)]);
                DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->sell_line_id)->where('purchase_line_id', $line->purchase_line_id)->update(['qty_returned' => $totalReturned, 'updated_at' => now()]);
                $sellReturned = DB::table('mfg_material_issue_lines')->where('sell_line_id', $line->sell_line_id)->sum('returned_quantity');
                DB::table('transaction_sell_lines')->where('id', $line->sell_line_id)->update(['quantity_returned' => $sellReturned]);
                $remaining = D::sub($remaining, $take); $cost = D::add($cost, $returnedCost);
                if (D::cmp($remaining, '0') === 0) { break; }
            }
            $this->state(D::cmp($remaining, '0') === 0, 'Return exceeds the unused, non-wasted material issued to this batch.');
            $this->stock->change($user->business_id, $variation->product_id, $variation->id, $order->location_id, $quantity);
            $this->movement($user, $order, $postingId, $batchId, $variation, 'material_return', $quantity, D::div($cost, $quantity), $cost, $batch->issue_transaction_id);
            $materialCost = D::sub($batch->material_cost, $cost);
            DB::table('mfg_production_batches')->where('id', $batchId)->update(['material_cost' => $materialCost, 'total_cost' => $materialCost, 'updated_at' => now()]);
            DB::table('transactions')->where('id', $batch->issue_transaction_id)->update(['total_before_tax' => $materialCost, 'final_total' => $materialCost, 'updated_at' => now()]);
            $this->refreshOrder($orderId);
            return ['order_id' => $orderId, 'batch_id' => $batchId, 'returned_quantity' => $quantity, 'returned_cost' => $cost];
        });
    }

    public function reverse(User $user, int $orderId, int $batchId, string $reason, string $key): array
    {
        $this->access->authorize($user, 'stock.adjust'); $this->order($user, $orderId);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|max:2000'])->validate();
        return $this->postings->run($user, 'production.reverse', $key, ['order_id' => $orderId, 'batch_id' => $batchId, 'reason' => $reason], function ($postingId) use ($user, $orderId, $batchId, $reason) {
            $order = $this->order($user, $orderId); $batch = $this->batch($orderId, $batchId);
            $this->state($batch->status !== 'reversed' && $order->status !== 'cancelled', 'This batch cannot be reversed again.');
            $lines = DB::table('mfg_material_issue_lines')->where('batch_id', $batchId)->orderBy('variation_id')->get();
            $ids = $lines->pluck('variation_id')->push($order->variation_id)->unique()->sort()->values()->all();
            DB::table('variations')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($batch->output_purchase_line_id) {
                $outputLayer = DB::table('purchase_lines')->where('id', $batch->output_purchase_line_id)->lockForUpdate()->first();
                foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
                    $this->state(D::cmp((string) $outputLayer->$field, '0') === 0, 'Output has downstream sales, transfers, adjustments or production use. Reverse those dependencies first.');
                }
                $output = $this->catalog->variation($user->business_id, $order->variation_id, false, true);
                $this->stock->change($user->business_id, $output->product_id, $output->id, $order->location_id, D::sub('0', $batch->accepted_quantity));
                DB::table('transactions')->where('id', $batch->receipt_transaction_id)->update(['status' => 'pending', 'updated_at' => now()]);
            }
            foreach ($lines as $line) {
                $remaining = D::sub($line->quantity, $line->returned_quantity);
                if (D::cmp($remaining, '0') === 0) { continue; }
                $layer = DB::table('purchase_lines')->where('id', $line->purchase_line_id)->lockForUpdate()->first();
                $this->state(D::cmp($layer->mfg_quantity_used, $remaining) >= 0, 'The source cost layer is inconsistent.');
                DB::table('purchase_lines')->where('id', $line->purchase_line_id)->update(['mfg_quantity_used' => D::sub($layer->mfg_quantity_used, $remaining)]);
                DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->sell_line_id)->where('purchase_line_id', $line->purchase_line_id)->update(['qty_returned' => $line->quantity, 'updated_at' => now()]);
                $variation = $this->catalog->variation($user->business_id, $line->variation_id, false, true);
                $this->stock->change($user->business_id, $variation->product_id, $variation->id, $order->location_id, $remaining);
            }
            $movements = DB::table('inventory_movements')->where('business_id', $user->business_id)->where('reference_type', 'production_batch')->where('reference_id', $batchId)->whereNull('reversal_of')->orderBy('id')->get();
            foreach ($movements as $movement) {
                $copy = (array) $movement; $originalId = $copy['id']; unset($copy['id']);
                $copy['posting_id'] = $postingId; $copy['created_by'] = $user->id; $copy['created_at'] = now();
                $copy['movement_type'] = 'production_reversal'; $copy['reversal_of'] = $originalId;
                $copy['quantity'] = D::sub('0', $movement->quantity); $copy['total_cost'] = D::sub('0', $movement->total_cost);
                DB::table('inventory_movements')->insert($copy);
            }
            DB::table('transactions')->where('id', $batch->issue_transaction_id)->update(['status' => 'draft', 'updated_at' => now()]);
            DB::table('mfg_production_batches')->where('id', $batchId)->update(['status' => 'reversed', 'reversal_reason' => $reason, 'updated_at' => now()]);
            $this->refreshOrder($orderId);
            return ['order_id' => $orderId, 'batch_id' => $batchId, 'status' => 'reversed'];
        });
    }

    public function cancel(User $user, int $orderId, string $reason, string $key): array
    {
        $this->access->authorize($user, 'orders.start'); $this->order($user, $orderId);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|max:2000'])->validate();
        return $this->postings->run($user, 'production.cancel', $key, ['order_id' => $orderId, 'reason' => $reason], function () use ($user, $orderId, $reason) {
            $order = $this->order($user, $orderId);
            $this->state($order->status !== 'cancelled', 'This order is already cancelled.');
            $this->state(! DB::table('mfg_production_batches')->where('order_id', $orderId)->where('status', '!=', 'reversed')->exists(), 'Reverse all posted batches before cancelling the order.');
            DB::table('mfg_production_orders')->where('id', $orderId)->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'updated_at' => now()]);
            return ['order_id' => $orderId, 'status' => 'cancelled'];
        });
    }

    public function order(User $user, int $id): object
    {
        $order = DB::table('mfg_production_orders')->where('business_id', $this->access->business($user))->where('id', $id)->lockForUpdate()->first();
        abort_unless($order, 404); $this->access->location($user, $order->location_id, false);
        return $order;
    }

    private function batch(int $orderId, int $batchId): object
    {
        $batch = DB::table('mfg_production_batches')->where('order_id', $orderId)->where('id', $batchId)->lockForUpdate()->first();
        abort_unless($batch, 404); return $batch;
    }

    private function consumeLayers(User $user, object $order, int $batchId, object $variation, int $sellLineId, string $quantity, string $waste): string
    {
        $allocations = app(\App\Services\PurchaseLayerService::class)->allocate((int) $user->business_id, (int) $order->location_id, (int) $variation->product_id, (int) $variation->id, $quantity, 'mfg_quantity_used');
        $cost = '0.0000';
        foreach ($allocations as $allocation) {
            $take = $allocation['quantity'];
            $lineWaste = D::cmp($waste, $take) <= 0 ? $waste : $take; $waste = D::sub($waste, $lineWaste);
            DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $sellLineId, 'purchase_line_id' => $allocation['purchase_line_id'], 'quantity' => $take, 'qty_returned' => '0', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('mfg_material_issue_lines')->insert($allocation + [
                'batch_id' => $batchId, 'variation_id' => $variation->id, 'sell_line_id' => $sellLineId, 'wastage_quantity' => $lineWaste,
            ]);
            $cost = D::add($cost, $allocation['total_cost']);
        }
        return $cost;
    }

    private function transaction(User $user, object $order, string $type, string $status): int
    {
        return DB::table('transactions')->insertGetId([
            'business_id' => $user->business_id, 'location_id' => $order->location_id, 'type' => $type, 'status' => $status,
            'payment_status' => 'paid', 'transaction_date' => now(), 'created_by' => $user->id,
            'ref_no' => $order->order_number, 'additional_notes' => 'Manufacturing order '.$order->order_number,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function movement(User $user, object $order, int $postingId, int $batchId, object $variation, string $type, string $quantity, string $unitCost, string $cost, ?int $transactionId): void
    {
        DB::table('inventory_movements')->insert([
            'business_id' => $user->business_id, 'location_id' => $order->location_id,
            'product_id' => $variation->product_id, 'variation_id' => $variation->id, 'posting_id' => $postingId,
            'movement_type' => $type, 'reference_type' => 'production_batch', 'reference_id' => $batchId,
            'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $cost,
            'transaction_id' => $transactionId, 'created_by' => $user->id, 'created_at' => now(),
        ]);
    }

    private function refreshOrder(int $orderId): void
    {
        $order = DB::table('mfg_production_orders')->where('id', $orderId)->first();
        $batches = DB::table('mfg_production_batches')->where('order_id', $orderId)->where('status', '!=', 'reversed')->get();
        $accepted = '0.0000'; $defective = '0.0000'; $cost = '0.0000'; $open = false;
        foreach ($batches as $batch) {
            $accepted = D::add($accepted, $batch->accepted_quantity); $defective = D::add($defective, $batch->defective_quantity);
            $cost = D::add($cost, $batch->total_cost); $open = $open || $batch->status === 'in_progress';
        }
        $status = $open ? 'in_progress' : (D::cmp($accepted, $order->planned_quantity) === 0 ? 'completed' : (D::cmp($accepted, '0') > 0 ? 'partially_completed' : 'planned'));
        DB::table('mfg_production_orders')->where('id', $orderId)->update(['status' => $status, 'accepted_quantity' => $accepted, 'defective_quantity' => $defective, 'actual_cost' => $cost, 'updated_at' => now()]);
    }

    private function validQuantity($value, object $variation): string
    {
        $quantity = D::positive(D::value($value));
        $this->state($variation->allow_decimal || D::cmp($quantity, bcadd($quantity, '0', 0)) === 0, 'This product unit does not allow fractional quantities.');
        return $quantity;
    }

    private function state(bool $valid, string $message): void
    {
        if (! $valid) { throw ValidationException::withMessages(['production' => $message]); }
    }
}
