<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerAttendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'attendance_time' => 'datetime',
        'in_time' => 'datetime',
        'out_time' => 'datetime',
        'late_approved_at' => 'datetime',
        'tada_bill_amount' => 'float',
        'is_within_radius' => 'boolean',
        'is_late' => 'boolean',
        'meta' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeCountable($query)
    {
        return $query->whereNotNull('in_time')
            ->where('late_approval_status', 'approved');
    }
}
