<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryMovement extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];
    protected $casts = ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4', 'created_at' => 'datetime'];

    protected static function booted()
    {
        static::updating(function () { throw new LogicException('Stock movements are immutable; post a reversal.'); });
        static::deleting(function () { throw new LogicException('Stock movements are immutable; post a reversal.'); });
    }
}
