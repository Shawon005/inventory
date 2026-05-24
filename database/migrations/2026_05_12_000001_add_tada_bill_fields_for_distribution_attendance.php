<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'tada_bill_amount')) {
                $table->decimal('tada_bill_amount', 12, 2)->default(0)->after('attendance_location');
            }
        });

        Schema::table('customer_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_attendances', 'tada_bill_amount')) {
                $table->decimal('tada_bill_amount', 12, 2)->nullable()->after('late_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_attendances', function (Blueprint $table) {
            if (Schema::hasColumn('customer_attendances', 'tada_bill_amount')) {
                $table->dropColumn('tada_bill_amount');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'tada_bill_amount')) {
                $table->dropColumn('tada_bill_amount');
            }
        });
    }
};

