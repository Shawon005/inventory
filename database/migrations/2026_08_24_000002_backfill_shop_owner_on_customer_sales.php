<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_sales', 'shop_owner_id')) {
            return;
        }

        $hasResalerPhone = Schema::hasColumn('customer_sales', 'resaler_phone');
        $owners = DB::table('shop_owners')->whereNotNull('distributor_id')->orderBy('id')->get();

        foreach ($owners as $owner) {
            if ($hasResalerPhone) {
                $phones = DB::table('shop_owner_phones')
                    ->where('shop_owner_id', $owner->id)
                    ->pluck('phone')
                    ->filter()
                    ->values();

                if ($phones->isNotEmpty()) {
                    DB::table('customer_sales')
                        ->where('customer_id', $owner->distributor_id)
                        ->whereNull('shop_owner_id')
                        ->whereIn('resaler_phone', $phones)
                        ->update(['shop_owner_id' => $owner->id]);
                }
            }

            $sameNameOwnerCount = DB::table('shop_owners')
                ->where('distributor_id', $owner->distributor_id)
                ->where('name', $owner->name)
                ->count();

            if ($sameNameOwnerCount === 1) {
                DB::table('customer_sales')
                    ->where('customer_id', $owner->distributor_id)
                    ->whereNull('shop_owner_id')
                    ->where('sold_to', $owner->name)
                    ->update(['shop_owner_id' => $owner->id]);
            }
        }
    }

    public function down(): void
    {
        // Historical links are intentionally preserved because subsequent app sales
        // use the same column and cannot be safely distinguished from backfilled rows.
    }
};
