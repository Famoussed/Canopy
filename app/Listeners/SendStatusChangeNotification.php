<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Notification\SendNotificationAction;

class SendStatusChangeNotification
{
    public function __construct(
        private readonly SendNotificationAction $action,
    ) {}

    /**
     * Herhangi bir status değişikliği event'ini dinler.
     * StoryStatusChanged, TaskStatusChanged, IssueStatusChanged
     */
    public function handle(object $event): void
    {
        // Notification gönderme mantığı burada implement edilecek.
        // Event'in tipine göre ilgili kullanıcılara bildirim gönderilir.
    }
}
