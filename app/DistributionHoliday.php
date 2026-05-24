<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DistributionHoliday extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
