<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'is_dealer')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->boolean('is_dealer')->default(false)->after('type')->index();
            });
        }

        // Preserve distribution behaviour for contacts already tied to a
        // territory or an SR account.
        if (Schema::hasColumn('contacts', 'zone_id')) {
            DB::table('contacts')
                ->whereIn('type', ['customer', 'both'])
                ->whereNotNull('zone_id')
                ->update(['is_dealer' => true]);
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'contact_id')) {
            DB::table('contacts')
                ->whereIn('id', DB::table('customers')->whereNotNull('contact_id')->select('contact_id'))
                ->update(['is_dealer' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'is_dealer')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex(['is_dealer']);
                $table->dropColumn('is_dealer');
            });
        }
    }
};
