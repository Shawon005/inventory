<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'monthly_target_amount')) {
                $table->decimal('monthly_target_amount', 12, 2)->default(0)->after('tada_bill_amount');
            }
            if (! Schema::hasColumn('customers', 'commission_percent')) {
                $table->decimal('commission_percent', 5, 2)->default(0)->after('monthly_target_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'commission_percent')) {
                $table->dropColumn('commission_percent');
            }
            if (Schema::hasColumn('customers', 'monthly_target_amount')) {
                $table->dropColumn('monthly_target_amount');
            }
        });
    }
};

