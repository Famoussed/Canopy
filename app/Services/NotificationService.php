<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Notification\MarkAsReadAction;
use App\Actions\Notification\SendNotificationAction;
use App\Events\Notification\NotificationSent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        private readonly SendNotificationAction $sendAction,
        private readonly MarkAsReadAction $markAsReadAction,
    ) {}

    public function send(User $user, string $type, array $data): void
    {
        $notification = $this->sendAction->execute($user, $type, $data);

        NotificationSent::dispatch($notification, $user->id);
    }

    public function markAsRead(string $notificationId, User $user): void
    {
        $this->markAsReadAction->execute($notificationId, $user);
    }

    public function markAllAsRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }

    public function getUnreadNotifications(User $user): LengthAwarePaginator
    {
        return $user->notifications()
            ->unread()
            ->latest()
            ->paginate(20);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->notifications()->unread()->count();
    }
}
