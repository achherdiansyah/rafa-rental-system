<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Delivery audit for outbound channels (WhatsApp). SKIPPED/FAILED/SENT
 * states are persisted so the behaviour is auditable and never faked.
 */
class NotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'event',
        'channel',
        'recipient_type',
        'recipient_id',
        'recipient_phone',
        'provider',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
