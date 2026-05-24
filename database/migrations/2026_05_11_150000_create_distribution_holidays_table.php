<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('note')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'start_date', 'end_date'], 'dist_holidays_biz_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_holidays');
    }
};
