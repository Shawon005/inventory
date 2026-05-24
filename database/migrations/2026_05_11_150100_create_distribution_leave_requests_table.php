<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedInteger('days_count')->default(1);
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->string('admin_note', 500)->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'customer_id', 'status'], 'dist_leave_biz_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_leave_requests');
    }
};
