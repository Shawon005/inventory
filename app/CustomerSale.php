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

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public function getOrderAmountAttribute(): float
    {
        return (float) $this->sale_price;
    }

    public function getDeliveryAmountAttribute(): float
    {
        $status = strtolower((string) $this->status);

        return ($status === 'delivered' || $status === '')
            ? $this->order_amount
            : 0.0;
    }

    public function getTotalAmountAttribute(): float
    {
        return max(0, $this->order_amount - (float) ($this->return_amount ?? 0));
    }

    public function getReturnStatusAttribute(): string
    {
        $returnAmount = (float) ($this->return_amount ?? 0);

        if ($returnAmount <= 0) {
            return 'none';
        }

        return $returnAmount >= $this->order_amount ? 'full' : 'partial';
    }

    /**
     * Get the customer.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
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
