<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'employee_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('employee_type', 20)->default('sr')->after('business_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'employee_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['employee_type']);
                $table->dropColumn('employee_type');
            });
        }
    }
};
