<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    protected $table = 'mfg_boms';
    protected $guarded = ['id', 'business_id'];
    protected $casts = ['is_active' => 'boolean'];
    public function versions() { return $this->hasMany(BomVersion::class, 'bom_id'); }
    public function variation() { return $this->belongsTo(\App\Variation::class); }
}
