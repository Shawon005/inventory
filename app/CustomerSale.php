<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerSale extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the customer.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variation.
     */
    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }
}
