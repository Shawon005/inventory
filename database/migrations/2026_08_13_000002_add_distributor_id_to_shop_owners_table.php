<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->unsignedInteger('distributor_id')->nullable()->after('id');
            $table->foreign('distributor_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
            $table->index(['distributor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->dropForeign(['distributor_id']);
            $table->dropIndex(['distributor_id', 'created_at']);
            $table->dropColumn('distributor_id');
        });
    }
};
