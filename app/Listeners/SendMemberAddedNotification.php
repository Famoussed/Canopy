<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Project\MemberAdded;
use App\Services\NotificationService;

class SendMemberAddedNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(MemberAdded $event): void
    {
        $this->notificationService->send(
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
