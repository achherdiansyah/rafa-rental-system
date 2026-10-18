<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPaymentHold extends Model
{
    use HasFactory;

    public const ACTIVE = 'ACTIVE';

    public const RESOLVED = 'RESOLVED';

    public const REASON_INVOICE_COUNT = 'INVOICE_COUNT_3';

    public const REASON_INVOICE_AGE = 'OLDEST_INVOICE_72H';

    protected $fillable = [
        'rental_id',
        'status',
        'reason_code',
        'trigger_invoice_id',
        'outstanding_amount',
        'started_at',
        'resolved_at',
        'created_by',
        'resolved_by',
        'resolution_reason',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_amount' => 'decimal:2',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function triggerInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'trigger_invoice_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
