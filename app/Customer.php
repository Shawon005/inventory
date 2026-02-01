<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the business that owns the customer.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who created the customer.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get customer stocks.
     */
    public function stocks()
    {
        return $this->hasMany(CustomerStock::class);
    }

    /**
     * Get stock transfers to this customer.
     */
    public function stockTransfers()
    {
        return $this->hasMany(CustomerStockTransfer::class);
    }

    /**
     * Get customer sales.
     */
    public function sales()
    {
        return $this->hasMany(CustomerSale::class);
    }

    /**
     * Get customer location history.
     */
    public function locations()
    {
        return $this->hasMany(CustomerLocation::class);
    }

    /**
     * Scope for active customers only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
