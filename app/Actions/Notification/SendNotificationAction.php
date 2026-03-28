<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Events\Notification\NotificationSent;
use App\Models\Notification;
use App\Models\User;

class SendNotificationAction
{
    public function execute(User $user, string $type, array $data): Notification
    {
        $notification = $user->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);

        NotificationSent::dispatch($notification, $user->id);

        return $notification;
    }
}
