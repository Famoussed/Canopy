<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\CloseSprintAction;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-05: CloseSprintAction testi.
 *
 * Sprint kapatıldığında tamamlanmamış story'lerin backlog'a dönmesini test eder.
 */
class CloseSprintActionTest extends TestCase
{
    use RefreshDatabase;

    private CloseSprintAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CloseSprintAction;
    }

    public function test_close_sprint_transitions_to_closed(): void
    {
        $sprint = Sprint::factory()->active()->create();

        $result = $this->action->execute($sprint);

        $this->assertEquals(SprintStatus::Closed, $result->status);
    }

    public function test_unfinished_stories_return_to_backlog(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create(['project_id' => $project->id]);

        $doneStory = UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
        ]);

        $inProgressStory = UserStory::factory()->inProgress()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
        ]);

        $newStory = UserStory::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'status' => StoryStatus::New,
        ]);

        $this->action->execute($sprint);

        // Done story sprint'te kalır
        $this->assertNotNull($doneStory->fresh()->sprint_id);

        // InProgress ve New story'ler backlog'a döner
        $this->assertNull($inProgressStory->fresh()->sprint_id);
        $this->assertNull($newStory->fresh()->sprint_id);
    }

    public function test_all_done_stories_stay_in_sprint(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create(['project_id' => $project->id]);

        $story1 = UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
        ]);

        $story2 = UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
        ]);

        $this->action->execute($sprint);

        $this->assertNotNull($story1->fresh()->sprint_id);
        $this->assertNotNull($story2->fresh()->sprint_id);
    }
}
