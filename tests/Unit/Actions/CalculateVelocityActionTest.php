<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Analytics\CalculateVelocityAction;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-10: CalculateVelocityAction testi.
 *
 * Sprint bazında velocity hesaplamasını doğrular.
 */
class CalculateVelocityActionTest extends TestCase
{
    use RefreshDatabase;

    private CalculateVelocityAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculateVelocityAction;
    }

    public function test_calculates_velocity_for_closed_sprints(): void
    {
        $project = Project::factory()->create();

        $sprint1 = Sprint::factory()->closed()->create(['project_id' => $project->id]);
        $sprint2 = Sprint::factory()->closed()->create(['project_id' => $project->id]);

        UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint1->id,
            'total_points' => 10,
        ]);

        UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint2->id,
            'total_points' => 20,
        ]);

        $result = $this->action->execute($project);

        $this->assertCount(2, $result['sprints']);
        $this->assertEquals(15.0, $result['average_velocity']);
    }

    public function test_ignores_planning_and_active_sprints(): void
    {
        $project = Project::factory()->create();

        Sprint::factory()->create(['project_id' => $project->id, 'status' => \App\Enums\SprintStatus::Planning]);
        Sprint::factory()->active()->create(['project_id' => $project->id]);

        $result = $this->action->execute($project);

        $this->assertCount(0, $result['sprints']);
        $this->assertEquals(0, $result['average_velocity']);
    }

    public function test_limits_to_requested_sprint_count(): void
    {
        $project = Project::factory()->create();

        for ($i = 0; $i < 8; $i++) {
            Sprint::factory()->closed()->create(['project_id' => $project->id]);
        }

        $result = $this->action->execute($project, 3);

        $this->assertCount(3, $result['sprints']);
    }

    public function test_only_counts_done_stories(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->closed()->create(['project_id' => $project->id]);

        UserStory::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 10,
        ]);

        UserStory::factory()->inProgress()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 5,
        ]);

        $result = $this->action->execute($project);

        $this->assertEquals(10.0, $result['sprints'][0]['completed_points']);
    }
}
