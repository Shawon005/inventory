<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_work_locations')) {
            Schema::create('customer_work_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('customer_id')->index();
                $table->string('weekday', 12);
                $table->string('location_name')->nullable();
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->unsignedInteger('allowed_radius_meters')->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['customer_id', 'weekday'], 'customer_weekday_unique');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_work_locations');
    }
};
