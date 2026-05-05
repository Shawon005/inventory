<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('customer_id');
            $table->date('attendance_date');
            $table->enum('type', ['in', 'out']);
            $table->timestamp('attendance_time');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->boolean('is_within_radius')->default(false);
            $table->boolean('is_late')->default(false);
            $table->integer('late_minutes')->default(0);
            $table->string('image_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'attendance_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_attendances');
    }
};
