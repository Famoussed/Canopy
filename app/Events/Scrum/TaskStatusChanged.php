<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\Task;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly User $changedBy,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->task->userStory->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'story_id' => $this->task->user_story_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by' => $this->changedBy->id,
        ];
    }
}
