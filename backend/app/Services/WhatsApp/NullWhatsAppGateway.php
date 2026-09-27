<?php

namespace App\Services\WhatsApp;

/**
 * Default gateway when no WhatsApp provider is configured. It reports the
 * delivery as NOT sent — never a fake success — so the system can keep
 * running and log an honest SKIPPED entry for audit.
 */
class NullWhatsAppGateway implements WhatsAppGateway
{
    public function name(): string
    {
        return 'none';
    }

    /**
     * @return array{sent: bool, error: string|null}
     */
    public function send(WhatsAppMessage $message): array
    {
        return [
            'sent' => false,
            'error' => 'WhatsApp provider belum dikonfigurasi (WHATSAPP_PROVIDER).',
        ];
    }
}
