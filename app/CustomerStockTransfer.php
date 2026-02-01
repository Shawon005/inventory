<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerStockTransfer extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $table = 'customer_stock_transfers';

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

    /**
     * Get the location (main inventory source).
     */
    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    /**
     * Get the admin who created the transfer.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
