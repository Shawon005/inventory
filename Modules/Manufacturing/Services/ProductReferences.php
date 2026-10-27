<?php

namespace Modules\Manufacturing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductReferences
{
    public function variationIsReferenced($ids): bool
    {
        if (Schema::hasTable('b2b_product_settings') && DB::table('variations as v')->join('b2b_product_settings as b', 'b.product_id', '=', 'v.product_id')->whereIn('v.id', $ids)->exists()) { return true; }
        if (! Schema::hasTable('mfg_boms')) { return false; }
        return DB::table('mfg_boms')->whereIn('variation_id', $ids)->exists() ||
            DB::table('mfg_bom_items')->whereIn('variation_id', $ids)->exists();
    }
}
