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
        'work_latitude' => 'float',
        'work_longitude' => 'float',
        'tada_bill_amount' => 'float',
        'monthly_target_amount' => 'float',
        'commission_percent' => 'float',
    ];

    /**
     * Get the business that owns the customer.
     */

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }
    
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

    public function stockOwner()
    {
        return $this->belongsTo(Customer::class, 'stock_owner_customer_id');
    }

    public function distributorCustomers()
    {
        return $this->hasMany(Customer::class, 'stock_owner_customer_id');
    }

    public function getResolvedStockOwnerIdAttribute(): int
    {
        $visited = [$this->id => true];
        $current = $this;

        while (! empty($current->stock_owner_customer_id)) {
            $next = self::find($current->stock_owner_customer_id);

            if (! $next || isset($visited[$next->id])) {
                break;
            }

            $visited[$next->id] = true;
            $current = $next;
        }

        return (int) $current->id;
    }

    public function resolvedStockOwner()
    {
        return self::find($this->resolved_stock_owner_id);
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

    public function attendances()
    {
        return $this->hasMany(CustomerAttendance::class);
    }

    public function workLocations()
    {
        return $this->hasMany(CustomerWorkLocation::class);
    }

    /**
     * Scope for active customers only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
