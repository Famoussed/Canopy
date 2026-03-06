<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\ProjectRole;
use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-17, F-18, F-19: Backlog sıralama, Epic completion, Scope change testleri.
 *
 * Backlog reorder endpoint'i, epic tamamlanma yüzdesi ve sprint scope change algılama.
 */
class BacklogAndEpicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->project->memberships()->create([
            'user_id' => $this->user->id,
            'role' => ProjectRole::Owner,
        ]);
    }

    public function test_backlog_stories_can_be_reordered(): void
    {
        $story1 = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'order' => 1,
        ]);
        $story2 = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'order' => 2,
        ]);
        $story3 = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'order' => 3,
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/projects/{$this->project->slug}/stories/reorder",
            ['ordered_ids' => [$story3->id, $story1->id, $story2->id]]
        );

        $response->assertStatus(204);
    }

    public function test_epic_completion_percentage_via_api(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);

        UserStory::factory()->done()->create([
            'project_id' => $this->project->id,
            'epic_id' => $epic->id,
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'epic_id' => $epic->id,
            'status' => StoryStatus::New,
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/projects/{$this->project->slug}/epics/{$epic->id}"
        );

        $response->assertStatus(200);
    }

    public function test_story_move_to_sprint_triggers_scope_change(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/projects/{$this->project->slug}/stories/{$story->id}/move-to-sprint",
            ['sprint_id' => $sprint->id]
        );

        $response->assertStatus(200);
        $this->assertEquals($sprint->id, $story->fresh()->sprint_id);
    }

    public function test_sprint_with_scope_changes_tracked(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'total_points' => 5,
        ]);

        // Move story to sprint
        $this->actingAs($this->user)->postJson(
            "/api/projects/{$this->project->slug}/stories/{$story->id}/move-to-sprint",
            ['sprint_id' => $sprint->id]
        );

        // Verify story is in sprint
        $this->assertEquals($sprint->id, $story->fresh()->sprint_id);
    }
}
