<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_shop_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('shop_owner_id')->nullable();
            $table->string('shop_name');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('shop_latitude', 10, 8);
            $table->decimal('shop_longitude', 11, 8);
            $table->decimal('distance_meters', 8, 2);
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('shop_owner_id')->references('id')->on('shop_owners')->onDelete('set null');
            $table->index(['customer_id', 'visited_at']);
            $table->index(['shop_owner_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_shop_visits');
    }
};
