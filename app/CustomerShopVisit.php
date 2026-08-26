<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerShopVisit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'shop_latitude' => 'float',
        'shop_longitude' => 'float',
        'distance_meters' => 'float',
        'visit_date' => 'date:Y-m-d',
        'visited_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }
}
