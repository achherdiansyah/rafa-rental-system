<?php

namespace App\Models;

use App\Enums\TimesheetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_detail_id',
        'report_date',
        'start_hm',
        'end_hm',
        'total_work_hours',
        'standby_hours',
        'breakdown_hours',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'start_hm' => 'decimal:2',
            'end_hm' => 'decimal:2',
            'total_work_hours' => 'decimal:2',
            'standby_hours' => 'decimal:2',
            'breakdown_hours' => 'decimal:2',
            'status' => TimesheetStatus::class,
        ];
    }

    public function rentalDetail(): BelongsTo
    {
        return $this->belongsTo(RentalDetail::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TimesheetRevision::class);
    }
}
