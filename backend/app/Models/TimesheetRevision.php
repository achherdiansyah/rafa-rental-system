<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'timesheet_id',
        'version',
        'old_start_hm',
        'old_end_hm',
        'revision_reason',
        'revised_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'old_start_hm' => 'decimal:2',
            'old_end_hm' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function revisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
