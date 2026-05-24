<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerWorkLocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'allowed_radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
