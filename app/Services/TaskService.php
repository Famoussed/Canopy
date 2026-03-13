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
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    public function __construct(
        private readonly ChangeTaskStatusAction $changeStatusAction,
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
        $oldStatus = $task->status;

        $task = DB::transaction(function () use ($task, $newStatus) {
            return $this->changeStatusAction->execute($task, $newStatus);
        });

        try {
            TaskStatusChanged::dispatch($task, $oldStatus->value, $newStatus->value, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for TaskStatusChanged', ['error' => $e->getMessage()]);
        }

        return $task;
    }

    public function assign(Task $task, User $assignee, User $assignedBy): Task
    {
        $task->update(['assigned_to' => $assignee->id]);
        $task = $task->fresh();

        try {
            TaskAssigned::dispatch($task, $assignee, $assignedBy);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for TaskAssigned', ['error' => $e->getMessage()]);
        }

        return $task;
    }
}
