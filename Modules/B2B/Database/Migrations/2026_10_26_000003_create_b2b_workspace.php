<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_postings')) { throw new RuntimeException('Install the shared manufacturing inventory foundation first.'); }
        Schema::table('transactions', function (Blueprint $t) { $t->string('sales_channel', 16)->default('retail')->index(); });
        Schema::create('b2b_product_settings', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('product_id');
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['business_id', 'product_id']);
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('product_id')->references('id')->on('products');
        });
        // Each permitted main warehouse has an internal stock location. No duplicate balance table.
        Schema::create('b2b_locations', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id');
            $t->unsignedInteger('main_location_id'); $t->unsignedInteger('stock_location_id'); $t->timestamps();
            $t->unique(['business_id', 'main_location_id']); $t->unique('stock_location_id');
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('main_location_id')->references('id')->on('business_locations');
            $t->foreign('stock_location_id')->references('id')->on('business_locations');
        });
        Schema::create('b2b_customer_profiles', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('contact_id');
            $t->boolean('is_active')->default(true); $t->decimal('credit_limit', 22, 4)->default(0);
            $t->unsignedInteger('payment_term_days')->default(0); $t->timestamps();
            $t->unique(['business_id', 'contact_id']);
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('contact_id')->references('id')->on('contacts');
        });
        Schema::create('b2b_transfers', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('main_location_id');
            $t->unsignedInteger('source_location_id'); $t->unsignedInteger('destination_location_id');
            $t->unsignedInteger('out_transaction_id'); $t->unsignedInteger('in_transaction_id');
            $t->unsignedInteger('posting_id'); $t->string('direction', 12); $t->string('reference', 40);
            $t->text('notes')->nullable(); $t->unsignedInteger('created_by'); $t->timestamps();
            $t->unique(['business_id', 'reference']); $t->unique('posting_id');
            $t->foreign('business_id')->references('id')->on('business');
            foreach (['main_location_id', 'source_location_id', 'destination_location_id'] as $c) { $t->foreign($c)->references('id')->on('business_locations'); }
            foreach (['out_transaction_id', 'in_transaction_id'] as $c) { $t->foreign($c)->references('id')->on('transactions'); }
            $t->foreign('posting_id')->references('id')->on('inventory_postings');
            $t->foreign('created_by')->references('id')->on('users');
        });
        Schema::create('b2b_transfer_layers', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('transfer_id');
            $t->unsignedInteger('source_purchase_line_id'); $t->unsignedInteger('destination_purchase_line_id');
            $t->unsignedInteger('sell_line_id'); $t->decimal('quantity', 22, 4); $t->decimal('unit_cost', 22, 4);
            $t->foreign('transfer_id')->references('id')->on('b2b_transfers');
            foreach (['source_purchase_line_id', 'destination_purchase_line_id'] as $c) { $t->foreign($c)->references('id')->on('purchase_lines'); }
            $t->foreign('sell_line_id')->references('id')->on('transaction_sell_lines');
        });
        Schema::create('b2b_sale_details', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('main_location_id'); $t->unsignedInteger('posting_id');
            $t->date('due_date'); $t->decimal('cost_total', 22, 4)->default(0);
            $t->decimal('returned_total', 22, 4)->default(0); $t->decimal('returned_cost', 22, 4)->default(0);
            $t->json('customer_snapshot'); $t->timestamps();
            $t->unique('transaction_id'); $t->unique('posting_id');
            $t->foreign('business_id')->references('id')->on('business');
            $t->foreign('transaction_id')->references('id')->on('transactions');
            $t->foreign('main_location_id')->references('id')->on('business_locations');
            $t->foreign('posting_id')->references('id')->on('inventory_postings');
        });
        Schema::create('b2b_sale_line_details', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('sell_line_id'); $t->string('product_name'); $t->string('sku');
            $t->string('unit_name'); $t->decimal('cost_total', 22, 4); $t->string('price_source', 16)->default('manual');
            $t->decimal('returned_total', 22, 4)->default(0); $t->decimal('returned_cost', 22, 4)->default(0);
            $t->unique('sell_line_id'); $t->foreign('sell_line_id')->references('id')->on('transaction_sell_lines');
        });
        Schema::create('b2b_returns', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('business_id'); $t->unsignedInteger('sale_transaction_id');
            $t->unsignedInteger('return_transaction_id'); $t->unsignedInteger('posting_id');
            $t->decimal('total', 22, 4); $t->decimal('cost_total', 22, 4); $t->text('reason'); $t->timestamps();
            $t->foreign('business_id')->references('id')->on('business');
            foreach (['sale_transaction_id', 'return_transaction_id'] as $c) { $t->foreign($c)->references('id')->on('transactions'); }
            $t->unique('return_transaction_id'); $t->unique('posting_id');
            $t->foreign('posting_id')->references('id')->on('inventory_postings');
        });
        foreach (['dashboard.view','products.manage','customers.manage','stock.transfer','pos.access','sales.create','credit.manage','payments.record','returns.process','reports.view'] as $ability) {
            DB::table('permissions')->updateOrInsert(['name' => 'b2b.'.$ability, 'guard_name' => 'web'], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (['b2b_transfers', 'b2b_sale_details', 'b2b_customer_profiles', 'b2b_product_settings', 'b2b_locations'] as $table) {
            if (DB::table($table)->exists()) { throw new RuntimeException('B2B records exist. Preserve history instead of rolling back.'); }
        }
        foreach (['b2b_returns', 'b2b_sale_line_details', 'b2b_sale_details', 'b2b_transfer_layers', 'b2b_transfers', 'b2b_customer_profiles', 'b2b_locations', 'b2b_product_settings'] as $table) { Schema::dropIfExists($table); }
        Schema::table('transactions', function (Blueprint $t) { $t->dropColumn('sales_channel'); });
    }
};
