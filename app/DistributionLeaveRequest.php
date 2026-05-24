<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DistributionLeaveRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
