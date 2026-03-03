<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Notification\SendNotificationAction;
use App\Events\Scrum\TaskAssigned;

class SendTaskAssignedNotification
{
    public function __construct(
        private readonly SendNotificationAction $action,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $this->action->execute(
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
