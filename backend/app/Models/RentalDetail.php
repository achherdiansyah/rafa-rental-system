<?php

namespace App\Models;

use App\Enums\RentalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'assignment_id',
        'check_in_hm',
        'check_out_hm',
        'condition_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalStatus::class,
            'check_in_hm' => 'decimal:2',
            'check_out_hm' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(BookingUnitAssignment::class, 'assignment_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }
}
