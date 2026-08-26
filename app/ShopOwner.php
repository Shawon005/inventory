<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOwner extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function phones()
    {
        return $this->hasMany(ShopOwnerPhone::class);
    }

    public function distributor()
    {
        return $this->belongsTo(Customer::class, 'distributor_id');
    }

    public function sales()
    {
        return $this->hasMany(CustomerSale::class);
    }

    public function visits()
    {
        return $this->hasMany(CustomerShopVisit::class);
    }
}
