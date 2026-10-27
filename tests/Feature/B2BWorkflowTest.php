<?php

namespace Tests\Feature;

use App\Events\TransactionPaymentAdded;
use App\Support\Decimal as D;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\B2B\Services\Catalog;
use Modules\B2B\Services\InventoryService;
use Modules\B2B\Services\SalesService;
use Symfony\Component\Process\Process;
use Tests\Support\ManufacturingTestCase;

class B2BWorkflowTest extends ManufacturingTestCase
{
    private $realEvents;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('system', function (Blueprint $t) { $t->increments('id'); $t->string('key'); $t->text('value')->nullable(); });
        Schema::table('business_locations', function (Blueprint $t) { $t->text('default_payment_accounts')->nullable(); });
        Schema::table('products', function (Blueprint $t) { $t->decimal('alert_quantity', 22, 4)->nullable(); });
        Schema::table('variations', function (Blueprint $t) { $t->string('sub_sku')->nullable(); $t->decimal('sell_price_inc_tax', 22, 4)->default(999); });
        Schema::table('transactions', function (Blueprint $t) { $t->unsignedInteger('contact_id')->nullable(); $t->unsignedInteger('return_parent_id')->nullable(); });
        Schema::create('contacts', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('type'); $t->string('name'); $t->string('supplier_business_name')->nullable(); $t->string('mobile');
            $t->string('email')->nullable(); $t->text('address_line_1')->nullable(); $t->string('tax_number')->nullable(); $t->string('contact_status')->default('active'); $t->unsignedInteger('created_by'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('user_contact_access', function (Blueprint $t) { $t->unsignedInteger('contact_id'); $t->unsignedInteger('user_id'); });
        Schema::create('accounts', function (Blueprint $t) { $t->increments('id'); $t->unsignedInteger('business_id'); $t->string('name'); $t->boolean('is_closed')->default(false); $t->softDeletes(); });
        Schema::create('transaction_payments', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('transaction_id')->nullable(); $t->unsignedInteger('business_id'); $t->decimal('amount', 22, 4); $t->string('method'); $t->unsignedInteger('account_id')->nullable(); $t->boolean('is_return')->default(false); $t->unsignedInteger('payment_for'); $t->string('payment_ref_no'); $t->dateTime('paid_on'); $t->string('note')->nullable(); $t->unsignedInteger('created_by'); $t->timestamps();
        });
        Schema::create('account_transactions', function (Blueprint $t) {
            $t->increments('id'); $t->decimal('amount', 22, 4); $t->unsignedInteger('account_id'); $t->string('type'); $t->string('sub_type')->nullable(); $t->dateTime('operation_date'); $t->unsignedInteger('created_by'); $t->unsignedInteger('transaction_id')->nullable(); $t->unsignedInteger('transaction_payment_id')->nullable(); $t->text('note')->nullable(); $t->unsignedInteger('transfer_transaction_id')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        (require base_path('Modules/B2B/Database/Migrations/2026_10_26_000003_create_b2b_workspace.php'))->up();
        DB::table('contacts')->insert(['id' => 1, 'business_id' => 1, 'type' => 'customer', 'name' => 'Wholesale buyer', 'supplier_business_name' => 'Buyer Ltd', 'mobile' => '012345', 'created_by' => 1]);
        DB::table('b2b_customer_profiles')->insert(['business_id' => 1, 'contact_id' => 1, 'credit_limit' => '10000', 'payment_term_days' => 30, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->realEvents = Event::getFacadeRoot(); Event::fake([TransactionPaymentAdded::class]);
        $this->operator->shouldReceive('hasRole')->andReturn(false); $this->operator->shouldReceive('checkPermissionTo')->andReturnUsing(fn ($a) => ! in_array($a, $this->denied, true));
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/manufacturing')); view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        session()->put('user.business_id', 1); session()->put('user.id', 1);
    }

    public function test_pos_preloads_all_selectable_customers_and_excludes_disabled_deleted_and_other_business_customers(): void
    {
        foreach (range(2, 35) as $id) {
            DB::table('contacts')->insert([
                'id' => $id, 'business_id' => $id === 2 ? 2 : 1, 'type' => 'customer',
                'name' => 'Buyer '.$id, 'supplier_business_name' => 'Business '.$id,
                'mobile' => 'TEST-'.$id, 'created_by' => 1,
                'contact_status' => $id === 4 ? 'inactive' : 'active',
                'deleted_at' => $id === 5 ? now() : null,
            ]);
        }
        DB::table('b2b_customer_profiles')->insert(['business_id' => 1, 'contact_id' => 3, 'credit_limit' => '0', 'payment_term_days' => 0, 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $response = $this->get('/b2b/pos')->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $options = (new \DOMXPath($document))->query('//select[@id="b2b-sale-customer"]/option[@value!=""]');
        $ids = [];
        foreach ($options as $option) { $ids[] = (int) $option->getAttribute('value'); }
        $this->assertCount(31, $ids); // The page includes customers beyond the first API page.
        foreach ([1, 6, 35] as $id) { $this->assertContains($id, $ids); }
        foreach ([2, 3, 4, 5] as $id) { $this->assertNotContains($id, $ids); }
        $cashCustomer = app(Catalog::class)->customer($this->operator, 6);
        $this->assertSame('0.0000', D::value((string) $cashCustomer->b2b_credit_limit));
        $this->assertSame(0, (int) $cashCustomer->payment_term_days);
    }

    private function transfer(int $v, string $qty = '20', string $direction = 'to_b2b', string $key = ''): array
    {
        return app(InventoryService::class)->transfer($this->operator, ['location_id' => 1, 'direction' => $direction, 'items' => [['variation_id' => $v, 'quantity' => $qty]]], $key ?: 'transfer-'.bin2hex(random_bytes(8)));
    }

    private function checkout(int $v, string $qty = '5', string $price = '30', array $payments = [], string $key = ''): array
    {
        return app(SalesService::class)->checkout($this->operator, ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => $qty, 'unit_price' => $price]], 'payments' => $payments], $key ?: 'sale-'.bin2hex(random_bytes(8)));
    }

    private function b2bBalance(int $v): string { return app(Catalog::class)->stock($v, app(Catalog::class)->bucket($this->operator, 1)); }
    private function paid(string $amount): array { return [['amount' => $amount, 'method' => 'cash']]; }
    private function readyProduct(string $qty = '100'): int { $v = $this->product('finished_good', 3); $this->receipt($v, $qty); $this->transfer($v); return $v; }

    public function test_transfer_moves_selected_quantity_without_selling_or_duplicating_stock(): void
    {
        $v = $this->product('finished_good', 3); $layer = $this->receipt($v, '500', '12.3456');
        DB::table('purchase_lines')->where('id', $layer)->update(['lot_number' => 'LOT-TEST', 'exp_date' => now()->addYear()->toDateString()]);
        $result = $this->transfer($v, '200');
        $this->assertSame('300.0000', $this->balance($v)); $this->assertSame('200.0000', $this->b2bBalance($v));
        $this->assertSame('500.0000', D::add($this->balance($v), $this->b2bBalance($v)));
        $this->assertSame(0, DB::table('transactions')->where('type', 'sell')->count()); $this->assertSame(0, DB::table('transaction_payments')->count());
        $dest = DB::table('purchase_lines')->where('id', DB::table('b2b_transfer_layers')->value('destination_purchase_line_id'))->first();
        $this->assertSame('12.3456', D::value((string) $dest->purchase_price_inc_tax)); $this->assertSame('LOT-TEST', $dest->lot_number);
        $this->assertSame(now()->addYear()->toDateString(), $dest->exp_date); $this->assertSame('2469.1200', $result['cost_total']);
        $this->assertSame(2, DB::table('inventory_movements')->where('reference_type', 'b2b_transfer')->count());
    }

    public function test_multiple_selected_products_transfer_atomically_and_retry_once(): void
    {
        $a = $this->product('normal', 3); $b = $this->product('finished_good', 3); $this->receipt($a, '10'); $this->receipt($b, '20');
        $data = ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => $a, 'quantity' => '4'], ['variation_id' => $b, 'quantity' => '7']]];
        $first = app(InventoryService::class)->transfer($this->operator, $data, 'multi-transfer-key');
        $this->assertSame($first, app(InventoryService::class)->transfer($this->operator, $data, 'multi-transfer-key'));
        $this->assertSame('6.0000', $this->balance($a)); $this->assertSame('13.0000', $this->balance($b)); $this->assertSame('7.0000', $this->b2bBalance($b));
        $this->assertSame(1, DB::table('b2b_transfers')->count());
        $data['items'][1]['quantity'] = '8'; $this->postJson('/b2b/transfers', $data, ['Idempotency-Key' => 'multi-transfer-key'])->assertUnprocessable();
    }

