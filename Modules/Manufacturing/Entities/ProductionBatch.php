<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    protected $table = 'mfg_production_batches';
    protected $guarded = ['*'];
    protected $casts = ['planned_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'defective_quantity' => 'decimal:4', 'material_cost' => 'decimal:4', 'total_cost' => 'decimal:4', 'unit_cost' => 'decimal:4', 'expense_cost' => 'decimal:4', 'valuation_rounding' => 'decimal:4'];
    public function order() { return $this->belongsTo(ProductionOrder::class, 'order_id'); }
}
