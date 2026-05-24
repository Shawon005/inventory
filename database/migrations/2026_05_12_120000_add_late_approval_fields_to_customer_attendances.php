<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_attendances', 'late_approval_status')) {
                $table->string('late_approval_status', 20)->default('approved')->after('late_minutes');
            }

            if (! Schema::hasColumn('customer_attendances', 'late_approved_by')) {
                $table->unsignedInteger('late_approved_by')->nullable()->after('late_approval_status');
            }

            if (! Schema::hasColumn('customer_attendances', 'late_approved_at')) {
                $table->timestamp('late_approved_at')->nullable()->after('late_approved_by');
            }
        });

        DB::table('customer_attendances')
            ->whereNull('late_approval_status')
            ->update(['late_approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('customer_attendances', function (Blueprint $table) {
            foreach (['late_approved_at', 'late_approved_by', 'late_approval_status'] as $column) {
                if (Schema::hasColumn('customer_attendances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
