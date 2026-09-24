<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'equipment_model_id',
        'quantity',
        'start_date',
        'end_date',
        'is_all_in',
        'rental_rate_snapshot',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_all_in' => 'boolean',
            'rental_rate_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class, 'equipment_model_id');
    }

    public function unitAssignments(): HasMany
    {
        return $this->hasMany(BookingUnitAssignment::class);
    }

    public function currentUnitAssignments(): HasMany
    {
        return $this->hasMany(BookingUnitAssignment::class)->where('is_current', true);
    }
}
