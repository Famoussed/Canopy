<?php

declare(strict_types=1);

namespace App\Events\Issue;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Issue $issue,
        public readonly User $creator,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.'.$this->issue->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'issue.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'title' => $this->issue->title,
            'created_by' => $this->creator->id,
        ];
    }
}
