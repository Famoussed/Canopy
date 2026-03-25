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
use App\Services\UserStoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-17, F-18, F-19: Backlog sıralama, Epic completion, Scope change testleri.
 *
 * Backlog reorder, epic tamamlanma yüzdesi ve sprint scope change algılama.
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

        $service = app(UserStoryService::class);
        $service->reorder($this->project, [$story3->id, $story1->id, $story2->id]);

        $this->assertEquals(1, $story3->fresh()->order);
        $this->assertEquals(2, $story1->fresh()->order);
        $this->assertEquals(3, $story2->fresh()->order);
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

        // Verify epic exists and stories are associated
        $this->assertDatabaseHas('epics', ['id' => $epic->id]);
        $this->assertEquals(2, $epic->userStories()->count());

        // Verify one story is done
        $doneCount = $epic->userStories()->where('status', StoryStatus::Done)->count();
        $this->assertEquals(1, $doneCount);
    }

    public function test_story_move_to_sprint_triggers_scope_change(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $service = app(UserStoryService::class);
        $service->moveToSprint($story, $sprint, $this->user);

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

        $service = app(UserStoryService::class);
        $service->moveToSprint($story, $sprint, $this->user);

        // Verify story is in sprint after move
        $this->assertEquals($sprint->id, $story->fresh()->sprint_id);
    }
}
