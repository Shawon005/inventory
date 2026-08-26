<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customer_sales', function (Blueprint $table) {
            $table->decimal('return_amount', 22, 4)->default(0)->after('status');
            $table->text('return_reason')->nullable()->after('return_amount');
            $table->timestamp('returned_at')->nullable()->after('return_reason');
        });
    }

    public function down()
    {
        Schema::table('customer_sales', function (Blueprint $table) {
            $table->dropColumn(['return_amount', 'return_reason', 'returned_at']);
        });
    }
};
