<?php

use App\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'assigned_sr_customer_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedInteger('assigned_sr_customer_id')->nullable()->after('employee_type');
                $table->unique('assigned_sr_customer_id');
                $table->foreign('assigned_sr_customer_id')
                    ->references('id')
                    ->on('customers')
                    ->nullOnDelete();
            });
        }

        DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NULL, MODIFY email VARCHAR(255) NULL, MODIFY password VARCHAR(255) NULL');

        $dsrUpdates = [
            'email' => null,
            'password' => null,
        ];
        if (Schema::hasColumn('customers', 'contact_id')) {
            $dsrUpdates['contact_id'] = null;
        }
        if (Schema::hasColumn('customers', 'stock_owner_customer_id')) {
            $dsrUpdates['stock_owner_customer_id'] = null;
        }

        DB::table('customers')
            ->where('employee_type', Customer::TYPE_DSR)
            ->update($dsrUpdates);

        if (Schema::hasTable('personal_access_tokens')) {
            $dsrIds = DB::table('customers')
                ->where('employee_type', Customer::TYPE_DSR)
                ->pluck('id');

            DB::table('personal_access_tokens')
                ->where('tokenable_type', Customer::class)
                ->whereIn('tokenable_id', $dsrIds)
                ->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'assigned_sr_customer_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropForeign(['assigned_sr_customer_id']);
                $table->dropUnique(['assigned_sr_customer_id']);
                $table->dropColumn('assigned_sr_customer_id');
            });
        }

        DB::table('customers')->whereNull('phone')->update(['phone' => '']);
        DB::table('customers')->whereNull('email')->orderBy('id')->get(['id'])->each(function ($customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'email' => 'employee-'.$customer->id.'@invalid.local',
            ]);
        });
        DB::table('customers')->whereNull('password')->update(['password' => '']);

        DB::statement('ALTER TABLE customers MODIFY phone VARCHAR(255) NOT NULL, MODIFY email VARCHAR(255) NOT NULL, MODIFY password VARCHAR(255) NOT NULL');
    }
};
