<?php

namespace App\Models;

use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'source',
        'amount',
        'reason',
        'approval_reason',
        'approved_by',
        'approved_at',
        'customer_bank_info',
        'transfer_reference',
        'status',
        'failure_reason',
        'processed_by',
        'processed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => RefundSource::class,
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function proof(): ?Attachment
    {
        /** @var Attachment|null $proof */
        $proof = $this->attachments
            ->first(fn ($a) => $a->document_type === 'REFUND_PROOF');

        return $proof;
    }
}
