<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customer_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_sales', 'items')) {
                $table->json('items')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('customer_sales', 'discount')) {
                $table->decimal('discount', 22, 4)->default(0)->after('sale_price');
            }

            if (! Schema::hasColumn('customer_sales', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('discount');
            }

            if (! Schema::hasColumn('customer_sales', 'status')) {
                $table->string('status')->default('ordered')->after('payment_method');
                $table->index('status');
            }
        });
    }

    public function down()
    {
        Schema::table('customer_sales', function (Blueprint $table) {
            if (Schema::hasColumn('customer_sales', 'items')) {
                $table->dropColumn('items');
            }
            if (Schema::hasColumn('customer_sales', 'discount')) {
                $table->dropColumn('discount');
            }
            if (Schema::hasColumn('customer_sales', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('customer_sales', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
