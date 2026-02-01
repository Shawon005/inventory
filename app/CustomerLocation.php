<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerLocation extends Model
{
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
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the customer.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
