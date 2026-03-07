<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Notification\SendNotificationAction;
use App\Events\Issue\IssueStatusChanged;
use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskStatusChanged;

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
        match (true) {
            $event instanceof StoryStatusChanged => $this->handleStoryStatusChanged($event),
            $event instanceof TaskStatusChanged => $this->handleTaskStatusChanged($event),
            $event instanceof IssueStatusChanged => $this->handleIssueStatusChanged($event),
            default => null,
        };
    }

    private function handleStoryStatusChanged(StoryStatusChanged $event): void
    {
        $story = $event->story;

        if ($story->created_by === $event->changedBy->id) {
            return;
        }

        $creator = $story->creator;

        if ($creator) {
            $this->action->execute(
                user: $creator,
                type: 'story_status_changed',
                data: [
                    'story_id' => $story->id,
                    'story_title' => $story->title,
                    'old_status' => $event->oldStatus,
                    'new_status' => $event->newStatus,
                    'changed_by' => $event->changedBy->name,
                ],
            );
        }
    }

    private function handleTaskStatusChanged(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if ($task->assigned_to === null || $task->assigned_to === $event->changedBy->id) {
            return;
        }

        $this->action->execute(
            user: $task->assignee,
            type: 'task_status_changed',
            data: [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'changed_by' => $event->changedBy->name,
            ],
        );
    }

    private function handleIssueStatusChanged(IssueStatusChanged $event): void
    {
        $issue = $event->issue;

        if ($issue->created_by === $event->changedBy->id) {
            return;
        }

        $creator = $issue->creator;

        if ($creator) {
            $this->action->execute(
                user: $creator,
                type: 'issue_status_changed',
                data: [
                    'issue_id' => $issue->id,
                    'issue_title' => $issue->title,
                    'old_status' => $event->oldStatus,
                    'new_status' => $event->newStatus,
                    'changed_by' => $event->changedBy->name,
                ],
            );
        }
    }
}
