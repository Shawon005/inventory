<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VariationLocationDetails extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('main_inventory', fn ($q) => \App\Support\InventoryChannel::mainLocations($q, 'variation_location_details.location_id'));
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
