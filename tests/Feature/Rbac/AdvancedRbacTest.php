<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-08, P-09, P-10: Issue, Transfer Ownership, EnsureProjectMember testleri.
 *
 * Üyelik kontrol middleware'i, issue yetkilendirme ve ownership transfer senaryoları.
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
        $response = $this->actingAs($this->member)->postJson(
            "/api/projects/{$this->project->slug}/issues",
            [
                'title' => 'Priority Bug',
                'type' => 'bug',
                'priority' => 'high',
                'severity' => 'critical',
            ]
        );

        $response->assertStatus(201);
    }

    public function test_member_cannot_update_project(): void
    {
        $response = $this->actingAs($this->member)->putJson(
            "/api/projects/{$this->project->slug}",
            ['name' => 'New Name']
        );

        $response->assertStatus(403);
    }

    public function test_owner_can_update_project(): void
    {
        $response = $this->actingAs($this->owner)->putJson(
            "/api/projects/{$this->project->slug}",
            ['name' => 'Updated Name']
        );

        $response->assertStatus(200);
    }

    public function test_moderator_cannot_delete_project(): void
    {
        $response = $this->actingAs($this->moderator)->deleteJson(
            "/api/projects/{$this->project->slug}"
        );

        $response->assertStatus(403);
    }

    public function test_ensure_project_member_middleware_blocks_non_member(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->getJson(
            "/api/projects/{$this->project->slug}/stories"
        );

        $response->assertStatus(403);
    }

    public function test_ensure_project_member_middleware_allows_member(): void
    {
        $response = $this->actingAs($this->member)->getJson(
            "/api/projects/{$this->project->slug}/stories"
        );

        $response->assertStatus(200);
    }

    public function test_ensure_project_member_middleware_allows_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->getJson(
            "/api/projects/{$this->project->slug}/stories"
        );

        $response->assertStatus(200);
    }

    public function test_moderator_can_add_member(): void
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->moderator)->postJson(
            "/api/projects/{$this->project->slug}/members",
            [
                'email' => $newUser->email,
                'role' => 'member',
            ]
        );

        $response->assertSuccessful();
    }

    public function test_member_cannot_add_member(): void
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->member)->postJson(
            "/api/projects/{$this->project->slug}/members",
            [
                'email' => $newUser->email,
                'role' => 'member',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_member_cannot_remove_other_member(): void
    {
        $otherMember = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $otherMember->id,
            'role' => ProjectRole::Member,
        ]);

        $response = $this->actingAs($this->member)->deleteJson(
            "/api/projects/{$this->project->slug}/members/{$otherMember->id}"
        );

        $response->assertStatus(403);
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

        $sixthUser = User::factory()->create();

        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->slug}/members",
            [
                'email' => $sixthUser->email,
                'role' => 'member',
            ]
        );

        $response->assertStatus(422);
    }
}
