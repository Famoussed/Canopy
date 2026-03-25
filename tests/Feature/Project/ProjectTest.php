<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proje CRUD ve Yetkilendirme Testi
 *
 * Proje oluşturma, güncelleme ve silme işlemlerini Livewire bileşenleri
 * üzerinden test eder.
 */
class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('projects.create-project')
            ->set('form.name', 'Test Project')
            ->set('form.description', 'A test project')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', ['name' => 'Test Project']);

        // BR-13: Owner membership otomatik oluşturulur
        $project = Project::where('name', 'Test Project')->first();
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => ProjectRole::Owner->value,
        ]);
    }

    public function test_user_can_list_own_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee($project->name);
    }

    public function test_owner_can_update_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        Livewire::actingAs($user)
            ->test('projects.project-settings', ['project' => $project])
            ->set('form.name', 'Updated Project')
            ->call('saveProject')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated Project']);
    }

    public function test_owner_can_delete_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->memberships()->create(['user_id' => $user->id, 'role' => ProjectRole::Owner]);

        Livewire::actingAs($user)
            ->test('projects.project-settings', ['project' => $project])
            ->call('deleteProject');

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_non_owner_cannot_delete_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->memberships()->create(['user_id' => $owner->id, 'role' => ProjectRole::Owner]);
        $project->memberships()->create(['user_id' => $member->id, 'role' => ProjectRole::Member]);

        // Policy: only Owner can delete a project
        $this->assertFalse($member->can('delete', $project));
        $this->assertTrue($owner->can('delete', $project));
    }
}
