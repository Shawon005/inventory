<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Legacy inventory queries see main stock; B2B services explicitly query their internal locations. */
final class InventoryChannel
{
    public static function installed(): bool { return Schema::hasTable('b2b_locations'); }

    public static function mainLocations($query, $column): void
    {
        if (self::installed()) { $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, DB::table('b2b_locations')->select('stock_location_id'))); }
    }

    public static function retailTransactions($query, string $column = 'transactions.sales_channel'): void
    {
        if (self::installed()) {
            $from = $query instanceof \Illuminate\Database\Eloquent\Builder ? $query->getQuery()->from : $query->from;
            if ($column === 'transactions.sales_channel' && is_string($from) && preg_match('/^transactions\s+(?:as\s+)?([a-z][a-z0-9_]*)$/iD', $from, $matches)) { $column = $matches[1].'.sales_channel'; }
            $query->where($column, 'retail');
        }
    }

    public static function internal(int $locationId): bool
    {
        return self::installed() && DB::table('b2b_locations')->where('stock_location_id', $locationId)->exists();
    }

    public static function assertLocation(int $locationId, string $channel = 'main'): void
    {
        if (self::internal($locationId) !== ($channel === 'b2b')) {
            throw ValidationException::withMessages(['location_id' => 'Use the dedicated B2B workflow for B2B stock and the main workflow for main stock.']);
        }
    }
}
