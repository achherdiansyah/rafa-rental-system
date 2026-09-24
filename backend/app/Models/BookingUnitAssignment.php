<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingUnitAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_detail_id',
        'equipment_unit_id',
        'status',
        'is_current',
        'assigned_by',
        'replaced_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'is_current' => 'boolean',
        ];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_detail_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EquipmentUnit::class, 'equipment_unit_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
