<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\TaskStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\TaskNotAssignedException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task Workflow Testleri
 *
 * Bu test sınıfı User Story içindeki alt görevlerin (Task) yönetimini doğrular.
 * Task'lar için öngörülen atama (assign) mekanizmaları ve durum geçişleri kontrol edilir.
 *
 * Test Edilen Senaryolar:
 * - test_user_can_create_task: Yeni task yaratılması.
 * - test_task_can_be_assigned: Bir task'ın bir kullanıcıya atanması.
 * - test_unassigned_task_cannot_be_started: Atanmamış (assignee'si null) olan task'a In Progress değeri verilmemesi.
 * - test_invalid_task_status_transition_throws_exception: Yanlış durum geçişlerinde exception durumu.
 */
class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected UserStory $story;

    protected TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $this->taskService = app(TaskService::class);
    }

    public function test_user_can_create_task(): void
    {
        // create() imzası: (array $data, UserStory $story, User $creator)
        // user_story_id $story üzerinden implicitly set edilir, data'ya gerek yok
        $data = ['title' => 'Design Navbar'];

        $task = $this->taskService->create($data, $this->story, $this->user);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Design Navbar',
            'user_story_id' => $this->story->id,
            'status' => 'new', // TaskStatus::New varsayılan başlangıç değeri
        ]);
    }

    public function test_task_can_be_assigned(): void
    {
        $task = Task::factory()->create(['user_story_id' => $this->story->id, 'assigned_to' => null]);

        // assign() imzası: (Task $task, User $assignee, User $assignedBy)
        $this->taskService->assign($task, $this->user, $this->user);

        $this->assertEquals($this->user->id, $task->fresh()->assigned_to);
    }

    public function test_unassigned_task_cannot_be_started(): void
    {
        // BR-16: Atanmamış task'a New → InProgress geçişi yasaktır.
        // TaskStatus::New doğru enum case'i; 'Todo' bu uygulamada mevcut değil.
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'assigned_to' => null,
            'status' => TaskStatus::New->value,
        ]);

        $this->expectException(TaskNotAssignedException::class);

        // Ataması olmayan Task durum atlaması yaşayamaz
        $this->taskService->changeStatus($task, TaskStatus::InProgress, $this->user);
    }

    public function test_invalid_task_status_transition_throws_exception(): void
    {
        // Done durumundaki task; doğrudan New'e dönemez (sadece InProgress'e gidebilir).
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'assigned_to' => $this->user->id,
            'status' => TaskStatus::Done->value,
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        // Done → New geçişi yasaktır. (allowedTransitions: done → [in_progress])
        $this->taskService->changeStatus($task, TaskStatus::New, $this->user);
    }
}
