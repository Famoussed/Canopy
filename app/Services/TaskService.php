<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\ChangeTaskStatusAction;
use App\Enums\TaskStatus;
use App\Events\Scrum\TaskAssigned;
use App\Events\Scrum\TaskStatusChanged;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        private ChangeTaskStatusAction $changeStatusAction,
    ) {}

    public function create(array $data, UserStory $story, User $creator): Task
    {
        return $story->tasks()->create([
            ...$data,
            'status' => TaskStatus::New,
            'created_by' => $creator->id,
        ]);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->fresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function changeStatus(Task $task, TaskStatus $newStatus, User $user): Task
    {
        return DB::transaction(function () use ($task, $newStatus, $user) {
            $oldStatus = $task->status;

            $task = $this->changeStatusAction->execute($task, $newStatus);

            TaskStatusChanged::dispatch($task, $oldStatus->value, $newStatus->value, $user);

            return $task;
        });
    }

    public function assign(Task $task, User $assignee, User $assignedBy): Task
    {
        $task->update(['assigned_to' => $assignee->id]);
        $task = $task->fresh();

        TaskAssigned::dispatch($task, $assignee, $assignedBy);

        return $task;
    }
}
