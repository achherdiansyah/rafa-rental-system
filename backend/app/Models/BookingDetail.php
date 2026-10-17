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
        'overtime_rate_snapshot',
        'mob_cost_snapshot',
        'demob_cost_snapshot',
        'subtotal',
        'origin_pool_id',
        'origin_name',
        'origin_address',
        'origin_latitude',
        'origin_longitude',
        'destination_project_location_id',
        'destination_name',
        'destination_address',
        'destination_latitude',
        'destination_longitude',
        'route_distance_km',
        'distance_rate_per_km',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_all_in' => 'boolean',
            'rental_rate_snapshot' => 'decimal:2',
            'overtime_rate_snapshot' => 'decimal:2',
            'mob_cost_snapshot' => 'decimal:2',
            'demob_cost_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'origin_latitude' => 'decimal:8',
            'origin_longitude' => 'decimal:8',
            'destination_latitude' => 'decimal:8',
            'destination_longitude' => 'decimal:8',
            'route_distance_km' => 'decimal:2',
            'distance_rate_per_km' => 'decimal:2',
        ];
    }

    public function originPool(): BelongsTo
    {
        return $this->belongsTo(EquipmentPool::class, 'origin_pool_id');
    }

    public function destinationProjectLocation(): BelongsTo
    {
        return $this->belongsTo(ProjectLocation::class, 'destination_project_location_id');
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
