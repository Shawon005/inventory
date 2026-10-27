<?php

namespace Modules\Manufacturing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightCommand extends Command
{
    protected $signature = 'manufacturing:preflight {--json : Print machine-readable results}';
    protected $description = 'Read-only manufacturing schema, valuation and stock-consistency checks';

    public function handle(): int
    {
        $errors = []; $warnings = [];
        if (! extension_loaded('bcmath')) { $errors[] = 'PHP BCMath is required for decimal-safe postings.'; }
        foreach (['business', 'business_locations', 'users', 'products', 'variations', 'units', 'variation_location_details', 'purchase_lines', 'transactions', 'transaction_sell_lines', 'transaction_sell_lines_purchase_lines', 'stock_adjustment_lines', 'permissions', 'migrations'] as $table) {
            try { DB::table($table)->limit(1)->get(); }
            catch (\Throwable $e) { $errors[] = 'Cannot read required table '.$table.' (database code '.$e->getCode().').'; }
        }
        if (! $errors) {
            if (DB::table('variation_location_details')->select('variation_id', 'location_id')->groupBy('variation_id', 'location_id')->havingRaw('COUNT(*) > 1')->exists()) {
                $errors[] = 'Duplicate variation/location balances exist. Installation must stop for reconciliation.';
            }
            $methods = DB::table('business')->distinct()->pluck('accounting_method')->all();
            foreach ($methods as $method) { if (! in_array($method, ['fifo', 'lifo'], true)) { $errors[] = 'Unsupported configured manufacturing valuation method: '.$method; } }
            $invalid = DB::table('variation_location_details as s')->leftJoin('variations as v', 'v.id', '=', 's.variation_id')->leftJoin('products as p', 'p.id', '=', 's.product_id')->leftJoin('business_locations as l', 'l.id', '=', 's.location_id')
                ->where(fn ($q) => $q->whereNull('v.id')->orWhereNull('p.id')->orWhereNull('l.id')->orWhereColumn('v.product_id', '!=', 's.product_id')->orWhereColumn('p.business_id', '!=', 'l.business_id'))->exists();
            if ($invalid) { $errors[] = 'Stock rows contain orphaned or cross-business product/location relationships.'; }
            if (! Schema::hasTable('inventory_postings') || ! Schema::hasTable('mfg_production_orders')) { $warnings[] = 'Manufacturing migrations have not yet been applied.'; }
            $warnings[] = 'No stock or historical transactions were changed. Use a verified backup and disposable migration rehearsal before live installation.';
        }
        $result = ['ready' => ! $errors, 'errors' => $errors, 'warnings' => $warnings];
        if ($this->option('json')) { $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); }
        else {
            foreach ($errors as $error) { $this->error($error); }
            foreach ($warnings as $warning) { $this->warn($warning); }
            if (! $errors) { $this->info('Core preflight passed.'); }
        }
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
