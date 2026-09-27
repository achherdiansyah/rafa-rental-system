<?php

namespace App\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Database-channel (in-app) notification. Uses the default DB notification
 * table; no Redis dependency. Related entity is recorded in the payload via
 * class name + id for later resolution.
 */
class SystemNotification extends Notification
{
    public function __construct(
        private readonly string $event,
        private readonly ?Model $entity,
        private readonly string $message,
        private readonly ?string $link = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'entity_type' => $this->entity ? get_class($this->entity) : null,
            'entity_id' => $this->entity?->getKey(),
            'message' => $this->message,
            'link' => $this->link,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
