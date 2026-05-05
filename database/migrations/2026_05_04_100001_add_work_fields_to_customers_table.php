<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'work_latitude')) {
                $table->decimal('work_latitude', 10, 8)->nullable()->after('address');
            }
            if (! Schema::hasColumn('customers', 'work_longitude')) {
                $table->decimal('work_longitude', 11, 8)->nullable()->after('work_latitude');
            }
            if (! Schema::hasColumn('customers', 'allowed_radius_meters')) {
                $table->unsignedInteger('allowed_radius_meters')->default(100)->after('work_longitude');
            }
            if (! Schema::hasColumn('customers', 'shift_start_time')) {
                $table->time('shift_start_time')->nullable()->after('allowed_radius_meters');
            }
            if (! Schema::hasColumn('customers', 'shift_end_time')) {
                $table->time('shift_end_time')->nullable()->after('shift_start_time');
            }
            if (! Schema::hasColumn('customers', 'base_salary')) {
                $table->decimal('base_salary', 12, 2)->default(0)->after('shift_end_time');
            }
            if (! Schema::hasColumn('customers', 'late_deduction_per_hour')) {
                $table->decimal('late_deduction_per_hour', 12, 2)->default(0)->after('base_salary');
            }
            if (! Schema::hasColumn('customers', 'overtime_pay_per_hour')) {
                $table->decimal('overtime_pay_per_hour', 12, 2)->default(0)->after('late_deduction_per_hour');
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'work_latitude',
                'work_longitude',
                'allowed_radius_meters',
                'shift_start_time',
                'shift_end_time',
                'base_salary',
                'late_deduction_per_hour',
                'overtime_pay_per_hour',
            ]);
        });
    }
};
