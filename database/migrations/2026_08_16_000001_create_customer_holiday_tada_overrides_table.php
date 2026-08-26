<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_holiday_tada_overrides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('customer_id');
            $table->date('holiday_date');
            $table->enum('status', ['paid', 'missed']);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('adjusted_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('adjusted_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['customer_id', 'holiday_date'], 'customer_friday_tada_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_holiday_tada_overrides');
    }
};
