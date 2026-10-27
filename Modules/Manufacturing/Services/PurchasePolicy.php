<?php

namespace Modules\Manufacturing\Services;

use App\PurchaseLine;
use App\Support\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchasePolicy
{
    public function validateLine(PurchaseLine $line): void
    {
        if (! Schema::hasTable('mfg_product_settings')) { return; }
        $transaction = DB::table('transactions')->where('id', $line->transaction_id)->first();
        if (! $transaction) { return; }
        if (in_array($transaction->type, ['purchase', 'purchase_order'], true)) {
            $allowed = DB::table('mfg_product_settings')->where('business_id', $transaction->business_id)->where('product_id', $line->product_id)->value('allow_purchase');
            $increased = ! $line->exists || D::cmp(D::legacy($line->getAttributes()['quantity']), D::legacy($line->getRawOriginal('quantity'))) > 0;
            if ($allowed !== null && ! $allowed && $increased) {
                throw ValidationException::withMessages(['purchases' => 'This product is configured for internal manufacturing only and cannot be purchased.']);
            }
        }
        // A legacy purchase edit/return cannot make a layer already used by production impossible.
        if ($line->exists) {
            $stored = DB::table('purchase_lines')->where('id', $line->id)->lockForUpdate()->first();
            $b2bReferenced = Schema::hasTable('b2b_transfer_layers') && (
                DB::table('b2b_transfer_layers')->where('source_purchase_line_id', $line->id)->orWhere('destination_purchase_line_id', $line->id)->exists());
            if ($stored && (D::cmp((string) $stored->mfg_quantity_used, '0') > 0 || $b2bReferenced)) {
                $used = '0.0000';
                foreach (['quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used'] as $field) {
                    $value = $line->isDirty($field) ? $line->getAttributes()[$field] : $stored->$field;
                    $used = D::add($used, D::legacy($value));
                }
                if (D::cmp(D::legacy($line->getAttributes()['quantity']), $used) < 0 || $line->isDirty(['product_id', 'variation_id', 'transaction_id', 'purchase_price', 'purchase_price_inc_tax'])) {
                    throw ValidationException::withMessages(['purchases' => 'This purchase layer is used by production or a B2B transfer. Preserve its used quantity, identity and cost.']);
                }
            }
        }
    }
}
