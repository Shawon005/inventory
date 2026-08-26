<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerHolidayTadaOverride extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'holiday_date' => 'date',
        'amount' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
