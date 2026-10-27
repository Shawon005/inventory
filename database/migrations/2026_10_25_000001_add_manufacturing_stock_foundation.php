<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Run before DDL: never silently merge balances or alter historical stock.
        if (DB::table('variation_location_details')->select('variation_id', 'location_id')
            ->groupBy('variation_id', 'location_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate variation/location stock rows: reconcile them before installing manufacturing.');
        }
        Schema::table('products', function (Blueprint $table) {
            $table->string('manufacturing_type', 24)->default('normal')->index();
        });
        Schema::table('variation_location_details', function (Blueprint $table) {
            $table->unique(['variation_id', 'location_id'], 'stock_variation_location_unique');
        });
        Schema::create('inventory_postings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('operation', 48);
            $table->string('idempotency_key', 80);
            $table->char('payload_hash', 64);
            $table->json('request_payload');
            $table->json('result')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['business_id', 'operation', 'idempotency_key'], 'inventory_posting_key_unique');
            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('created_by')->references('id')->on('users');
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->increments('id');
            foreach (['business_id', 'location_id', 'product_id', 'variation_id', 'posting_id', 'created_by'] as $column) {
                $table->unsignedInteger($column);
            }
            $table->string('movement_type', 48);
            $table->string('reference_type', 48);
            $table->unsignedInteger('reference_id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('reversal_of')->nullable()->unique();
            $table->decimal('quantity', 22, 4);
            $table->decimal('unit_cost', 22, 4);
            $table->decimal('total_cost', 22, 4);
            $table->timestamp('created_at');
            $table->foreign('business_id')->references('id')->on('business');
            $table->foreign('location_id')->references('id')->on('business_locations');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('variations');
            $table->foreign('posting_id')->references('id')->on('inventory_postings');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('reversal_of')->references('id')->on('inventory_movements');
            $table->index(['business_id', 'location_id', 'created_at'], 'inventory_movement_location_date');
            $table->index(['business_id', 'reference_type', 'reference_id'], 'inventory_movement_reference');
        });
    }

    public function down()
    {
        if (DB::table('inventory_movements')->exists() || DB::table('inventory_postings')->exists() || DB::table('products')->where('manufacturing_type', '!=', 'normal')->exists()) {
            throw new RuntimeException('Manufacturing postings exist; rollbacks must not erase inventory history.');
        }
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_postings');
        Schema::table('variation_location_details', fn (Blueprint $table) => $table->dropUnique('stock_variation_location_unique'));
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex('products_manufacturing_type_index'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('manufacturing_type'));
    }
};
