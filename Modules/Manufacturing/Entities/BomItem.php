<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    public $timestamps = false;
    protected $table = 'mfg_bom_items';
    protected $guarded = ['*'];
    protected $casts = ['quantity' => 'decimal:4', 'base_quantity' => 'decimal:4', 'unit_multiplier' => 'decimal:8', 'wastage_percent' => 'decimal:4'];
    public function variation() { return $this->belongsTo(\App\Variation::class); }
}
