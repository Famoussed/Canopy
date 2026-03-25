<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Task;
use Exception;

class TaskNotAssignedException extends Exception
{
    public function __construct(
        public readonly Task $task,
    ) {
        parent::__construct(
            "Task #{$task->id} başlatılamaz: henüz bir kullanıcıya atanmamış.",
            422,
        );
    }
}
