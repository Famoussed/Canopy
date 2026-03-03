<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\ChangeTaskStatusAction;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotAssignedException;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeTaskStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_start_unassigned_task(): void
    {
        $story = UserStory::factory()->create();
        $task = Task::factory()->create([
            'user_story_id' => $story->id,
            'status' => TaskStatus::New,
            'assigned_to' => null,
        ]);

        $this->expectException(TaskNotAssignedException::class);

        $action = new ChangeTaskStatusAction();
        $action->execute($task, TaskStatus::InProgress);
    }

    public function test_assigned_task_can_start(): void
    {
        $user = User::factory()->create();
        $story = UserStory::factory()->create();
        $task = Task::factory()->create([
            'user_story_id' => $story->id,
            'status' => TaskStatus::New,
            'assigned_to' => $user->id,
        ]);

        $action = new ChangeTaskStatusAction();
        $result = $action->execute($task, TaskStatus::InProgress);

        $this->assertEquals(TaskStatus::InProgress, $result->status);
    }
}
