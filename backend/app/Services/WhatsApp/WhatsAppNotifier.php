<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Orchestrates WhatsApp delivery for business events while keeping the
 * business layer decoupled from any concrete provider.
 *
 * - Provider comes from config (services.whatsapp.provider); unset → Null
 *   gateway which honestly logs SKIPPED (never a fake success).
 * - Delivery result/error is persisted for audit.
 * - Async (DB queue) when services.whatsapp.queue is true; sync otherwise.
 * - Any failure is contained: the system keeps running normally.
 */
class WhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppGateway $gateway
    ) {}

    public function notify(User $recipient, string $event, string $message, ?Model $entity = null): NotificationDelivery
    {
        $phone = $recipient->phone_number;
        $provider = $this->providerName();

        if (! $phone) {
            return NotificationDelivery::create([
                'event' => $event,
                'channel' => 'whatsapp',
                'recipient_type' => User::class,
                'recipient_id' => $recipient->id,
                'recipient_phone' => null,
                'status' => 'SKIPPED',
                'error' => 'Nomor WhatsApp penerima tidak tersedia.',
            ]);
        }

        if ($provider === 'none') {
            return NotificationDelivery::create([
                'event' => $event,
                'channel' => 'whatsapp',
                'recipient_type' => User::class,
                'recipient_id' => $recipient->id,
                'recipient_phone' => $phone,
                'provider' => null,
                'status' => 'SKIPPED',
                'error' => 'WhatsApp provider belum dikonfigurasi; pengiriman dilewati.',
            ]);
        }

        $payload = new WhatsAppMessage($phone, $message, $event);

        if (config('services.whatsapp.queue', false)) {
            Queue::connection('database')->push(
                new SendWhatsAppNotification($payload, (int) $recipient->id, $provider)
            );

            return NotificationDelivery::create([
                'event' => $event,
                'channel' => 'whatsapp',
                'recipient_type' => User::class,
                'recipient_id' => $recipient->id,
                'recipient_phone' => $phone,
                'provider' => $provider,
                'status' => 'QUEUED',
            ]);
        }

        // Synchronous delivery with honest result persisting.
        try {
            $result = $this->gateway->send($payload);
        } catch (\Throwable $e) {
            $result = ['sent' => false, 'error' => $e->getMessage()];
        }

        return NotificationDelivery::create([
            'event' => $event,
            'channel' => 'whatsapp',
            'recipient_type' => User::class,
            'recipient_id' => $recipient->id,
            'recipient_phone' => $phone,
            'provider' => $provider,
            'status' => $result['sent'] ? 'SENT' : 'FAILED',
            'error' => $result['sent'] ? null : $result['error'],
            'sent_at' => $result['sent'] ? now() : null,
        ]);
    }

    private function providerName(): string
    {
        return $this->gateway->name() ?: 'none';
    }
}
