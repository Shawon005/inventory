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
            if (! Schema::hasColumn('customer_attendances', 'in_time')) {
                $table->timestamp('in_time')->nullable()->after('attendance_date');
            }
            if (! Schema::hasColumn('customer_attendances', 'out_time')) {
                $table->timestamp('out_time')->nullable()->after('in_time');
            }
        });

        $rows = DB::table('customer_attendances')
            ->select('customer_id', 'attendance_date')
            ->groupBy('customer_id', 'attendance_date')
            ->get();

        foreach ($rows as $row) {
            $entries = DB::table('customer_attendances')
                ->where('customer_id', $row->customer_id)
                ->whereDate('attendance_date', $row->attendance_date)
                ->orderBy('attendance_time')
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $in = $entries->firstWhere('type', 'in') ?? $entries->first();
            $out = $entries->where('type', 'out')->last();

            $keeper = $in;

            DB::table('customer_attendances')
                ->where('id', $keeper->id)
                ->update([
                    'in_time' => $in?->attendance_time,
                    'out_time' => $out?->attendance_time,
                    'attendance_time' => $in?->attendance_time ?? $keeper->attendance_time,
                    'type' => 'in',
                ]);

            DB::table('customer_attendances')
                ->where('customer_id', $row->customer_id)
                ->whereDate('attendance_date', $row->attendance_date)
                ->where('id', '!=', $keeper->id)
                ->delete();
        }

        Schema::table('customer_attendances', function (Blueprint $table) {
            try {
                $table->unique(['customer_id', 'attendance_date'], 'customer_attendance_unique_day');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_attendances', function (Blueprint $table) {
            try {
                $table->dropUnique('customer_attendance_unique_day');
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('customer_attendances', 'in_time')) {
                $table->dropColumn('in_time');
            }
            if (Schema::hasColumn('customer_attendances', 'out_time')) {
                $table->dropColumn('out_time');
            }
        });
    }
};
