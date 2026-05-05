<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'attendance_location')) {
                $table->string('attendance_location')->nullable()->after('overtime_pay_per_hour');
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'attendance_location')) {
                $table->dropColumn('attendance_location');
            }
        });
    }
};

