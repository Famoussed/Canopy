<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-05, P-06, P-07: Task RBAC policy testleri.
 *
 * Member kendi task'ını düzenleyebilir, başkasının task'ını düzenleyemez,
 * Moderator tüm task'ları düzenleyebilir senaryolarını test eder.
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
            'status' => TaskStatus::InProgress,
        ]);

        $response = $this->actingAs($this->member)->putJson(
            "/api/tasks/{$task->id}/status",
            ['status' => 'done']
        );

        $response->assertStatus(200);
        $this->assertEquals(TaskStatus::Done, $task->fresh()->status);
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
            'status' => TaskStatus::InProgress,
        ]);

        $response = $this->actingAs($this->member)->putJson(
            "/api/tasks/{$task->id}/status",
            ['status' => 'done']
        );

        $response->assertStatus(403);
    }

    public function test_moderator_can_change_any_task_status(): void
    {
        $task = Task::factory()->assigned($this->member)->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::InProgress,
        ]);

        $response = $this->actingAs($this->moderator)->putJson(
            "/api/tasks/{$task->id}/status",
            ['status' => 'done']
        );

        $response->assertStatus(200);
    }

    public function test_member_cannot_create_task(): void
    {
        $response = $this->actingAs($this->member)->postJson(
            "/api/stories/{$this->story->id}/tasks",
            ['title' => 'New Task']
        );

        $response->assertStatus(403);
    }

    public function test_moderator_can_create_task(): void
    {
        $response = $this->actingAs($this->moderator)->postJson(
            "/api/stories/{$this->story->id}/tasks",
            ['title' => 'New Task']
        );

        $response->assertStatus(201);
    }

    public function test_member_cannot_assign_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $response = $this->actingAs($this->member)->putJson(
            "/api/tasks/{$task->id}/assign",
            ['assigned_to' => $this->member->id]
        );

        $response->assertStatus(403);
    }

    public function test_moderator_can_assign_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $response = $this->actingAs($this->moderator)->putJson(
            "/api/tasks/{$task->id}/assign",
            ['assigned_to' => $this->member->id]
        );

        $response->assertStatus(200);
    }

    public function test_member_cannot_delete_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $response = $this->actingAs($this->member)->deleteJson(
            "/api/tasks/{$task->id}"
        );

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_task(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
        ]);

        $response = $this->actingAs($this->owner)->deleteJson(
            "/api/tasks/{$task->id}"
        );

        $response->assertStatus(204);
    }
}
