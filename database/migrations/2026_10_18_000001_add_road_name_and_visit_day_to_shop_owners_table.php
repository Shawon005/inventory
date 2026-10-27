<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->string('road_name', 255)->nullable();
            $table->string('visit_day', 9)->nullable();
            $table->index('road_name');
            $table->index('visit_day');
        });
    }

    public function down(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->dropIndex(['road_name']);
            $table->dropIndex(['visit_day']);
            $table->dropColumn(['road_name', 'visit_day']);
        });
    }
};
