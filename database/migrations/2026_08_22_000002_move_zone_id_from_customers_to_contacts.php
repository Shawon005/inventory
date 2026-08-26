<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'zone_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->unsignedInteger('zone_id')->nullable()->after('business_id');
                $table->foreign('zone_id')->references('id')->on('zones')->onDelete('set null');
            });
        }

        $customerColumns = ['id', 'business_id', 'zone_id'];
        if (Schema::hasColumn('customers', 'contact_id')) {
            $customerColumns[] = 'contact_id';
        }
        if (Schema::hasColumn('customers', 'stock_owner_customer_id')) {
            $customerColumns[] = 'stock_owner_customer_id';
        }

        $customers = DB::table('customers')
            ->get($customerColumns)
            ->keyBy('id');

        foreach ($customers->whereNotNull('zone_id') as $customer) {
            $stockOwner = $this->resolveStockOwner($customer, $customers);
            if ($stockOwner && ! empty($stockOwner->contact_id)) {
                DB::table('contacts')
                    ->where('id', $stockOwner->contact_id)
                    ->where('business_id', $stockOwner->business_id)
                    ->whereNull('zone_id')
                    ->update(['zone_id' => $customer->zone_id]);
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('zone_id')->nullable()->after('business_id');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('set null');
        });

        $contactZones = DB::table('contacts')->whereNotNull('zone_id')->pluck('zone_id', 'id');
        $customerColumns = ['id', 'business_id'];
        if (Schema::hasColumn('customers', 'contact_id')) {
            $customerColumns[] = 'contact_id';
        }
        if (Schema::hasColumn('customers', 'stock_owner_customer_id')) {
            $customerColumns[] = 'stock_owner_customer_id';
        }

        $customers = DB::table('customers')
            ->get($customerColumns)
            ->keyBy('id');

        foreach ($customers as $customer) {
            $stockOwner = $this->resolveStockOwner($customer, $customers);
            $zoneId = $stockOwner && ! empty($stockOwner->contact_id)
                ? $contactZones->get($stockOwner->contact_id)
                : null;

            if ($zoneId) {
                DB::table('customers')->where('id', $customer->id)->update(['zone_id' => $zoneId]);
            }
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
        });
    }

    protected function resolveStockOwner($customer, $customers)
    {
        $visited = [];
        $current = $customer;

        while ($current && ! empty($current->stock_owner_customer_id) && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            $current = $customers->get($current->stock_owner_customer_id) ?: $current;

            if (isset($visited[$current->id])) {
                break;
            }
        }

        return $current;
    }
};
