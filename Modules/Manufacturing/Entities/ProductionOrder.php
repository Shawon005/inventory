<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $table = 'mfg_production_orders';
    protected $guarded = ['*'];
    protected $casts = ['bom_snapshot' => 'array', 'planned_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'defective_quantity' => 'decimal:4', 'expected_cost' => 'decimal:4', 'actual_cost' => 'decimal:4'];
    public function batches() { return $this->hasMany(ProductionBatch::class, 'order_id'); }
    public function bom_version() { return $this->belongsTo(BomVersion::class); }
    public function variation() { return $this->belongsTo(\App\Variation::class); }
}
