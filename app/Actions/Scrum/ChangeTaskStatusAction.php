<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\TaskStatus;
use App\Exceptions\TaskNotAssignedException;
use App\Models\Task;

class ChangeTaskStatusAction
{
    /**
     * BR-16: Task atanmadan başlatılamaz.
     */
    public function execute(Task $task, TaskStatus $newStatus): Task
    {
        // BR-16: new → in_progress geçişi için atanmış olmalı
        if (
            $task->status === TaskStatus::New
            && $newStatus === TaskStatus::InProgress
            && $task->assigned_to === null
        ) {
            throw new TaskNotAssignedException($task);
        }

        $task->transitionTo($newStatus->value);

        return $task->fresh();
    }
}
