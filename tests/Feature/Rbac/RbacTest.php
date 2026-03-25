<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Exceptions\OwnerCannotBeRemovedException;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rol Tabanlı Erişim Kontrolü (RBAC) Testi
 *
 * Proje içindeki farklı rollerin (Owner, Moderator, Member) erişim
 * yetkilerinin Policy ve Livewire katmanında doğru uygulandığını test eder.
 */
class RbacTest extends TestCase
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

    public function test_member_cannot_create_story(): void
    {
        $this->assertFalse($this->member->can('create', [UserStory::class, $this->project]));
    }

    public function test_moderator_can_create_story(): void
    {
        $this->assertTrue($this->moderator->can('create', [UserStory::class, $this->project]));
    }

    public function test_member_can_create_issue(): void
    {
        // Members can create issues — verify via Livewire
        Livewire::actingAs($this->member)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('createForm.title', 'Bug Report')
            ->set('createForm.type', 'bug')
            ->call('createIssue')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'title' => 'Bug Report',
        ]);
    }

    public function test_non_member_cannot_access_project(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->get("/projects/{$this->project->slug}/backlog");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_any_project(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get("/projects/{$this->project->slug}/backlog");

        $response->assertStatus(200);
    }

    public function test_owner_cannot_be_removed(): void
    {
        // BR-14: Proje sahibi projeden çıkarılamaz — service throws OwnerCannotBeRemovedException
        $service = app(MembershipService::class);

        try {
            $service->removeById($this->project, $this->owner->id, $this->moderator);
        } catch (OwnerCannotBeRemovedException $e) {
            // Expected — owner cannot be removed
        }

        // Owner should still be a member
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
        ]);
    }
}
