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

class SprintClosed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Sprint $sprint,
        public readonly User $closedBy,
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
        return 'sprint.closed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sprint_id' => $this->sprint->id,
            'sprint_name' => $this->sprint->name,
            'closed_by' => $this->closedBy->id,
        ];
    }
}
