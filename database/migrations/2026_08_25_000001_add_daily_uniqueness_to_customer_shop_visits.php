<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_shop_visits', function (Blueprint $table) {
            $table->date('visit_date')->nullable()->after('shop_owner_id');
        });

        // Preserve existing rows while marking only the first visit to each shop
        // on each day as countable. Older duplicate rows remain available for audit.
        $seen = [];
        DB::table('customer_shop_visits')
            ->whereNotNull('shop_owner_id')
            ->orderBy('id')
            ->chunkById(500, function ($visits) use (&$seen) {
                foreach ($visits as $visit) {
                    $visitDate = substr((string) $visit->visited_at, 0, 10);
                    $key = $visit->customer_id.'|'.$visit->shop_owner_id.'|'.$visitDate;

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    DB::table('customer_shop_visits')
                        ->where('id', $visit->id)
                        ->update(['visit_date' => $visitDate]);
                }
            });

        Schema::table('customer_shop_visits', function (Blueprint $table) {
            $table->unique(
                ['customer_id', 'shop_owner_id', 'visit_date'],
                'customer_shop_visits_daily_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_shop_visits', function (Blueprint $table) {
            $table->dropUnique('customer_shop_visits_daily_unique');
            $table->dropColumn('visit_date');
        });
    }
};
