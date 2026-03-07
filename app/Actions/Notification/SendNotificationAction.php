<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Events\Notification\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class SendNotificationAction
{
    public function execute(User $user, string $type, array $data): Notification
    {
        $notification = $user->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);

        try {
            NotificationSent::dispatch($notification, $user->id);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for NotificationSent', ['error' => $e->getMessage()]);
        }

        return $notification;
    }
}
