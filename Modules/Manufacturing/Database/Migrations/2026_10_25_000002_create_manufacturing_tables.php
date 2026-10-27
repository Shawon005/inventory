<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mfg_boms', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('variation_id');
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['business_id', 'variation_id']);
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('variation_id')->references('id')->on('variations');
        });
        Schema::create('mfg_bom_versions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('bom_id');
            $t->unsignedInteger('version');
            $t->decimal('output_quantity', 22, 4);
            foreach (['labor_cost', 'machine_cost', 'overhead_cost', 'other_cost'] as $c) {
                $t->decimal($c, 22, 4)->default(0);
            }
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->unique(['bom_id', 'version']);
            $t->foreign('bom_id')->references('id')->on('mfg_boms');
            $t->foreign('created_by')->references('id')->on('users');
        });
        Schema::create('mfg_bom_items', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('bom_version_id');
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('unit_id');
            $t->decimal('quantity', 22, 4);
            $t->decimal('unit_multiplier', 22, 8);
            $t->decimal('wastage_percent', 9, 4)->default(0);
            $t->decimal('base_quantity', 22, 4);
            $t->unique(['bom_version_id', 'variation_id']);
            $t->foreign('bom_version_id')->references('id')->on('mfg_bom_versions');
            $t->foreign('variation_id')->references('id')->on('variations');
            $t->foreign('unit_id')->references('id')->on('units');
        });
        Schema::create('mfg_product_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id');
            $t->unsignedInteger('product_id');
            $t->boolean('allow_purchase')->default(true);
            $t->text('production_notes')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'product_id']);
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('product_id')->references('id')->on('products');
        });
        Schema::create('mfg_production_orders', function (Blueprint $t) {
            $t->increments('id');
            foreach (['business_id', 'location_id', 'variation_id', 'bom_version_id', 'created_by'] as $c) {
                $t->unsignedInteger($c);
            }
            $t->string('order_number', 40);
            $t->string('status', 24)->default('draft');
            $t->decimal('planned_quantity', 22, 4);
            $t->decimal('accepted_quantity', 22, 4)->default(0);
            $t->decimal('defective_quantity', 22, 4)->default(0);
            $t->decimal('expected_cost', 22, 4);
            $t->decimal('actual_cost', 22, 4)->default(0);
            $t->json('bom_snapshot');
            $t->date('planned_start_date')->nullable();
            $t->date('planned_completion_date')->nullable();
            $t->unsignedInteger('assigned_to')->nullable();
            $t->unsignedInteger('parent_order_id')->nullable();
            $t->text('notes')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'order_number']);
            $t->index(['business_id', 'location_id', 'status'], 'mfg_order_business_location_status');
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('location_id')->references('id')->on('business_locations');
            $t->foreign('variation_id')->references('id')->on('variations');
            $t->foreign('bom_version_id')->references('id')->on('mfg_bom_versions');
            $t->foreign('created_by')->references('id')->on('users');
            $t->foreign('assigned_to')->references('id')->on('users');
            $t->foreign('parent_order_id')->references('id')->on('mfg_production_orders');
        });
        Schema::create('mfg_production_batches', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('order_id');
            $t->unsignedInteger('issue_transaction_id');
            $t->unsignedInteger('receipt_transaction_id')->nullable();
            $t->unsignedInteger('output_purchase_line_id')->nullable();
            $t->string('status', 24)->default('in_progress');
            $t->decimal('planned_quantity', 22, 4);
            $t->decimal('accepted_quantity', 22, 4)->default(0);
            $t->decimal('defective_quantity', 22, 4)->default(0);
            $t->decimal('material_cost', 22, 4);
            $t->decimal('expense_cost', 22, 4)->default(0);
            $t->decimal('total_cost', 22, 4);
            $t->decimal('unit_cost', 22, 4)->default(0);
            $t->decimal('valuation_rounding', 22, 4)->default(0);
            $t->string('lot_number')->nullable();
            $t->date('expiry_date')->nullable();
            $t->text('reversal_reason')->nullable();
            $t->timestamps();
            $t->foreign('order_id')->references('id')->on('mfg_production_orders');
            $t->foreign('issue_transaction_id')->references('id')->on('transactions');
            $t->foreign('receipt_transaction_id')->references('id')->on('transactions');
            $t->foreign('output_purchase_line_id')->references('id')->on('purchase_lines');
        });
        Schema::create('mfg_material_issue_lines', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('batch_id');
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('purchase_line_id');
            $t->unsignedInteger('sell_line_id');
            $t->decimal('quantity', 22, 4);
            $t->decimal('unit_cost', 22, 4);
            $t->decimal('total_cost', 22, 4);
            $t->decimal('wastage_quantity', 22, 4)->default(0);
            $t->decimal('returned_quantity', 22, 4)->default(0);
            $t->decimal('returned_cost', 22, 4)->default(0);
            $t->foreign('batch_id')->references('id')->on('mfg_production_batches');
            $t->foreign('variation_id')->references('id')->on('variations');
            $t->foreign('purchase_line_id')->references('id')->on('purchase_lines');
            $t->foreign('sell_line_id')->references('id')->on('transaction_sell_lines');
        });
        Schema::create('mfg_production_expenses', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('batch_id');
            $t->string('type', 24);
            $t->decimal('amount', 22, 4);
            $t->unsignedInteger('created_by');
            $t->timestamp('created_at');
            $t->foreign('batch_id')->references('id')->on('mfg_production_batches');
            $t->foreign('created_by')->references('id')->on('users');
            $t->unique(['batch_id', 'type']);
        });
        foreach (['view', 'materials.manage', 'components.manage', 'bom.manage', 'orders.create', 'orders.start', 'orders.complete', 'stock.adjust', 'reports.view'] as $ability) {
            DB::table('permissions')->insertOrIgnore(['name' => 'manufacturing.'.$ability, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        // Installation markers are deliberately not written until UI/legacy hooks are integrated.
    }

    public function down()
    {
        if (DB::table('mfg_production_orders')->exists() || DB::table('mfg_boms')->exists() || DB::table('mfg_product_settings')->exists()) {
            throw new RuntimeException('Manufacturing records exist; rollback would remove business history.');
        }
        foreach (['mfg_production_expenses', 'mfg_material_issue_lines', 'mfg_production_batches', 'mfg_production_orders', 'mfg_product_settings', 'mfg_bom_items', 'mfg_bom_versions', 'mfg_boms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
