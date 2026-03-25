<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-05, P-06, P-07: Task RBAC policy testleri.
 *
 * Task düzenleme ve durum değiştirme yetkisi: task'ı oluşturan üye, Moderatör veya Owner.
 * Member kendi oluşturduğu task'ı düzenleyebilir ve durumunu değiştirebilir,
 * başkasının oluşturduğu task'ta bu yetkilere sahip değildir. (P19, P20)
 */
class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private Project $project;

    private UserStory $story;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->moderator = User::factory()->create();
        $this->member = User::factory()->create();

        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->project->memberships()->createMany([
            ['user_id' => $this->owner->id, 'role' => ProjectRole::Owner],
            ['user_id' => $this->moderator->id, 'role' => ProjectRole::Moderator],
            ['user_id' => $this->member->id, 'role' => ProjectRole::Member],
        ]);

        $this->story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_member_can_change_own_task_status(): void
    {
        $task = Task::factory()->assigned($this->member)->create([
            'user_story_id' => $this->story->id,
            'created_by' => $this->member->id,
            'status' => TaskStatus::InProgress,
        ]);

        $this->assertTrue($this->member->can('changeStatus', $task));

        $service = app(TaskService::class);
        $updated = $service->changeStatus($task, TaskStatus::Done, $this->member);

        $this->assertEquals(TaskStatus::Done, $updated->status);
    }

    public function test_member_cannot_change_others_task_status(): void
    {
        $otherMember = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $otherMember->id,
            'role' => ProjectRole::Member,
        ]);

        $task = Task::factory()->assigned($otherMember)->create([
            'user_story_id' => $this->story->id,
            'created_by' => $otherMember->id,
            'status' => TaskStatus::InProgress,
        ]);

        $this->assertFalse($this->member->can('changeStatus', $task));
    }

    public function test_moderator_can_change_any_task_status(): void
    {
        $task = Task::factory()->assigned($this->member)->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::InProgress,
        ]);

        $this->assertTrue($this->moderator->can('changeStatus', $task));

        $service = app(TaskService::class);
        $updated = $service->changeStatus($task, TaskStatus::Done, $this->moderator);

        $this->assertEquals(TaskStatus::Done, $updated->status);
    }

    public function test_member_can_create_task(): void
    {
        $this->assertTrue($this->member->can('create', [Task::class, $this->project]));

        $service = app(TaskService::class);
        $task = $service->create(['title' => 'New Task'], $this->story, $this->member);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'New Task']);
    }

    public function test_moderator_can_create_task(): void
    {
        $this->assertTrue($this->moderator->can('create', [Task::class, $this->project]));
    }

    public function test_non_member_cannot_create_task(): void
    {
        $nonMember = User::factory()->create();

        $this->assertFalse($nonMember->can('create', [Task::class, $this->project]));
    }

    public function test_member_cannot_assign_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $this->assertFalse($this->member->can('assign', $task));
    }

    public function test_moderator_can_assign_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $this->assertTrue($this->moderator->can('assign', $task));

        $service = app(TaskService::class);
        $updated = $service->assign($task, $this->member, $this->moderator);

        $this->assertEquals($this->member->id, $updated->assigned_to);
    }

    public function test_member_cannot_delete_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $this->assertFalse($this->member->can('delete', $task));
    }

    public function test_owner_can_delete_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $this->assertTrue($this->owner->can('delete', $task));

        $service = app(TaskService::class);
        $service->delete($task);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_member_can_update_own_created_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->member->can('update', $task));

        $service = app(TaskService::class);
        $updated = $service->update($task, ['title' => 'Updated By Creator']);

        $this->assertEquals('Updated By Creator', $updated->title);
    }

    public function test_member_cannot_update_task_created_by_others(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'created_by' => $this->moderator->id,
        ]);

        $this->assertFalse($this->member->can('update', $task));
    }
}
