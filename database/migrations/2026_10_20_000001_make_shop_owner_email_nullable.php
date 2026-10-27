<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Match the existing column length and preserve its unique index.
        DB::statement('ALTER TABLE shop_owners MODIFY COLUMN email VARCHAR(191) NULL');
    }

    public function down(): void
    {
        if (DB::table('shop_owners')->whereNull('email')->exists()) {
            throw new RuntimeException('Supply emails for all shop owners before rolling back optional emails.');
        }

        DB::statement('ALTER TABLE shop_owners MODIFY COLUMN email VARCHAR(191) NOT NULL');
    }
};
