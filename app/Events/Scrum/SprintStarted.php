<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SprintStarted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Sprint $sprint,
        public readonly User $startedBy,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->sprint->project_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sprint.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sprint_id' => $this->sprint->id,
            'sprint_name' => $this->sprint->name,
            'started_by' => $this->startedBy->id,
        ];
    }
}