    public function test_insufficient_second_product_rolls_back_the_entire_transfer(): void
    {
        $a = $this->product('normal', 3); $b = $this->product('normal', 3); $this->receipt($a, '10'); $this->receipt($b, '2');
        $this->postJson('/b2b/transfers', ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => $a, 'quantity' => '5'], ['variation_id' => $b, 'quantity' => '3']]], ['Idempotency-Key' => 'insufficient-key'])->assertUnprocessable();
        $this->assertSame('10.0000', $this->balance($a)); $this->assertSame(0, DB::table('b2b_transfers')->count()); $this->assertSame(0, DB::table('b2b_locations')->count());
    }

    public function test_return_transfer_preserves_cost_and_main_stock_can_be_used_again(): void
    {
        $v = $this->readyProduct(); $this->transfer($v, '6', 'to_main');
        $this->assertSame('86.0000', $this->balance($v)); $this->assertSame('14.0000', $this->b2bBalance($v));
        DB::transaction(function () use ($v) { $a = app(\App\Services\PurchaseLayerService::class)->allocate(1, 1, DB::table('variations')->where('id', $v)->value('product_id'), $v, '86', 'quantity_sold'); $this->assertSame('86.0000', array_reduce($a, fn ($n, $a) => D::add($n, $a['quantity']), '0.0000')); });
    }

    public function test_sales_require_manual_price_and_do_not_read_or_change_retail_price(): void
    {
        $v = $this->readyProduct(); $payload = ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => '5']]];
        $this->postJson('/b2b/sales', $payload, ['Idempotency-Key' => 'missing-price-key'])->assertUnprocessable();
        $payload['items'][0]['unit_price'] = '0'; $this->postJson('/b2b/sales', $payload, ['Idempotency-Key' => 'zero-price-key'])->assertUnprocessable();
        $result = $this->checkout($v, '5', '21.1234', $this->paid('105.6170'));
        $this->assertSame('105.6170', $result['total']); $this->assertSame('0.0000', $result['due']);
        $this->assertSame('80.0000', $this->balance($v)); $this->assertSame('15.0000', $this->b2bBalance($v));
        $this->assertSame('999.0000', D::value((string) DB::table('variations')->where('id', $v)->value('sell_price_inc_tax')));
        $row = $this->getJson('/b2b/data/products?pos=1&location_id=1')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('sell_price_inc_tax', $row); $this->assertArrayNotHasKey('selling_price', $row);
        $this->getJson('/b2b/invoices/'.$result['transaction_id'])->assertOk()->assertJsonPath('items.0.price_source', 'manual');
    }

    public function test_b2b_cannot_spend_main_inventory_or_oversell_its_allocation(): void
    {
        $v = $this->readyProduct();
        $this->postJson('/b2b/sales', ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => '21', 'unit_price' => '1']]], ['Idempotency-Key' => 'oversell-key'])->assertUnprocessable();
        $this->assertSame('80.0000', $this->balance($v)); $this->assertSame('20.0000', $this->b2bBalance($v)); $this->assertSame(0, DB::table('b2b_sale_details')->count());
    }

    public function test_duplicate_checkout_and_payment_are_idempotent(): void
    {
        $v = $this->readyProduct(); $a = $this->checkout($v, '5', '20', $this->paid('40'), 'duplicate-checkout-key');
        $this->assertSame($a, $this->checkout($v, '5', '20', $this->paid('40'), 'duplicate-checkout-key'));
        $service = app(SalesService::class); $payment = ['payments' => $this->paid('60')];
        $first = $service->recordPayment($this->operator, $a['transaction_id'], $payment, 'duplicate-payment-key');
        $this->assertSame($first, $service->recordPayment($this->operator, $a['transaction_id'], $payment, 'duplicate-payment-key'));
        $this->assertSame('0.0000', $first['due']); $this->assertSame(2, DB::table('transaction_payments')->count()); $this->assertSame('15.0000', $this->b2bBalance($v));
        $this->postJson('/b2b/sales/'.$a['transaction_id'].'/payments', ['payments' => $this->paid('1')], ['Idempotency-Key' => 'overpayment-key'])->assertUnprocessable();
    }

    public function test_credit_limit_and_credit_permission_are_enforced(): void
    {
        $v = $this->readyProduct(); DB::table('b2b_customer_profiles')->update(['credit_limit' => '50']);
        $data = ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => '2', 'unit_price' => '30']]];
        $this->postJson('/b2b/sales', $data, ['Idempotency-Key' => 'credit-limit-key'])->assertUnprocessable();
        $this->denied = ['b2b.credit.manage']; $data['payments'] = $this->paid('50');
        $this->postJson('/b2b/sales', $data, ['Idempotency-Key' => 'credit-denial-key'])->assertForbidden();
        $data['payments'] = $this->paid('60'); $this->postJson('/b2b/sales', $data, ['Idempotency-Key' => 'paid-sale-key'])->assertCreated();
    }

    public function test_returns_credit_due_first_then_refund_excess_and_restore_original_cost(): void
    {
        $v = $this->readyProduct(); $sale = $this->checkout($v, '10', '20', $this->paid('150')); $id = $sale['transaction_id'];
        $line = DB::table('transaction_sell_lines')->where('transaction_id', $id)->value('id');
        $data = ['items' => [['sell_line_id' => $line, 'quantity' => '2']], 'reason' => 'Unopened goods'];
        $a = app(SalesService::class)->returnItems($this->operator, $id, $data, 'return-first-key');
        $this->assertSame('10.0000', $a['due']); $this->assertSame('0.0000', $a['refund']); $this->assertSame('12.0000', $this->b2bBalance($v));
        $data['items'][0]['quantity'] = '1';
        $this->postJson('/b2b/sales/'.$id.'/returns', $data, ['Idempotency-Key' => 'return-refund-key'])->assertUnprocessable();
        $this->assertSame('12.0000', $this->b2bBalance($v));
        $data['refund_payments'] = $this->paid('10');
        $b = app(SalesService::class)->returnItems($this->operator, $id, $data, 'return-refund-key');
        $this->assertSame('10.0000', $b['refund']); $this->assertSame('0.0000', $b['due']); $this->assertSame('13.0000', $this->b2bBalance($v));
        $this->assertSame($b, app(SalesService::class)->returnItems($this->operator, $id, $data, 'return-refund-key'));
        $this->assertSame('80.0000', $this->balance($v));
        $data['items'][0]['quantity'] = '8'; $this->postJson('/b2b/sales/'.$id.'/returns', $data, ['Idempotency-Key' => 'excess-return-key'])->assertUnprocessable();
    }

    public function test_main_models_hide_b2b_balances_documents_payments_and_locations(): void
    {
        $v = $this->readyProduct(); $sale = $this->checkout($v, '2', '20', $this->paid('40'));
        $bucket = app(Catalog::class)->bucket($this->operator, 1);
        $this->assertNull(\App\BusinessLocation::find($bucket));
        $this->assertSame('80.0000', D::value((string) \App\VariationLocationDetails::where('variation_id', $v)->sum('qty_available')));
        $this->assertNull(\App\Transaction::find($sale['transaction_id'])); $this->assertSame(0, \App\TransactionPayment::count());
        $this->assertSame(0, \App\Transaction::from('transactions as t')->where('t.type', 'sell')->count());
        $this->getJson('/manufacturing/products?location_id='.$bucket)->assertForbidden();
        try { app(\App\Services\InventoryStockService::class)->change(1, DB::table('variations')->where('id', $v)->value('product_id'), $v, $bucket, '-1'); $this->fail('Main stock service must reject a B2B bucket.'); } catch (ValidationException $e) { $this->assertSame('18.0000', $this->b2bBalance($v)); }
    }

    public function test_unauthorized_tenant_and_warehouse_requests_are_rejected(): void
    {
        $v = $this->readyProduct(); $this->locations = [3];
        $this->postJson('/b2b/transfers', ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => $v, 'quantity' => '1']]], ['Idempotency-Key' => 'warehouse-denial'])->assertForbidden();
        $this->getJson('/b2b/data/products?location_id=1')->assertForbidden(); $this->getJson('/b2b/data/products')->assertOk()->assertJsonPath('data.0.b2b_quantity', '0.0000');
        $this->locations = 'all'; $other = $this->product('normal', 3, 2);
        $this->postJson('/b2b/transfers', ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => $other, 'quantity' => '1']]], ['Idempotency-Key' => 'tenant-denial'])->assertUnprocessable();
        $this->denied = ['b2b.stock.transfer']; $this->postJson('/b2b/transfers', ['location_id' => 1, 'direction' => 'to_b2b', 'items' => [['variation_id' => $v, 'quantity' => '1']]], ['Idempotency-Key' => 'permission-denial'])->assertForbidden();
    }

    public function test_disabled_products_cannot_sell_but_remaining_stock_can_return_to_main(): void
    {
        $v = $this->readyProduct(); DB::table('b2b_product_settings')->update(['is_active' => 0]);
        $this->postJson('/b2b/sales', ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => '1', 'unit_price' => '20']]], ['Idempotency-Key' => 'disabled-product'])->assertUnprocessable();
        DB::table('products')->where('id', DB::table('variations')->where('id', $v)->value('product_id'))->update(['is_inactive' => 1]);
        $this->getJson('/b2b/data/products?enrolled=1&include_inactive=1')->assertOk()->assertJsonPath('data.0.b2b_quantity', '20.0000');
        $this->getJson('/b2b/data/products?pos=1&include_inactive=1')->assertOk()->assertJsonPath('total', 0);
        $this->transfer($v, '20', 'to_main'); $this->assertSame('100.0000', $this->balance($v));
    }

    public function test_screens_invoice_and_export_render_with_permissions(): void
    {
        foreach (['', '/products','/inventory','/transfers','/customers','/pos','/sales','/dues','/reports','/movements'] as $path) { $this->get('/b2b'.$path)->assertOk()->assertSee('workspace.js')->assertSee('Transfer stock'); }
        $v = $this->readyProduct(); $sale = $this->checkout($v);
        $this->get('/b2b/invoices/'.$sale['transaction_id'])->assertOk()->assertSee('B2B invoice')->assertSee('Buyer Ltd');
        $this->get('/b2b/reports/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->denied = ['b2b.reports.view']; $this->get('/b2b/reports')->assertForbidden(); $this->get('/b2b/reports/export')->assertForbidden();
    }

    public function test_main_pos_rejects_forged_internal_location_and_stock_list_excludes_b2b(): void
    {
        $v = $this->readyProduct(); $bucket = app(Catalog::class)->bucket($this->operator, 1);
        $this->postJson('/pos', ['location_id' => $bucket])->assertForbidden();
        $main = DB::table('products as p')->join('variations as v', 'v.product_id', '=', 'p.id')->leftJoin('variation_location_details as s', function ($join) { $join->on('s.variation_id', '=', 'v.id'); \App\Support\InventoryChannel::mainLocations($join, 's.location_id'); })->where('v.id', $v)->sum('s.qty_available');
        $this->assertSame('80.0000', D::value((string) $main));
        $this->getJson('/manufacturing/products')->assertOk()->assertJsonPath('data.0.stock', '80.0000');
    }

    public function test_transferred_purchase_identity_cost_and_used_quantity_are_protected(): void
    {
        $v = $this->readyProduct(); $source = DB::table('b2b_transfer_layers')->value('source_purchase_line_id');
        $line = \App\PurchaseLine::findOrFail($source); $line->purchase_price_inc_tax = '1';
        try { $line->save(); $this->fail('Transferred source cost must be protected.'); } catch (ValidationException $e) { $this->assertSame('10.0000', D::value((string) DB::table('purchase_lines')->where('id', $source)->value('purchase_price_inc_tax'))); }
        $tx = \App\Transaction::findOrFail($line->transaction_id); $tx->status = 'pending';
        try { $tx->save(); $this->fail('Transferred source status must be protected.'); } catch (ValidationException $e) { $this->assertSame('received', DB::table('transactions')->where('id', $tx->id)->value('status')); }
    }

    public function test_customer_enrollment_creates_no_stock_and_checks_tenant_and_payment_settings(): void
    {
        $this->postJson('/b2b/customers', ['name' => 'New trade buyer', 'mobile' => '01000', 'credit_limit' => '100', 'payment_term_days' => 14, 'is_active' => true], ['Idempotency-Key' => 'customer-save-key'])->assertCreated();
        $this->assertSame(0, DB::table('variation_location_details')->count()); $this->assertSame(2, DB::table('b2b_customer_profiles')->count());
        try { \App\Contact::findOrFail(1)->delete(); $this->fail('An enrolled B2B customer identity must be preserved.'); } catch (ValidationException $e) { $this->assertNull(DB::table('contacts')->where('id', 1)->value('deleted_at')); }
        $v = $this->readyProduct(); DB::table('business_locations')->where('id', 1)->update(['default_payment_accounts' => json_encode(['cash' => ['is_enabled' => 1], 'card' => ['is_enabled' => 0]])]);
        $this->postJson('/b2b/sales', ['location_id' => 1, 'contact_id' => 1, 'items' => [['variation_id' => $v, 'quantity' => '1', 'unit_price' => '20']], 'payments' => [['amount' => '20', 'method' => 'card']]], ['Idempotency-Key' => 'disabled-method-key'])->assertUnprocessable();
        $this->assertSame('20.0000', $this->b2bBalance($v));
    }

    public function test_fractional_sales_and_repeated_partial_returns_preserve_exact_invoice_total(): void
    {
        $v = $this->product('normal', 1); $this->receipt($v, '5', '0.3333'); $this->transfer($v, '3');
        $sale = $this->checkout($v, '1', '0.3333', $this->paid('0.3333')); $line = DB::table('transaction_sell_lines')->where('transaction_id', $sale['transaction_id'])->value('id');
        $last = null;
        foreach ([['0.3', '0.1000'], ['0.3', '0.1000'], ['0.4', '0.1333']] as $i => [$qty, $refund]) {
            $last = app(SalesService::class)->returnItems($this->operator, $sale['transaction_id'], ['items' => [['sell_line_id' => $line, 'quantity' => $qty]], 'reason' => 'Fractional return', 'refund_payments' => $this->paid($refund)], 'fractional-return-'.$i);
        }
        $this->assertSame('0.0000', $last['due']); $this->assertSame('3.0000', $this->b2bBalance($v)); $this->assertSame('0.0000', app(SalesService::class)->paid($sale['transaction_id']));
    }

    public function test_b2b_reports_and_movements_are_scoped_and_prices_remain_manual(): void
    {
        $v = $this->readyProduct(); $this->checkout($v, '2', '30', $this->paid('60'));
        $this->getJson('/b2b/data/summary')->assertOk()->assertJsonPath('net_sales', '60.0000')->assertJsonPath('cost_of_goods', '20.0000')->assertJsonPath('gross_profit', '40.0000');
        $this->getJson('/b2b/data/movements?location_id=1')->assertOk()->assertJsonPath('total', 3);
        $this->getJson('/b2b/data/transfers?location_id=1')->assertOk()->assertJsonPath('total', 1);
        $this->locations = [3]; $this->getJson('/b2b/data/summary')->assertOk()->assertJsonPath('net_sales', '0.0000');
        $this->getJson('/b2b/data/movements')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_export_b2b_browser_fixture_when_requested(): void
    {
        if (getenv('B2B_EXPORT_FRONTEND_FIXTURE') !== '1') { $this->markTestSkipped('Opt-in browser fixture export.'); }
        \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8781');
        $v = $this->readyProduct('500'); $productId = DB::table('variations')->where('id', $v)->value('product_id');
        DB::table('products')->where('id', $productId)->update(['name' => '12W LED bulb', 'sku' => 'LED-12W']);
        $sale = $this->checkout($v, '5', '90', $this->paid('200')); $id = $sale['transaction_id'];
        $path = storage_path('framework/testing/b2b-frontend-preview'); if (!is_dir($path)) mkdir($path, 0777, true);
        foreach (['dashboard' => '', 'products' => '/products', 'inventory' => '/inventory', 'transfers' => '/transfers', 'pos' => '/pos', 'sales' => '/sales', 'customers' => '/customers', 'reports' => '/reports', 'invoice' => '/invoices/'.$id] as $name => $route) { file_put_contents($path.'/'.$name.'.html', $this->get('/b2b'.$route)->assertOk()->getContent()); }
        $api = []; foreach (['/b2b/data/products', '/b2b/data/customers', '/b2b/data/sales', '/b2b/data/transfers', '/b2b/data/summary', '/b2b/data/movements', '/b2b/invoices/'.$id, '/b2b/transfers/1'] as $route) { $api[$route] = $this->getJson($route)->assertOk()->json(); }
        file_put_contents($path.'/api.json', json_encode($api, JSON_PRETTY_PRINT)); $this->assertFileExists($path.'/pos.html');
    }

    public function test_accounting_event_posts_collection_once_and_refund_in_opposite_direction(): void
    {
        Event::swap($this->realEvents); session()->put('business.enabled_modules', ['account']);
        $account = DB::table('accounts')->insertGetId(['business_id' => 1, 'name' => 'Cash']);
        $v = $this->readyProduct(); $payments = [['amount' => '60', 'method' => 'cash', 'account_id' => $account]];
        $sale = $this->checkout($v, '2', '30', $payments, 'accounting-sale-key'); $this->checkout($v, '2', '30', $payments, 'accounting-sale-key');
        $this->assertSame(1, DB::table('account_transactions')->count()); $this->assertSame('credit', DB::table('account_transactions')->value('type'));
        $line = DB::table('transaction_sell_lines')->where('transaction_id', $sale['transaction_id'])->value('id');
        app(SalesService::class)->returnItems($this->operator, $sale['transaction_id'], ['reason' => 'Return', 'items' => [['sell_line_id' => $line, 'quantity' => '1']], 'refund_payments' => [['amount' => '30', 'method' => 'cash', 'account_id' => $account]]], 'accounting-refund-key');
        $this->assertSame(['credit', 'debit'], DB::table('account_transactions')->orderBy('id')->pluck('type')->all());
    }

    public function test_raw_material_to_two_components_to_finished_product_to_b2b_sale(): void
    {
        $production = app(\Modules\Manufacturing\Services\ProductionService::class);
        $raw = $this->product('raw_material', 1); $body = $this->product('component', 3); $holder = $this->product('component', 3); $circuit = $this->product('component', 3); $bulb = $this->product('finished_good', 3);
        $this->receipt($raw, '10', '100'); $this->receipt($circuit, '100', '10');
        foreach ([[$body, '40', '100'], [$holder, '10', '50']] as [$output, $grams, $labor]) {
            $bom = $this->bom($output, $raw, $grams, 2); $order = $this->order($bom['bom_version_id'], '100');
            $batch = $production->start($this->operator, $order, ['quantity' => '100'], 'start-'.bin2hex(random_bytes(8)));
            $production->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '100', 'expenses' => ['labor_cost' => $labor]], 'receive-'.bin2hex(random_bytes(8)));
        }
        $this->assertSame('5.0000', $this->balance($raw));
        $bom = app(\Modules\Manufacturing\Services\BomService::class)->save($this->operator, ['name' => 'Final assembly', 'variation_id' => $bulb, 'output_quantity' => '1', 'items' => array_map(fn ($v) => ['variation_id' => $v, 'unit_id' => 3, 'quantity' => '1'], [$body, $holder, $circuit])], 'assembly-recipe-key');
        $order = $this->order($bom['bom_version_id'], '100'); $batch = $production->start($this->operator, $order, ['quantity' => '100'], 'assembly-start-key');
        $production->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '100', 'expenses' => ['labor_cost' => '350']], 'assembly-receive-key');
        $this->assertSame('5.0000', $this->balance($raw)); $this->assertSame('0.0000', $this->balance($body)); $this->assertSame('0.0000', $this->balance($holder));
        $this->assertSame('100.0000', $this->balance($bulb));
        $this->assertSame('2000.0000', D::value((string) DB::table('mfg_production_orders')->where('id', $order)->value('actual_cost')));
        $this->transfer($bulb, '60'); $sale = $this->checkout($bulb, '10', '35', $this->paid('350'));
        $this->assertSame('40.0000', $this->balance($bulb)); $this->assertSame('50.0000', $this->b2bBalance($bulb));
        $this->assertSame('350.0000', $sale['total']);
        $this->assertSame('200.0000', D::value((string) DB::table('b2b_sale_details')->value('cost_total')));
    }

    public function test_mysql_concurrent_transfers_cannot_allocate_the_same_main_stock_twice(): void
    {
        if (! $this->testDatabase) { $this->markTestSkipped('Run MFG_TEST_MYSQL=1 for InnoDB concurrency.'); }
        $v = $this->product('finished_good', 3); $this->receipt($v, '10'); $workers = $this->workers('b2b-transfer', [$v, '6']);
        $this->assertEqualsCanonicalizing([0, 2], array_map(fn ($p) => $p->getExitCode(), $workers)); $this->assertSame('4.0000', $this->balance($v)); $this->assertSame('6.0000', $this->b2bBalance($v));
    }

    public function test_mysql_concurrent_b2b_sales_cannot_oversell_or_exceed_credit(): void
    {
        if (! $this->testDatabase) { $this->markTestSkipped('Run MFG_TEST_MYSQL=1 for InnoDB concurrency.'); }
        $v = $this->readyProduct(); DB::table('b2b_customer_profiles')->update(['credit_limit' => '100']);
        $workers = $this->workers('b2b-sale', [$v, '6']);
        $this->assertEqualsCanonicalizing([0, 2], array_map(fn ($p) => $p->getExitCode(), $workers)); $this->assertSame('14.0000', $this->b2bBalance($v));
        $this->assertSame('60.0000', app(SalesService::class)->outstanding(1, 1));
        $other = $this->product('finished_good', 3); $this->receipt($other, '10'); $this->transfer($other, '10');
        DB::table('b2b_customer_profiles')->update(['credit_limit' => '10000']);
        $workers = $this->workers('b2b-sale', [$other, '6']);
        $this->assertEqualsCanonicalizing([0, 2], array_map(fn ($p) => $p->getExitCode(), $workers));
        $this->assertSame('4.0000', $this->b2bBalance($other));
    }

    private function workers(string $operation, array $args): array
    {
        $workers = [];
        foreach ([1, 2] as $n) { $p = new Process(array_merge([PHP_BINARY, base_path('tests/Support/manufacturing_worker.php'), $this->testDatabase, $operation], array_map('strval', $args), [(string) $n]), base_path()); $p->setTimeout(30); $p->start(); $workers[] = $p; }
        foreach ($workers as $p) { $p->wait(); $this->assertMatchesRegularExpression('/worker-(posted|rejected)/', $p->getOutput(), $p->getOutput().$p->getErrorOutput()); }
        return $workers;
    }
}
