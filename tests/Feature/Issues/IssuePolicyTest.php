<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5: Issue Policy — Assignment & Delete Permission Tests
 *
 * BR-19 genişletme: Issue silme yetkisi (Moderator+ veya oluşturan),
 * Issue atama yetkisi (Owner/Moderator).
 */
class IssuePolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private User $otherMember;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->moderator = User::factory()->create();
        $this->member = User::factory()->create();
        $this->otherMember = User::factory()->create();

        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->project->memberships()->createMany([
            ['user_id' => $this->owner->id, 'role' => ProjectRole::Owner],
            ['user_id' => $this->moderator->id, 'role' => ProjectRole::Moderator],
            ['user_id' => $this->member->id, 'role' => ProjectRole::Member],
            ['user_id' => $this->otherMember->id, 'role' => ProjectRole::Member],
        ]);
    }

    // ─── Delete Permission Tests ───

    public function test_owner_can_delete_any_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->owner->can('delete', $issue));
    }

    public function test_moderator_can_delete_any_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->moderator->can('delete', $issue));
    }

    public function test_member_can_delete_own_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->member->can('delete', $issue));
    }

    public function test_member_cannot_delete_others_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertFalse($this->otherMember->can('delete', $issue));
    }

    // ─── Assign Permission Tests ───

    public function test_owner_can_assign_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->owner->can('assign', $issue));
    }

    public function test_moderator_can_assign_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->moderator->can('assign', $issue));
    }

    public function test_member_cannot_assign_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertFalse($this->member->can('assign', $issue));
    }

    // ─── Existing Permissions Remain Intact ───

    public function test_member_can_create_issue(): void
    {
        $this->assertTrue($this->member->can('create', [Issue::class, $this->project]));
    }

    public function test_member_can_update_own_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->member->can('update', $issue));
    }

    public function test_member_cannot_update_others_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertFalse($this->otherMember->can('update', $issue));
    }
}
