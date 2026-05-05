<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerAttendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'attendance_date' => 'date',
        'attendance_time' => 'datetime',
        'is_within_radius' => 'boolean',
        'is_late' => 'boolean',
        'meta' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
