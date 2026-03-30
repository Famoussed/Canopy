<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\User;

class SendNotificationAction
{
    public function execute(User $user, string $type, array $data): Notification
    {
        return $user->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);
    }
}
