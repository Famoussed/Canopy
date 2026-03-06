<?php

declare(strict_types=1);

namespace Tests\Livewire\Projects;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-09 & L-10: ProjectSettings (MemberManager) Livewire component testi.
 *
 * Üye ekleme, rol değiştirme, üye çıkarma ve proje güncelleme testleri.
 */
class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->memberships()->create([
            'user_id' => $this->owner->id,
            'role' => ProjectRole::Owner,
        ]);
    }

    public function test_settings_page_renders(): void
    {
        $response = $this->actingAs($this->owner)->get(
            "/projects/{$this->project->slug}/settings"
        );

        $response->assertStatus(200);
        $response->assertSee('Proje Ayarları');
    }

    public function test_can_update_project_name(): void
    {
        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('projectName', 'Updated Project Name')
            ->call('saveProject')
            ->assertHasNoErrors();

        $this->assertEquals('Updated Project Name', $this->project->fresh()->name);
    }

    public function test_project_name_is_required(): void
    {
        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('projectName', '')
            ->call('saveProject')
            ->assertHasErrors(['projectName']);
    }

    public function test_add_member_by_email(): void
    {
        $newUser = User::factory()->create(['email' => 'yeniuye@test.com']);

        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('newMemberEmail', 'yeniuye@test.com')
            ->set('newMemberRole', 'member')
            ->call('addMember');

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $newUser->id,
            'role' => ProjectRole::Member->value,
        ]);
    }

    public function test_add_member_with_invalid_email_shows_error(): void
    {
        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('newMemberEmail', 'nonexistent@test.com')
            ->set('newMemberRole', 'member')
            ->call('addMember')
            ->assertHasErrors(['newMemberEmail']);
    }

    public function test_add_duplicate_member_shows_error(): void
    {
        $existingUser = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $existingUser->id,
            'role' => ProjectRole::Member,
        ]);

        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('newMemberEmail', $existingUser->email)
            ->set('newMemberRole', 'member')
            ->call('addMember')
            ->assertHasErrors(['newMemberEmail']);
    }

    public function test_change_member_role(): void
    {
        $member = User::factory()->create();
        $membership = $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->call('changeRole', $membership->id, 'moderator');

        $this->assertEquals(
            ProjectRole::Moderator->value,
            $membership->fresh()->role->value
        );
    }

    public function test_remove_member(): void
    {
        $member = User::factory()->create();
        $membership = $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->call('removeMember', $membership->id);

        $this->assertDatabaseMissing('project_memberships', [
            'id' => $membership->id,
        ]);
    }

    public function test_delete_project(): void
    {
        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->call('deleteProject');

        $this->assertSoftDeleted('projects', ['id' => $this->project->id]);
    }
}
