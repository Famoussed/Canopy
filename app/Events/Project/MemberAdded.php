<?php

declare(strict_types=1);

namespace App\Events\Project;

use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly User $member,
        public readonly ProjectMembership $membership,
        public readonly User $addedBy,
    ) {}

    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->member->id}"),
            new PrivateChannel("project.{$this->project->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'member.added';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'member_id' => $this->member->id,
            'role' => $this->membership->role->value,
            'added_by' => $this->addedBy->id,
        ];
    }
}
