<?php

namespace Modules\B2B\Services;

use App\Events\TransactionPaymentAdded;
use App\Services\PurchaseLayerService;
use App\Support\Decimal as D;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Services\PostingService;

class SalesService
{
    public function __construct(private Access $access, private Catalog $catalog, private InventoryService $inventory, private PostingService $postings, private PurchaseLayerService $layers) {}

    public function checkout(User $user, array $data, string $key): array
    {
        $this->access->authorize($user, 'pos.access'); $this->access->authorize($user, 'sales.create');
        Validator::make($data, ['location_id' => 'required|integer|min:1', 'contact_id' => 'required|integer|min:1', 'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100', 'items.*.variation_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required', 'items.*.unit_price' => 'required', 'payments' => 'sometimes|array|max:10'])->validate();
        $this->access->location($user, (int) $data['location_id']);
        $this->catalog->customer($user, (int) $data['contact_id']);
        return $this->postings->run($user, 'b2b.sale', $key, $data, function ($postingId) use ($user, $data) {
            $customer = $this->catalog->customer($user, (int) $data['contact_id']);
            $main = (int) $data['location_id']; $bucket = $this->catalog->bucket($user, $main);
            $this->require((bool) $bucket, 'There is no B2B stock at the selected warehouse.');
            $items = $data['items']; usort($items, fn ($a, $b) => $a['variation_id'] <=> $b['variation_id']);
            $total = '0.0000'; $cost = '0.0000'; $prepared = [];
            foreach ($items as $item) {
                $v = $this->catalog->variation($user, (int) $item['variation_id'], true);
                $qty = $this->catalog->quantity($item['quantity'], $v); $price = D::positive(D::value($item['unit_price']), 'unit_price');
                $total = D::add($total, D::mul($qty, $price)); $prepared[] = [$v, $qty, $price];
            }
            $paid = $this->paymentsTotal($user, $main, $data['payments'] ?? []);
            $this->require(D::cmp($paid, $total) <= 0, 'Payment exceeds the sale total.');
            $due = D::sub($total, $paid);
            if (D::cmp($due, '0') > 0) {
                $this->access->authorize($user, 'credit.manage');
                $this->require(D::cmp(D::add($this->outstanding($user->business_id, $customer->id), $due), (string) $customer->b2b_credit_limit) <= 0, 'This sale would exceed the customer B2B credit limit.');
            }
            $reference = 'B2B-INV-'.$postingId;
            $id = $this->inventory->transaction($user, $bucket, 'sell', 'final', $reference, 'b2b', ['invoice_no' => $reference, 'contact_id' => $customer->id, 'total_before_tax' => $total, 'final_total' => $total, 'payment_status' => 'due', 'additional_notes' => $data['notes'] ?? null]);
            foreach ($prepared as [$v, $qty, $price]) {
                $this->inventory->change($user, $v, $bucket, D::sub('0', $qty));
                $allocations = $this->layers->allocate($user->business_id, $bucket, $v->product_id, $v->id, $qty, 'quantity_sold');
                $line = $this->inventory->sellLine($id, $v, $qty, $price); $lineCost = '0.0000';
                foreach ($allocations as $a) {
                    DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line, 'purchase_line_id' => $a['purchase_line_id'], 'quantity' => $a['quantity'], 'qty_returned' => '0', 'created_at' => now(), 'updated_at' => now()]);
                    $lineCost = D::add($lineCost, $a['total_cost']);
                }
                DB::table('b2b_sale_line_details')->insert(['sell_line_id' => $line, 'product_name' => $v->name.($v->variation_name === 'DUMMY' ? '' : ' / '.$v->variation_name), 'sku' => $v->sub_sku ?: $v->sku, 'unit_name' => $v->unit_name, 'price_source' => 'manual', 'cost_total' => $lineCost]);
                $this->inventory->movement($user, $v, $bucket, $postingId, 'b2b_sale', D::sub('0', $qty), D::div($lineCost, $qty), D::sub('0', $lineCost), 'b2b_sale', $id);
                $cost = D::add($cost, $lineCost);
            }
            DB::table('b2b_sale_details')->insert(['business_id' => $user->business_id, 'transaction_id' => $id, 'main_location_id' => $main, 'posting_id' => $postingId,
                'due_date' => now()->addDays((int) $customer->payment_term_days)->toDateString(), 'cost_total' => $cost,
                'customer_snapshot' => json_encode(['name' => $customer->supplier_business_name ?: $customer->name, 'contact_person' => $customer->name, 'mobile' => $customer->mobile, 'email' => $customer->email, 'address' => $customer->address_line_1, 'tax_number' => $customer->tax_number], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['payments'] ?? [] as $i => $payment) { $this->writePayment($user, $id, $customer->id, $payment, 'B2B-P-'.$postingId.'-'.$i); }
            $status = $this->refresh($id);
            return ['transaction_id' => $id, 'invoice_no' => $reference, 'total' => $total, 'paid' => $paid, 'due' => $due, 'payment_status' => $status];
        });
    }

