<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_sales', 'shop_owner_id')) {
            Schema::table('customer_sales', function (Blueprint $table) {
                $table->unsignedInteger('shop_owner_id')->nullable()->after('customer_id');
                $table->foreign('shop_owner_id')
                    ->references('id')
                    ->on('shop_owners')
                    ->onDelete('set null');
                $table->index(['shop_owner_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_sales', 'shop_owner_id')) {
            Schema::table('customer_sales', function (Blueprint $table) {
                $table->dropForeign(['shop_owner_id']);
                $table->dropIndex(['shop_owner_id', 'created_at']);
                $table->dropColumn('shop_owner_id');
            });
        }
    }
};
