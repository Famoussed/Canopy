<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Notification\SendNotificationAction;
use App\Events\Project\MemberAdded;

class SendMemberAddedNotification
{
    public function __construct(
        private readonly SendNotificationAction $action,
    ) {}

    public function handle(MemberAdded $event): void
    {
        $this->action->execute(
            user: $event->member,
            type: 'member_added',
            data: [
                'project_id' => $event->project->id,
                'project_name' => $event->project->name,
                'role' => $event->membership->role->value,
                'added_by' => $event->addedBy->name,
            ],
        );
    }
}
