<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;

class MarkAsReadAction
{
    public function execute(string $notificationId, User $user): void
    {
        $user->notifications()
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
