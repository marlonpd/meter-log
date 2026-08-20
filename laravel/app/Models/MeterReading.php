<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $fillable = [
        'reading_date',
        'reading_value',
    ];

    protected $casts = [
        'reading_date' => 'date:Y-m-d',
        'reading_value' => 'decimal:2',
    ];
}
