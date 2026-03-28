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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

        $task->loadMissing('userStory');

        TaskStatusChanged::dispatch($task, $oldStatus->value, $newStatus->value, $user);

        return $task;
    }

    public function assign(Task $task, User $assignee, User $assignedBy): Task
    {
        $task->update(['assigned_to' => $assignee->id]);
        $task = $task->fresh();

        $task->loadMissing('userStory');

        TaskAssigned::dispatch($task, $assignee, $assignedBy);

        return $task;
    }

    public function listByStory(UserStory $story): Collection
    {
        return $story->tasks()->with('assignee')->get();
    }

    public function assignById(Task $task, string $assigneeId, User $assignedBy): Task
    {
        $assignee = User::findOrFail($assigneeId);

        return $this->assign($task, $assignee, $assignedBy);
    }

    public function getTaskDetails(Task $task): Task
    {
        return $task->loadMissing('userStory.project');
    }

    public function findById(string $id): Task
    {
        return Task::findOrFail($id);
    }

    public function unassign(Task $task): Task
    {
        $task->update(['assigned_to' => null]);

        return $task->fresh();
    }
}
