<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\User;
use App\Models\UserStory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly UserStory $story,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly User $changedBy,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->story->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'story.status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'story_id' => $this->story->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'changed_by' => $this->changedBy->id,
        ];
    }
}
