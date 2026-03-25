<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P-08, P-09, P-10: Issue, Project yetki ve üyelik yönetimi testleri.
 *
 * Üyelik kontrol middleware'i, proje güncelleme yetkilendirme ve üye yönetimi senaryoları.
 */
class AdvancedRbacTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private Project $project;

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
    }

    public function test_member_can_create_issue_with_priority(): void
    {
        Livewire::actingAs($this->member)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('createForm.title', 'Priority Bug')
            ->set('createForm.type', 'bug')
            ->set('createForm.priority', 'high')
            ->set('createForm.severity', 'critical')
            ->call('createIssue')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'title' => 'Priority Bug',
            'priority' => 'high',
        ]);
    }

    public function test_member_cannot_update_project(): void
    {
        $this->assertFalse($this->member->can('update', $this->project));
    }

    public function test_owner_can_update_project(): void
    {
        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('form.name', 'Updated Name')
            ->call('saveProject')
            ->assertHasNoErrors();

        $this->assertEquals('Updated Name', $this->project->fresh()->name);
    }

    public function test_moderator_cannot_delete_project(): void
    {
        $this->assertFalse($this->moderator->can('delete', $this->project));
    }

    public function test_ensure_project_member_middleware_blocks_non_member(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->get("/projects/{$this->project->slug}/backlog");

        $response->assertStatus(403);
    }

    public function test_ensure_project_member_middleware_allows_member(): void
    {
        $response = $this->actingAs($this->member)->get("/projects/{$this->project->slug}/backlog");

        $response->assertStatus(200);
    }

    public function test_ensure_project_member_middleware_allows_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get("/projects/{$this->project->slug}/backlog");

        $response->assertStatus(200);
    }

    public function test_moderator_can_add_member(): void
    {
        $newUser = User::factory()->create(['email' => 'newuser@test.com']);

        Livewire::actingAs($this->moderator)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('newMemberEmail', 'newuser@test.com')
            ->set('newMemberRole', 'member')
            ->call('addMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_member_cannot_add_member(): void
    {
        $this->assertFalse($this->member->can('addMember', $this->project));
    }

    public function test_member_cannot_remove_other_member(): void
    {
        $this->assertFalse($this->member->can('removeMember', $this->project));
    }

    public function test_max_member_limit_enforced_via_api(): void
    {
        // setUp creates 3 members (owner, moderator, member). Add 2 more to reach 5.
        for ($i = 0; $i < 2; $i++) {
            $this->project->memberships()->create([
                'user_id' => User::factory()->create()->id,
                'role' => ProjectRole::Member,
            ]);
        }

        $sixthUser = User::factory()->create(['email' => 'sixth@test.com']);

        Livewire::actingAs($this->owner)
            ->test('projects.project-settings', ['project' => $this->project])
            ->set('newMemberEmail', 'sixth@test.com')
            ->set('newMemberRole', 'member')
            ->call('addMember')
            ->assertHasErrors(['newMemberEmail']);

        $this->assertDatabaseMissing('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $sixthUser->id,
        ]);
    }
}
