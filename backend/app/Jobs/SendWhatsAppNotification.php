<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Async WhatsApp delivery on the database queue. Logs SENT/FAILED for audit;
 * failures never bubble up (the system keeps running).
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WhatsAppMessage $message,
        private readonly int $recipientUserId,
        private readonly string $provider,
    ) {}

    public function viaConnection(): ?string
    {
        return 'database';
    }

    public function handle(WhatsAppGateway $gateway): void
    {
        try {
            $result = $gateway->send($this->message);

            NotificationDelivery::create([
                'event' => $this->message->event,
                'channel' => 'whatsapp',
                'recipient_type' => User::class,
                'recipient_id' => $this->recipientUserId,
                'recipient_phone' => $this->message->recipientPhone,
                'provider' => $this->provider,
                'status' => $result['sent'] ? 'SENT' : 'FAILED',
                'error' => $result['sent'] ? null : $result['error'],
                'sent_at' => $result['sent'] ? now() : null,
            ]);
        } catch (\Throwable $e) {
            NotificationDelivery::create([
                'event' => $this->message->event,
                'channel' => 'whatsapp',
                'recipient_type' => User::class,
                'recipient_id' => $this->recipientUserId,
                'recipient_phone' => $this->message->recipientPhone,
                'provider' => $this->provider,
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'sent_at' => null,
            ]);
        }
    }
}
