<?php

namespace App\Services\WhatsApp;

/**
 * Pluggable WhatsApp provider seam. Business events do not know the provider;
 * they only ask the notifier to deliver. A provider is selected by config
 * (services.whatsapp.provider). Return shape: ['sent' => bool, 'error' => ?string].
 */
interface WhatsAppGateway
{
    public function name(): string;

    /**
     * @return array{sent: bool, error: string|null}
     */
    public function send(WhatsAppMessage $message): array;
}
