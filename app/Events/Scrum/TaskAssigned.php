<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly User $assignee,
        public readonly User $assignedBy,
    ) {}
}
