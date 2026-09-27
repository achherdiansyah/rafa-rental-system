<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Thin wrapper over the Laravel notification system (database channel only).
 * Every in-app notification carries an event name, the related entity, and a
 * message. Recipients are explicit — callers decide who may see what.
 */
class NotificationService
{
    public function send(
        User|iterable|null $recipients,
        string $event,
        ?Model $entity = null,
        string $message = '',
        ?string $link = null
    ): void {
        $targets = $recipients === null
            ? []
            : (is_iterable($recipients) ? $recipients : [$recipients]);

        foreach ($targets as $recipient) {
            if ($recipient) {
                $recipient->notify(new SystemNotification($event, $entity, $message, $link));
            }
        }
    }

    public function sendToRole(string $role, string $event, ?Model $entity = null, string $message = '', ?string $link = null): void
    {
        $this->send(
            User::where('role', $role)->get(),
            $event,
            $entity,
            $message,
            $link
        );
    }

    public function sendToAdmins(string $event, ?Model $entity = null, string $message = '', ?string $link = null): void
    {
        $this->sendToRole(UserRole::ADMIN->value, $event, $entity, $message, $link);
    }

    public function sendToOwners(string $event, ?Model $entity = null, string $message = '', ?string $link = null): void
    {
        $this->sendToRole(UserRole::OWNER->value, $event, $entity, $message, $link);
    }

    /**
     * @param  Collection<int, User>|User[]  $users
     */
    public function toCollection(Collection|array $users, string $event, ?Model $entity = null, string $message = '', ?string $link = null): void
    {
        foreach ($users as $user) {
            if ($user) {
                $user->notify(new SystemNotification($event, $entity, $message, $link));
            }
        }
    }
}
