<?php

declare(strict_types=1);

namespace Tests\Livewire\Issues;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5: Issue Assignment & UI Enhancement Tests
 *
 * Issue atama, oluşturan kolonu, ve yetki bazlı görünürlük testleri.
 */
class IssueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Owner User']);
        $this->moderator = User::factory()->create(['name' => 'Moderator User']);
        $this->member = User::factory()->create(['name' => 'Member User']);

        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->project->memberships()->createMany([
            ['user_id' => $this->owner->id, 'role' => ProjectRole::Owner],
            ['user_id' => $this->moderator->id, 'role' => ProjectRole::Moderator],
            ['user_id' => $this->member->id, 'role' => ProjectRole::Member],
        ]);
    }

    // ─── Creator Column Tests ───

    public function test_issue_list_shows_creator_column(): void
    {
        Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $response = $this->actingAs($this->owner)->get(
            "/projects/{$this->project->slug}/issues"
        );

        $response->assertSee('Oluşturan');
    }

    public function test_issue_list_shows_creator_name(): void
    {
        Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $response = $this->actingAs($this->owner)->get(
            "/projects/{$this->project->slug}/issues"
        );

        $response->assertSee('Member User');
    }

    // ─── Assignment Tests ───

    public function test_owner_can_assign_issue_via_livewire(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => null,
        ]);

        Livewire::actingAs($this->owner)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('assignIssue', $issue->id, $this->member->id);

        $this->assertEquals($this->member->id, $issue->fresh()->assigned_to);
    }

    public function test_moderator_can_assign_issue_via_livewire(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
            'assigned_to' => null,
        ]);

        Livewire::actingAs($this->moderator)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('assignIssue', $issue->id, $this->owner->id);

        $this->assertEquals($this->owner->id, $issue->fresh()->assigned_to);
    }

    public function test_member_cannot_assign_issue_via_livewire(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
            'assigned_to' => null,
        ]);

        Livewire::actingAs($this->member)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('assignIssue', $issue->id, $this->owner->id)
            ->assertForbidden();

        $this->assertNull($issue->fresh()->assigned_to);
    }

    public function test_can_unassign_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
            'assigned_to' => $this->member->id,
        ]);

        Livewire::actingAs($this->owner)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('assignIssue', $issue->id, '');

        $this->assertNull($issue->fresh()->assigned_to);
    }

    // ─── Delete Visibility Tests ───

    public function test_member_can_delete_own_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        Livewire::actingAs($this->member)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('deleteIssue', $issue->id);

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    public function test_member_cannot_delete_others_issue(): void
    {
        $otherMember = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $otherMember->id,
            'role' => ProjectRole::Member,
        ]);

        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->owner->id,
        ]);

        Livewire::actingAs($otherMember)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('deleteIssue', $issue->id)
            ->assertForbidden();

        $this->assertDatabaseHas('issues', ['id' => $issue->id]);
    }

    // ─── Assignment during Creation ───

    public function test_owner_can_create_issue_with_assignment(): void
    {
        Livewire::actingAs($this->owner)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('title', 'Assigned Bug')
            ->set('type', 'bug')
            ->set('assignedTo', $this->member->id)
            ->call('createIssue');

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'title' => 'Assigned Bug',
            'assigned_to' => $this->member->id,
        ]);
    }
}