    public function recordPayment(User $user, int $id, array $data, string $key): array
    {
        $this->access->authorize($user, 'payments.record'); $this->sale($user, $id);
        Validator::make($data, ['payments' => 'required|array|min:1|max:10'])->validate();
        return $this->postings->run($user, 'b2b.payment', $key, ['sale_id' => $id, 'data' => $data], function ($postingId) use ($user, $id, $data) {
            $sale = $this->sale($user, $id); $amount = $this->paymentsTotal($user, $sale->main_location_id, $data['payments']);
            $this->require(D::cmp($amount, $this->due($sale)) <= 0, 'Payment exceeds the remaining invoice balance.');
            foreach ($data['payments'] as $i => $payment) { $this->writePayment($user, $id, $sale->contact_id, $payment, 'B2B-P-'.$postingId.'-'.$i); }
            $status = $this->refresh($id);
            return ['transaction_id' => $id, 'amount' => $amount, 'due' => $this->due($this->sale($user, $id)), 'payment_status' => $status];
        });
    }

    public function returnItems(User $user, int $id, array $data, string $key): array
    {
        $this->access->authorize($user, 'returns.process'); $this->sale($user, $id);
        Validator::make($data, ['items' => 'required|array|min:1|max:100', 'items.*.sell_line_id' => 'required|integer|min:1|distinct', 'items.*.quantity' => 'required', 'reason' => 'required|string|max:2000', 'refund_payments' => 'sometimes|array|max:10'])->validate();
        return $this->postings->run($user, 'b2b.return', $key, ['sale_id' => $id, 'data' => $data], function ($postingId) use ($user, $id, $data) {
            $sale = $this->sale($user, $id); $total = '0.0000'; $cost = '0.0000';
            $returnTx = $this->inventory->transaction($user, $sale->location_id, 'sell_return', 'final', 'B2B-RET-'.$postingId, 'b2b', ['contact_id' => $sale->contact_id, 'return_parent_id' => $id, 'invoice_no' => 'B2B-RET-'.$postingId, 'additional_notes' => $data['reason']]);
            $returnId = DB::table('b2b_returns')->insertGetId(['business_id' => $user->business_id, 'sale_transaction_id' => $id, 'return_transaction_id' => $returnTx, 'posting_id' => $postingId, 'total' => '0', 'cost_total' => '0', 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) {
                $line = DB::table('transaction_sell_lines as l')->join('b2b_sale_line_details as b', 'b.sell_line_id', '=', 'l.id')->where('l.transaction_id', $id)->where('l.id', $item['sell_line_id'])->first(['l.*', 'b.returned_total', 'b.returned_cost']);
                abort_unless($line, 404);
                $v = $this->catalog->variation($user, $line->variation_id, false, true); $qty = $this->catalog->quantity($item['quantity'], $v);
                $this->require(D::cmp($qty, D::sub((string) $line->quantity, (string) $line->quantity_returned)) <= 0, 'Return exceeds the remaining sold quantity.');
                $newQty = D::add((string) $line->quantity_returned, $qty); $newValue = D::mul($newQty, (string) $line->unit_price_inc_tax); $value = D::sub($newValue, (string) $line->returned_total);
                // Restore exactly the layers used by this sale, not newly priced stock.
                $remaining = $qty; $lineCost = '0.0000';
                $maps = DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->orderBy('id')->lockForUpdate()->get();
                foreach ($maps as $map) {
                    $available = D::sub((string) $map->quantity, (string) $map->qty_returned); $take = D::cmp($remaining, $available) <= 0 ? $remaining : $available;
                    if (D::cmp($take, '0') <= 0) { continue; }
                    $layer = DB::table('purchase_lines')->where('id', $map->purchase_line_id)->lockForUpdate()->first();
                    $this->require($layer && D::cmp((string) $layer->quantity_sold, $take) >= 0, 'The original cost layer cannot be restored safely.');
                    $newReturned = D::add((string) $map->qty_returned, $take);
                    $valueCost = D::sub(D::mul($newReturned, (string) $layer->purchase_price_inc_tax), D::mul((string) $map->qty_returned, (string) $layer->purchase_price_inc_tax));
                    DB::table('purchase_lines')->where('id', $layer->id)->update(['quantity_sold' => D::sub((string) $layer->quantity_sold, $take)]);
                    DB::table('transaction_sell_lines_purchase_lines')->where('id', $map->id)->update(['qty_returned' => $newReturned, 'updated_at' => now()]);
                    $lineCost = D::add($lineCost, $valueCost); $remaining = D::sub($remaining, $take);
                    if (D::cmp($remaining, '0') === 0) { break; }
                }
                $this->require(D::cmp($remaining, '0') === 0, 'Return exceeds original cost allocations.');
                $this->inventory->change($user, $v, $sale->location_id, $qty);
                DB::table('transaction_sell_lines')->where('id', $line->id)->update(['quantity_returned' => $newQty, 'updated_at' => now()]);
                DB::table('b2b_sale_line_details')->where('sell_line_id', $line->id)->update(['returned_total' => $newValue, 'returned_cost' => D::add((string) $line->returned_cost, $lineCost)]);
                $this->inventory->sellLine($returnTx, $v, $qty, (string) $line->unit_price_inc_tax);
                $this->inventory->movement($user, $v, $sale->location_id, $postingId, 'b2b_sale_return', $qty, D::div($lineCost, $qty), $lineCost, 'b2b_return', $returnId);
                $total = D::add($total, $value); $cost = D::add($cost, $lineCost);
            }
            DB::table('b2b_sale_details')->where('transaction_id', $id)->update(['returned_total' => D::add((string) $sale->returned_total, $total), 'returned_cost' => D::add((string) $sale->returned_cost, $cost), 'updated_at' => now()]);
            // Credit the unpaid part first; refund only collections exceeding the new net invoice.
            $newNet = D::sub(D::sub((string) $sale->final_total, (string) $sale->returned_total), $total);
            $excess = D::sub($this->paid($id), $newNet); if (D::cmp($excess, '0') < 0) { $excess = '0.0000'; }
            $refund = $this->paymentsTotal($user, $sale->main_location_id, $data['refund_payments'] ?? []);
            $this->require(D::cmp($refund, $excess) === 0, 'Refund payments must equal '.$excess.' after crediting the unpaid balance.');
            if (D::cmp($refund, '0') > 0) { $this->access->authorize($user, 'payments.record'); }
            foreach ($data['refund_payments'] ?? [] as $i => $payment) { $this->writePayment($user, $id, $sale->contact_id, $payment, 'B2B-RF-'.$postingId.'-'.$i, true); }
            DB::table('b2b_returns')->where('id', $returnId)->update(['total' => $total, 'cost_total' => $cost]);
            DB::table('transactions')->where('id', $returnTx)->update(['total_before_tax' => $total, 'final_total' => $total]);
            $this->refresh($id);
            return ['return_id' => $returnId, 'transaction_id' => $id, 'returned_total' => $total, 'refund' => $refund, 'due' => $this->due($this->sale($user, $id))];
        });
    }

    public function sale(User $user, int $id): object
    {
        $sale = DB::table('transactions as t')->join('b2b_sale_details as b', 'b.transaction_id', '=', 't.id')->where('t.id', $id)->where('t.business_id', $user->business_id)->where('t.sales_channel', 'b2b')->where('t.type', 'sell')->first(['t.*', 'b.main_location_id', 'b.due_date', 'b.cost_total', 'b.returned_total', 'b.returned_cost', 'b.customer_snapshot']);
        abort_unless($sale, 404); $this->access->location($user, $sale->main_location_id, false); $this->catalog->customer($user, $sale->contact_id, false);
        return $sale;
    }

    public function paid(int $id): string
    {
        $paid = '0.0000';
        foreach (DB::table('transaction_payments')->where('transaction_id', $id)->get(['amount', 'is_return']) as $p) { $paid = $p->is_return ? D::sub($paid, (string) $p->amount) : D::add($paid, (string) $p->amount); }
        return $paid;
    }

    public function due(object $sale): string { return D::sub(D::sub((string) $sale->final_total, (string) $sale->returned_total), $this->paid($sale->id)); }

    public function outstanding(int $businessId, int $contactId): string
    {
        $due = '0.0000';
        foreach (DB::table('transactions as t')->join('b2b_sale_details as b', 'b.transaction_id', '=', 't.id')->where('t.business_id', $businessId)->where('t.contact_id', $contactId)->get(['t.id', 't.final_total', 'b.returned_total']) as $sale) { $due = D::add($due, $this->due($sale)); }
        return $due;
    }

    public function paymentsTotal(User $user, int $mainId, array $payments): string
    {
        Validator::make(['payments' => $payments], ['payments.*.amount' => 'required', 'payments.*.method' => 'required|in:cash,card,cheque,bank_transfer,other,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7', 'payments.*.account_id' => 'nullable|integer|min:1', 'payments.*.note' => 'nullable|string|max:191'])->validate();
        $location = $this->access->location($user, $mainId, false); $settings = json_decode($location->default_payment_accounts ?? '{}', true) ?: []; $total = '0.0000';
        foreach ($payments as $p) {
            $total = D::add($total, D::positive(D::value($p['amount']), 'amount'));
            if ($settings) { $this->require(! empty($settings[$p['method']]['is_enabled']), 'This payment method is disabled for this warehouse.'); }
            if (! empty($p['account_id'])) { $this->require(DB::table('accounts')->where('id', $p['account_id'])->where('business_id', $user->business_id)->where('is_closed', 0)->whereNull('deleted_at')->exists(), 'Choose an active payment account in this business.'); }
        }
        return $total;
    }

    private function writePayment(User $user, int $id, int $contactId, array $data, string $reference, bool $refund = false): void
    {
        $amount = D::positive(D::value($data['amount']));
        $fields = ['transaction_id' => $id, 'business_id' => $user->business_id, 'amount' => $amount, 'method' => $data['method'], 'account_id' => $data['account_id'] ?? null, 'is_return' => $refund ? 1 : 0,
            'payment_for' => $contactId, 'payment_ref_no' => $reference, 'paid_on' => now(), 'note' => $data['note'] ?? null, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()];
        $paymentId = DB::table('transaction_payments')->insertGetId($fields);
        $payment = new TransactionPayment; $payment->setRawAttributes(array_merge($fields, ['id' => $paymentId])); $payment->exists = true;
        event(new TransactionPaymentAdded($payment, ['transaction_type' => 'sell', 'is_return' => $refund ? 1 : 0, 'account_id' => $fields['account_id'], 'amount' => $amount]));
    }

    private function refresh(int $id): string
    {
        $sale = DB::table('transactions as t')->join('b2b_sale_details as b', 'b.transaction_id', '=', 't.id')->where('t.id', $id)->first(['t.id', 't.final_total', 'b.returned_total']);
        $paid = $this->paid($id); $status = D::cmp($this->due($sale), '0') === 0 ? 'paid' : (D::cmp($paid, '0') > 0 ? 'partial' : 'due');
        DB::table('transactions')->where('id', $id)->update(['payment_status' => $status, 'updated_at' => now()]); return $status;
    }

    private function require(bool $condition, string $message): void { if (! $condition) { throw ValidationException::withMessages(['b2b' => $message]); } }
}
