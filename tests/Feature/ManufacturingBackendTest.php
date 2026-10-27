<?php

namespace Tests\Feature;

use App\InventoryMovement;
use App\Support\Decimal as D;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Manufacturing\Services\BomService;
use Modules\Manufacturing\Services\ManufacturingCatalog;
use Modules\Manufacturing\Services\ProductionService;
use Symfony\Component\Process\Process;
use Tests\Support\ManufacturingTestCase;

class ManufacturingBackendTest extends ManufacturingTestCase
{
    public function test_component_production_uses_converted_raw_material_and_receives_stock_once(): void
    {
        $raw = $this->product(); $component = $this->product('component', 3); $line = $this->receipt($raw);
        $bom = $this->bom($component, $raw, '40', 2); // 40 grams = .04 KG.
        $order = $this->order($bom['bom_version_id'], '100');
        $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '100'], 'start-components');
        $this->assertSame('96.0000', $this->balance($raw));
        $this->assertSame('0.0000', $this->balance($component));
        $result = $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '100', 'expenses' => ['labor_cost' => '20']], 'receive-components');
        $this->assertSame('100.0000', $this->balance($component));
        $this->assertSame('0.6000', $result['unit_cost']);
        $this->assertSame('60.0000', $result['total_cost']);
        $this->assertSame('4.0000', D::value((string) DB::table('purchase_lines')->where('id', $line)->value('mfg_quantity_used')));
        $this->assertSame($result, $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '100', 'expenses' => ['labor_cost' => '20']], 'receive-components'));
        $this->assertSame('100.0000', $this->balance($component));
        $this->assertSame(2, DB::table('inventory_movements')->count());
        $this->assertSame('completed', DB::table('mfg_production_orders')->where('id', $order)->value('status'));
    }

    public function test_assembly_consumes_component_cost_without_consuming_its_raw_material_again(): void
    {
        $raw = $this->product(); $component = $this->product('component', 3); $finished = $this->product('finished_good', 3);
        $this->receipt($raw, '100', '10');
        $componentBom = $this->bom($component, $raw, '0.04'); $componentOrder = $this->order($componentBom['bom_version_id'], '10');
        $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $componentOrder, ['quantity' => '10'], 'start-body-001');
        $service->receive($this->operator, $componentOrder, $batch['batch_id'], ['accepted_quantity' => '10', 'expenses' => ['labor_cost' => '6']], 'receive-body-001');
        $finalBom = $this->bom($finished, $component, '1', 3); $finalOrder = $this->order($finalBom['bom_version_id'], '10');
        $batch = $service->start($this->operator, $finalOrder, ['quantity' => '10'], 'start-bulbs-001');
        $result = $service->receive($this->operator, $finalOrder, $batch['batch_id'], ['accepted_quantity' => '10'], 'receive-bulbs-001');
        $this->assertSame('99.6000', $this->balance($raw));
        $this->assertSame('0.0000', $this->balance($component));
        $this->assertSame('10.0000', $this->balance($finished));
        $this->assertSame('10.0000', $result['total_cost']);
    }

    public function test_nested_bom_planning_aggregates_leaf_quantities(): void
    {
        $raw = $this->product(); $component = $this->product('component', 3); $finished = $this->product('finished_good', 3);
        $this->bom($component, $raw, '40', 2); $bom = $this->bom($finished, $component, '2', 3);
        $leaves = app(BomService::class)->explode(1, $bom['bom_version_id'], '100');
        $this->assertCount(1, $leaves); $this->assertEquals($raw, $leaves[0]['variation_id']);
        $this->assertSame('8.0000', $leaves[0]['quantity']);
    }

    public function test_circular_bom_revision_rolls_back_and_preserves_previous_version(): void
    {
        $raw = $this->product(); $a = $this->product('component', 3); $b = $this->product('component', 3);
        $aBom = $this->bom($a, $raw); $this->bom($b, $a, '1', 3);
        $this->rejected(fn () => app(BomService::class)->save($this->operator, ['name' => 'Cycle', 'variation_id' => $a, 'output_quantity' => '1', 'items' => [['variation_id' => $b, 'unit_id' => 3, 'quantity' => '1']]], 'cycle-revision-001', $aBom['bom_id']));
        $this->assertSame(2, DB::table('mfg_bom_versions')->count());
        $this->assertEquals(1, DB::table('mfg_bom_versions')->where('id', $aBom['bom_version_id'])->value('is_active'));
    }

    public function test_order_snapshot_survives_bom_revision(): void
    {
        $raw = $this->product(); $output = $this->product('component', 3); $this->receipt($raw);
        $bom = $this->bom($output, $raw); $order = $this->order($bom['bom_version_id']);
        app(BomService::class)->save($this->operator, ['name' => 'Changed', 'variation_id' => $output, 'output_quantity' => '1', 'items' => [['variation_id' => $raw, 'unit_id' => 1, 'quantity' => '2']]], 'revise-bom-001', $bom['bom_id']);
        app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'start-snapshot-001');
        $this->assertSame('90.0000', $this->balance($raw));
    }

    public function test_insufficient_stock_rolls_back_all_materials_and_posting_records(): void
    {
        $raw = $this->product(); $output = $this->product('component', 3); $this->receipt($raw, '5');
        $bom = $this->bom($output, $raw); $order = $this->order($bom['bom_version_id']); $before = DB::table('inventory_postings')->count();
        $this->rejected(fn () => app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'start-shortage-001'));
        $this->assertSame('5.0000', $this->balance($raw));
        $this->assertSame(0, DB::table('mfg_production_batches')->count());
        $this->assertSame(0, DB::table('inventory_movements')->count());
        $this->assertSame($before, DB::table('inventory_postings')->count());
    }

    public function test_multiple_batches_partially_complete_then_complete_an_order(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '4'], 'partial-start-001');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '4'], 'partial-receipt-001');
        $this->assertSame('partially_completed', DB::table('mfg_production_orders')->where('id', $order)->value('status'));
        $this->assertSame('4.0000', $this->balance($output));
        $batch = $service->start($this->operator, $order, ['quantity' => '6'], 'partial-start-002');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '6'], 'partial-receipt-002');
        $this->assertSame('completed', DB::table('mfg_production_orders')->where('id', $order)->value('status'));
        $this->assertSame('10.0000', $this->balance($output)); $this->assertSame('90.0000', $this->balance($raw));
    }

    public function test_retries_do_not_reissue_and_different_payload_with_same_key_is_rejected(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $first = $service->start($this->operator, $order, ['quantity' => '10'], 'same-batch-key');
        $this->assertSame($first, $service->start($this->operator, $order, ['quantity' => '10'], 'same-batch-key'));
        $this->rejected(fn () => $service->start($this->operator, $order, ['quantity' => '5'], 'same-batch-key'));
        $this->assertSame('90.0000', $this->balance($raw)); $this->assertSame(1, DB::table('mfg_production_batches')->count());
    }

    public function test_new_key_cannot_receive_a_completed_batch_twice(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-once-001');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'receive-once-001');
        $this->rejected(fn () => $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'receive-twice-001'));
        $this->assertSame('10.0000', $this->balance($output));
    }

    public function test_defects_are_not_received_and_all_actual_expenses_are_capitalized(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-costs-001');
        $result = $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '8', 'defective_quantity' => '2', 'expenses' => ['labor_cost' => '20', 'machine_cost' => '10', 'overhead_cost' => '15', 'other_cost' => '15']], 'receive-costs-001');
        $this->assertSame('160.0000', $result['total_cost']); $this->assertSame('20.0000', $result['unit_cost']);
        $this->assertSame('8.0000', $this->balance($output));
    }

    public function test_unused_material_returns_reduce_wip_and_original_cost_layer_usage(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-returns-001');
        $result = $service->returnMaterials($this->operator, $order, $batch['batch_id'], ['variation_id' => $raw, 'quantity' => '2', 'reason' => 'Unused'], 'material-return-001');
        $this->assertSame('20.0000', $result['returned_cost']); $this->assertSame('92.0000', $this->balance($raw));
        $result = $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'return-receipt-001');
        $this->assertSame('8.0000', $result['unit_cost']);
    }

    public function test_reversal_restores_stock_and_cost_layers_and_keeps_history(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-reverse-001');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'receive-reverse-001');
        $service->reverse($this->operator, $order, $batch['batch_id'], 'Wrong run', 'reverse-batch-001');
        $this->assertSame('100.0000', $this->balance($raw)); $this->assertSame('0.0000', $this->balance($output));
        $this->assertSame('0.0000', D::value((string) DB::table('purchase_lines')->where('variation_id', $raw)->sum('mfg_quantity_used')));
        $this->assertSame(4, DB::table('inventory_movements')->count()); $this->assertSame(3, DB::table('transactions')->count());
        $this->assertSame('0.0000', D::value((string) DB::table('inventory_movements')->sum('quantity')));
    }

    public function test_output_already_consumed_cannot_be_reversed(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-used-001');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'receive-used-001');
        DB::table('purchase_lines')->where('variation_id', $output)->update(['quantity_sold' => '1']);
        $this->rejected(fn () => $service->reverse($this->operator, $order, $batch['batch_id'], 'Unsafe', 'reverse-used-001'));
        $this->assertSame('90.0000', $this->balance($raw)); $this->assertSame('10.0000', $this->balance($output));
    }

    public function test_zero_accepted_run_requires_explicit_scrap_and_has_no_stock_receipt(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'start-scrap-001');
        $this->rejected(fn () => $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '0', 'defective_quantity' => '10'], 'bad-scrap-001'));
        $service->receive($this->operator, $order, $batch['batch_id'], ['reason' => 'Failed batch'], 'scrap-run-001', true);
        $this->assertSame('0.0000', $this->balance($output)); $this->assertSame(0, DB::table('transactions')->where('type', 'production_purchase')->count());
    }

    public function test_fifo_and_lifo_use_the_correct_layer_cost(): void
    {
        $raw = $this->product(); $output = $this->product('component', 3);
        $this->receipt($raw, '5', '10', 1, '2026-01-01'); $this->receipt($raw, '5', '20', 1, '2026-01-02');
        $bom = $this->bom($output, $raw); $order = $this->order($bom['bom_version_id'], '5');
        DB::table('business')->where('id', 1)->update(['accounting_method' => 'lifo']);
        $batch = app(ProductionService::class)->start($this->operator, $order, ['quantity' => '5'], 'lifo-start-001');
        $this->assertSame('100.0000', $batch['material_cost']);
    }

    public function test_disallowed_valuation_method_rolls_back_instead_of_silently_using_fifo(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        DB::table('business')->where('id', 1)->update(['accounting_method' => 'avco']);
        $this->rejected(fn () => app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'avco-start-001'));
        $this->assertSame('100.0000', $this->balance($raw));
    }

    public function test_product_classification_creates_no_stock_or_duplicate_product(): void
    {
        $variation = $this->product('normal', 3); $product = DB::table('variations')->where('id', $variation)->value('product_id');
        $this->patchJson('/manufacturing/products/'.$product, ['manufacturing_type' => 'component', 'allow_purchase' => true])->assertOk();
        $this->assertSame(1, DB::table('products')->count()); $this->assertSame('0.0000', $this->balance($variation));
    }

    public function test_permissions_are_enforced_by_the_command_service_and_http_endpoint(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $this->denied = ['manufacturing.orders.start'];
        $this->postJson('/manufacturing/production-orders/'.$order.'/start', ['quantity' => '10', 'idempotency_key' => 'denied-start-001'])->assertForbidden();
        $this->assertSame('100.0000', $this->balance($raw));
        $this->assertSame(0, DB::table('mfg_production_batches')->count());
    }

    public function test_cross_tenant_and_forbidden_warehouse_access_is_rejected(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        $this->getJson('/manufacturing/production-orders?location_id=2')->assertNotFound();
        $this->locations = [3]; $this->getJson('/manufacturing/production-orders/'.$order)->assertForbidden();
        $this->getJson('/manufacturing/production-orders')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_cross_tenant_bom_input_is_rejected(): void
    {
        $foreign = $this->product('raw_material', 1, 2); $output = $this->product('component', 3);
        $this->rejected(fn () => $this->bom($output, $foreign));
        $this->assertSame(0, DB::table('mfg_boms')->count());
    }

    public function test_whole_unit_and_unit_family_rules_are_enforced(): void
    {
        $input = $this->product('raw_material', 3); $output = $this->product('component', 3);
        $this->rejected(fn () => $this->bom($output, $input, '0.5', 3));
        $this->rejected(fn () => $this->bom($output, $input, '1', 1));
        $this->assertSame(0, DB::table('mfg_boms')->count());
    }

    public function test_manufactured_stock_is_available_to_existing_retail_stock_and_layer_mapping(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'retail-start-001');
        $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '10'], 'retail-receive-001');
        $product = DB::table('variations')->where('id', $output)->value('product_id');
        DB::transaction(function () use ($output, $product) {
            app(ProductUtil::class)->decreaseProductQuantity($product, $output, 1, '3');
            $sale = DB::table('transactions')->insertGetId(['business_id' => 1, 'location_id' => 1, 'type' => 'sell', 'status' => 'final', 'transaction_date' => now(), 'created_by' => 1]);
            $line = DB::table('transaction_sell_lines')->insertGetId(['transaction_id' => $sale, 'product_id' => $product, 'variation_id' => $output, 'quantity' => '3']);
            app(\App\Utils\TransactionUtil::class)->mapPurchaseSell(['id' => 1, 'location_id' => 1, 'accounting_method' => 'fifo'], [(object) ['id' => $line, 'product_id' => $product, 'variation_id' => $output, 'quantity' => '3']], 'purchase', false);
        });
        $this->assertSame('7.0000', $this->balance($output));
        $this->assertSame('3.0000', D::value((string) DB::table('purchase_lines')->where('variation_id', $output)->value('quantity_sold')));
    }

    public function test_normal_stock_helpers_preserve_overselling_configuration(): void
    {
        $raw = $this->product(); $this->receipt($raw, '2'); $product = DB::table('variations')->where('id', $raw)->value('product_id');
        $this->rejected(fn () => app(ProductUtil::class)->decreaseProductQuantity($product, $raw, 1, '3'));
        DB::table('business')->where('id', 1)->update(['pos_settings' => '{"allow_overselling":true}']);
        app(ProductUtil::class)->decreaseProductQuantity($product, $raw, 1, '3');
        $this->assertSame('-1.0000', $this->balance($raw));
    }

    public function test_cancel_requires_reversal_of_posted_batches(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'cancel-start-001');
        $this->rejected(fn () => $service->cancel($this->operator, $order, 'Stop', 'cancel-order-001'));
        $service->reverse($this->operator, $order, $batch['batch_id'], 'Stop', 'cancel-reverse-001');
        $service->cancel($this->operator, $order, 'Stop', 'cancel-order-002');
        $this->assertSame('cancelled', DB::table('mfg_production_orders')->where('id', $order)->value('status'));
        $this->assertSame('100.0000', $this->balance($raw));
    }

    public function test_movement_model_rejects_historical_edits(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'audit-start-001');
        $movement = InventoryMovement::firstOrFail(); $movement->quantity = '99';
        $this->expectException(\LogicException::class); $movement->save();
    }

    public function test_mysql_concurrent_stock_deductions_cannot_oversell(): void
    {
        if (! $this->testDatabase) { $this->markTestSkipped('Run with MFG_TEST_MYSQL=1 for InnoDB concurrency.'); }
        $raw = $this->product(); $this->receipt($raw, '10');
        $workers = $this->workers('stock', [$raw, '6']);
        $this->assertEqualsCanonicalizing([0, 2], array_map(fn ($p) => $p->getExitCode(), $workers));
        $this->assertSame('4.0000', $this->balance($raw));
    }

    public function test_existing_purchase_workflow_receives_raw_material_and_audits_decimal_stock(): void
    {
        $raw = $this->product();
        $this->purchaseThroughExistingUtility($raw, '3.1255', '2.1');
        $this->assertSame('3.1255', $this->balance($raw));
        $movement = DB::table('inventory_movements')->first();
        $this->assertSame('3.1255', D::value((string) $movement->quantity));
        $this->assertSame('6.5636', D::value((string) $movement->total_cost));
        $this->assertSame('purchase_stock_change', $movement->movement_type);
    }

    public function test_purchase_subunit_conversion_preserves_stock_below_display_precision(): void
    {
        $raw = $this->product(); $this->purchaseThroughExistingUtility($raw, '1', '0.02', 2);
        $this->assertSame('0.0010', $this->balance($raw));
        $line = DB::table('purchase_lines')->first();
        $this->assertSame('20.0000', D::value((string) $line->purchase_price));
        $this->assertSame('0.0200', D::value((string) DB::table('inventory_movements')->value('total_cost')));
    }

    public function test_internal_only_component_purchase_is_rejected_and_stock_is_rolled_back(): void
    {
        $component = $this->product('component', 3); $productId = DB::table('variations')->where('id', $component)->value('product_id');
        DB::table('mfg_product_settings')->insert(['business_id' => 1, 'product_id' => $productId, 'allow_purchase' => false]);
        $this->rejected(fn () => $this->purchaseThroughExistingUtility($component, '10', '2'));
        $this->assertSame('0.0000', $this->balance($component));
        $this->assertSame(0, DB::table('purchase_lines')->count());
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    public function test_internal_only_setting_also_blocks_receiving_a_preexisting_pending_purchase(): void
    {
        $raw = $this->product(); $productId = DB::table('variations')->where('id', $raw)->value('product_id');
        $txId = DB::table('transactions')->insertGetId(['business_id' => 1, 'location_id' => 1, 'type' => 'purchase', 'status' => 'pending', 'transaction_date' => now(), 'created_by' => 1]);
        $lineId = DB::table('purchase_lines')->insertGetId(['transaction_id' => $txId, 'product_id' => $productId, 'variation_id' => $raw, 'quantity' => '10', 'purchase_price' => '2', 'purchase_price_inc_tax' => '2']);
        DB::table('mfg_product_settings')->insert(['business_id' => 1, 'product_id' => $productId, 'allow_purchase' => false]);
        $this->rejected(fn () => DB::transaction(function () use ($txId, $lineId, $productId, $raw) {
            $transaction = \App\Transaction::findOrFail($txId); $transaction->status = 'received'; $transaction->save();
            app(ProductUtil::class)->createOrUpdatePurchaseLines($transaction, [['product_id' => $productId, 'variation_id' => $raw, 'purchase_line_id' => $lineId, 'quantity' => '10']], null, false, 'pending');
        }));
        $this->assertSame('0.0000', $this->balance($raw));
        $this->assertSame('pending', DB::table('transactions')->where('id', $txId)->value('status'));
    }

    public function test_positive_and_negative_adjustments_preserve_layer_and_ledger_consistency(): void
    {
        $raw = $this->product(); $service = app(\Modules\Manufacturing\Services\StockAdjustmentService::class);
        $positive = ['variation_id' => $raw, 'location_id' => 1, 'quantity' => '10', 'unit_cost' => '3.25', 'reason' => 'Authorized opening count'];
        $service->post($this->operator, $positive, 'adjustment-positive-001');
        $service->post($this->operator, $positive, 'adjustment-positive-001');
        $service->post($this->operator, ['variation_id' => $raw, 'location_id' => 1, 'quantity' => '-2.5', 'reason' => 'Material wastage'], 'adjustment-negative-001');
        $this->assertSame('7.5000', $this->balance($raw));
        $this->assertSame('2.5000', D::value((string) DB::table('purchase_lines')->value('quantity_adjusted')));
        $this->assertSame('7.5000', D::value((string) DB::table('inventory_movements')->sum('quantity')));
        $this->assertSame(2, DB::table('transactions')->count());
    }

    public function test_material_waste_cannot_be_returned_as_unused_stock(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10', 'materials' => [['variation_id' => $raw, 'quantity' => '10', 'wastage_quantity' => '2']]], 'waste-start-001');
        $this->rejected(fn () => $service->returnMaterials($this->operator, $order, $batch['batch_id'], ['variation_id' => $raw, 'quantity' => '9', 'reason' => 'Incorrect return'], 'waste-return-001'));
        $this->assertSame('90.0000', $this->balance($raw));
    }

    public function test_reversal_after_partial_material_return_restores_only_net_consumption(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'net-start-001');
        $service->returnMaterials($this->operator, $order, $batch['batch_id'], ['variation_id' => $raw, 'quantity' => '2', 'reason' => 'Unused'], 'net-return-001');
        $service->reverse($this->operator, $order, $batch['batch_id'], 'Cancelled run', 'net-reverse-001');
        $this->assertSame('100.0000', $this->balance($raw));
        $this->assertSame('0.0000', D::value((string) DB::table('inventory_movements')->sum('quantity')));
    }

    public function test_archiving_a_material_does_not_prevent_safe_reversal(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '10'], 'archive-start-001');
        DB::table('products')->where('id', DB::table('variations')->where('id', $raw)->value('product_id'))->update(['is_inactive' => true]);
        $service->reverse($this->operator, $order, $batch['batch_id'], 'Restore materials', 'archive-reverse-001');
        $this->assertSame('100.0000', $this->balance($raw));
    }

    public function test_expired_material_layers_cannot_be_consumed_despite_positive_balance(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        DB::table('purchase_lines')->where('variation_id', $raw)->update(['exp_date' => '2000-01-01']);
        $this->rejected(fn () => app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'expired-start-001'));
        $this->assertSame('100.0000', $this->balance($raw));
    }

    public function test_receipt_rounding_difference_is_explicit_and_not_lost(): void
    {
        $raw = $this->product(); $output = $this->product('component', 3); $this->receipt($raw, '1', '1');
        $bom = app(BomService::class)->save($this->operator, ['name' => 'Three units', 'variation_id' => $output, 'output_quantity' => '3', 'items' => [['variation_id' => $raw, 'quantity' => '1', 'unit_id' => 1]]], 'rounding-bom-001');
        $order = $this->order($bom['bom_version_id'], '3'); $service = app(ProductionService::class);
        $batch = $service->start($this->operator, $order, ['quantity' => '3'], 'rounding-start-001');
        $result = $service->receive($this->operator, $order, $batch['batch_id'], ['accepted_quantity' => '3'], 'rounding-receive-001');
        $this->assertSame('0.3333', $result['unit_cost']); $this->assertSame('0.0001', $result['valuation_rounding']);
        $this->assertSame(1, DB::table('inventory_movements')->where('movement_type', 'production_rounding')->count());
    }

    public function test_consumed_purchase_cannot_be_reduced_repriced_or_moved(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'protected-layer-001');
        $line = \App\PurchaseLine::where('variation_id', $raw)->firstOrFail();
        $line->quantity = '5'; $this->rejected(fn () => $line->save());
        $line = $line->fresh(); $line->purchase_price = '99'; $this->rejected(fn () => $line->save());
        $transaction = \App\Transaction::findOrFail($line->transaction_id); $transaction->status = 'pending';
        $this->rejected(fn () => $transaction->save());
        $this->assertSame('90.0000', $this->balance($raw));
    }

    public function test_http_requires_idempotency_and_consistent_business_session(): void
    {
        $this->postJson('/manufacturing/production-orders', [])->assertUnprocessable();
        $this->withSession(['user.business_id' => 2])->getJson('/manufacturing')->assertForbidden();
    }

    public function test_unstarted_order_can_be_edited_but_posted_order_cannot(): void
    {
        [$raw, $output, $order] = $this->readyOrder(); $service = app(ProductionService::class);
        $service->update($this->operator, $order, ['planned_quantity' => '5', 'notes' => 'Small run'], 'edit-planned-001');
        $this->assertSame('5.0000', D::value((string) DB::table('mfg_production_orders')->where('id', $order)->value('planned_quantity')));
        $this->assertSame('100.0000', $this->balance($raw));
        $service->start($this->operator, $order, ['quantity' => '5'], 'edit-start-001');
        $this->rejected(fn () => $service->update($this->operator, $order, ['planned_quantity' => '8'], 'edit-posted-001'));
    }

    public function test_production_cost_uses_the_existing_tax_inclusive_inventory_valuation_basis(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        DB::table('purchase_lines')->where('variation_id', $raw)->update(['purchase_price_inc_tax' => '12']);
        $batch = app(ProductionService::class)->start($this->operator, $order, ['quantity' => '10'], 'tax-cost-start-001');
        $this->assertSame('120.0000', $batch['material_cost']);
    }

    public function test_referenced_product_units_and_variations_cannot_be_destroyed(): void
    {
        [$raw, $output, $order] = $this->readyOrder();
        $product = \App\Product::findOrFail(DB::table('variations')->where('id', $raw)->value('product_id'));
        $product->unit_id = 3; $this->rejected(fn () => $product->save());
        $this->rejected(fn () => \App\Variation::findOrFail($raw)->delete());
        $this->rejected(fn () => $product->fresh()->delete());
    }

    public function test_explicit_stock_reconciliation_is_audited_and_does_not_create_new_cost_layers(): void
    {
        $raw = $this->product(); $this->receipt($raw, '10');
        DB::table('variation_location_details')->where('variation_id', $raw)->update(['qty_available' => '8']);
        app(ProductUtil::class)->fixVariationStockMisMatch(1, $raw, 1, '10');
        $this->assertSame('10.0000', $this->balance($raw));
        $this->assertSame(1, DB::table('purchase_lines')->count());
        $movement = DB::table('inventory_movements')->first();
        $this->assertSame('stock_reconciliation', $movement->movement_type);
        $this->assertSame('2.0000', D::value((string) $movement->quantity));
    }

    public function test_real_spatie_permissions_control_backend_access(): void
    {
        \Illuminate\Support\Facades\Schema::create('roles', function ($t) { $t->increments('id'); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        \Illuminate\Support\Facades\Schema::create('model_has_roles', function ($t) { $t->unsignedInteger('role_id'); $t->string('model_type'); $t->unsignedInteger('model_id'); });
        \Illuminate\Support\Facades\Schema::create('model_has_permissions', function ($t) { $t->unsignedInteger('permission_id'); $t->string('model_type'); $t->unsignedInteger('model_id'); });
        \Illuminate\Support\Facades\Schema::create('role_has_permissions', function ($t) { $t->unsignedInteger('permission_id'); $t->unsignedInteger('role_id'); });
        $user = \App\User::findOrFail(1); $user->forceFill(['user_type' => 'user', 'allow_login' => 1]); $this->actingAs($user);
        $this->getJson('/manufacturing')->assertForbidden();
        $user->givePermissionTo('manufacturing.view');
        $this->getJson('/manufacturing')->assertOk();
        $this->postJson('/manufacturing/boms', ['idempotency_key' => 'real-denied-bom'])->assertForbidden();
    }

    public function test_migration_preflight_rejects_duplicates_without_deleting_or_changing_stock(): void
    {
        (require base_path('Modules/Manufacturing/Database/Migrations/2026_10_25_000002_create_manufacturing_tables.php'))->down();
        $migration = require database_path('migrations/2026_10_25_000001_add_manufacturing_stock_foundation.php');
        $migration->down();
        foreach (['3', '7'] as $quantity) { DB::table('variation_location_details')->insert(['product_id' => 1, 'product_variation_id' => 1, 'variation_id' => 1, 'location_id' => 1, 'qty_available' => $quantity]); }
        try { $migration->up(); $this->fail('Duplicate stock rows must stop installation.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Duplicate', $e->getMessage()); }
        $this->assertSame(2, DB::table('variation_location_details')->count());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('products', 'manufacturing_type'));
    }

    public function test_migration_rollback_refuses_to_erase_business_records(): void
    {
        $this->readyOrder();
        foreach ([base_path('Modules/Manufacturing/Database/Migrations/2026_10_25_000002_create_manufacturing_tables.php'), database_path('migrations/2026_10_25_000001_add_manufacturing_stock_foundation.php')] as $path) {
            try { (require $path)->down(); $this->fail('Business history must stop rollback.'); }
            catch (\RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertSame(1, DB::table('mfg_production_orders')->count());
    }

    public function test_mysql_concurrent_identical_production_requests_post_one_batch(): void
    {
        if (! $this->testDatabase) { $this->markTestSkipped('Run with MFG_TEST_MYSQL=1 for InnoDB concurrency.'); }
        [$raw, $output, $order] = $this->readyOrder();
        $workers = $this->workers('start', [$order, '10']);
        $this->assertSame([0, 0], array_map(fn ($p) => $p->getExitCode(), $workers));
        $this->assertSame(1, DB::table('mfg_production_batches')->count());
        $this->assertSame('90.0000', $this->balance($raw));
    }

    private function workers(string $operation, array $args): array
    {
        $workers = [];
        foreach ([1, 2] as $n) {
            $p = new Process(array_merge([PHP_BINARY, base_path('tests/Support/manufacturing_worker.php'), $this->testDatabase, $operation], array_map('strval', $args)), base_path());
            $p->setTimeout(20); $p->start(); $workers[] = $p;
        }
        foreach ($workers as $p) {
            $p->wait();
            $this->assertNotSame(1, $p->getExitCode(), $p->getOutput().$p->getErrorOutput());
            $this->assertMatchesRegularExpression('/worker-(posted|rejected)/', $p->getOutput(), $p->getOutput().$p->getErrorOutput());
        }
        return $workers;
    }

    private function readyOrder(): array
    {
        $raw = $this->product(); $output = $this->product('component', 3); $this->receipt($raw);
        $bom = $this->bom($output, $raw); return [$raw, $output, $this->order($bom['bom_version_id'])];
    }

    private function purchaseThroughExistingUtility(int $variationId, string $quantity, string $cost, ?int $subUnit = null): void
    {
        session()->put('currency', ['thousand_separator' => ',', 'decimal_separator' => '.', 'symbol' => '$', 'code' => 'USD']);
        $variation = DB::table('variations')->where('id', $variationId)->first();
        $unitId = DB::table('products')->where('id', $variation->product_id)->value('unit_id');
        $id = DB::table('transactions')->insertGetId(['business_id' => 1, 'location_id' => 1, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => now(), 'created_by' => 1]);
        app(ProductUtil::class)->createOrUpdatePurchaseLines(\App\Transaction::findOrFail($id), [[
            'product_id' => $variation->product_id, 'variation_id' => $variationId, 'quantity' => $quantity,
            'product_unit_id' => $unitId, 'sub_unit_id' => $subUnit, 'pp_without_discount' => $cost,
            'purchase_price' => $cost, 'purchase_price_inc_tax' => $cost, 'discount_percent' => '0', 'item_tax' => '0', 'purchase_line_tax_id' => null,
        ]], (object) ['thousand_separator' => ',', 'decimal_separator' => '.'], false);
    }

    private function rejected(callable $callback): void
    {
        try { $callback(); $this->fail('Expected a validation failure.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }
}
