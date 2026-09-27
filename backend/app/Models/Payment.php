<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'bank_account_id',
        'payment_date',
        'sender_name',
        'reference',
        'amount',
        'status',
        'rejection_reason',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function proof(): ?Attachment
    {
        /** @var Attachment|null $proof */
        $proof = $this->attachments
            ->first(fn ($a) => $a->document_type === 'PAYMENT_PROOF');

        return $proof;
    }
}
