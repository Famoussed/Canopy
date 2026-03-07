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

class StoryCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly UserStory $story,
        public readonly User $creator,
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
        return 'story.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'story_id' => $this->story->id,
            'story_title' => $this->story->title,
            'created_by' => $this->creator->id,
        ];
    }
}
