<?php

namespace App\Services\WhatsApp;

final readonly class WhatsAppMessage
{
    public function __construct(
        public string $recipientPhone,
        public string $message,
        public string $event,
    ) {}
}
