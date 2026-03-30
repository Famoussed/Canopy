<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Scrum\TaskAssigned;
use App\Services\NotificationService;

class SendTaskAssignedNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $this->notificationService->send(
            user: $event->assignee,
            type: 'task_assigned',
            data: [
                'task_id' => $event->task->id,
                'task_title' => $event->task->title,
                'assigned_by' => $event->assignedBy->name,
            ],
        );
    }
}
