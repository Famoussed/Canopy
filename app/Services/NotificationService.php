<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Notification\MarkAsReadAction;
use App\Actions\Notification\SendNotificationAction;
use App\Models\User;

class NotificationService
{
    public function __construct(
        private readonly SendNotificationAction $sendAction,
        private readonly MarkAsReadAction $markAsReadAction,
    ) {}

    public function send(User $user, string $type, array $data): void
    {
        $this->sendAction->execute($user, $type, $data);
    }

    public function markAsRead(string $notificationId, User $user): void
    {
        $this->markAsReadAction->execute($notificationId, $user);
    }

    public function markAllAsRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }
}
