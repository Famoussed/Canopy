<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Test Project',
            'description' => 'A test project',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Project');

        // BR-13: Owner membership otomatik
        $this->assertDatabaseHas('project_memberships', [
            'user_id' => $user->id,
            'role' => ProjectRole::Owner->value,
        ]);
    }

    public function test_user_can_list_own_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        $response = $this->actingAs($user)->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_owner_can_update_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->slug}", [
            'name' => 'Updated Project',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Project');
    }

    public function test_owner_can_delete_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        $response = $this->actingAs($user)->deleteJson("/api/projects/{$project->slug}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_non_owner_cannot_delete_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->memberships()->create(['user_id' => $owner->id, 'role' => ProjectRole::Owner]);
        $project->memberships()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

        $response = $this->actingAs($member)->deleteJson("/api/projects/{$project->slug}");

        $response->assertStatus(403);
    }
}
