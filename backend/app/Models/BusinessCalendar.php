<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_date',
        'is_working_day',
        'holiday_name',
    ];

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
            'is_working_day' => 'boolean',
        ];
    }
}
