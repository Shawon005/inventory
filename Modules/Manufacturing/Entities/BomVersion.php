<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;

class BomVersion extends Model
{
    protected $table = 'mfg_bom_versions';
    protected $guarded = ['*'];
    protected $casts = ['is_active' => 'boolean', 'output_quantity' => 'decimal:4'];
    public function bom() { return $this->belongsTo(Bom::class); }
    public function items() { return $this->hasMany(BomItem::class, 'bom_version_id'); }
}
