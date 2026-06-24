<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'stock_owner_customer_id')) {
                $table->unsignedInteger('stock_owner_customer_id')->nullable()->after('created_by');
                $table->foreign('stock_owner_customer_id')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
                $table->index('stock_owner_customer_id');
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'stock_owner_customer_id')) {
                $table->dropForeign(['stock_owner_customer_id']);
                $table->dropIndex(['stock_owner_customer_id']);
                $table->dropColumn('stock_owner_customer_id');
            }
        });
    }
};
