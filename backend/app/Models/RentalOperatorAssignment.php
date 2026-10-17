<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalOperatorAssignment extends Model
{
    use HasFactory;

    public const ACTIVE = 'ACTIVE';

    public const ENDED = 'ENDED';

    protected $fillable = [
        'rental_id',
        'rental_detail_id',
        'operator_user_id',
        'assigned_by',
        'started_at',
        'ended_at',
        'status',
        'end_reason',
        'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function rentalDetail(): BelongsTo
    {
        return $this->belongsTo(RentalDetail::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}