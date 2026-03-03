<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\StartSprintAction;
use App\Enums\SprintStatus;
use App\Exceptions\ActiveSprintAlreadyExistsException;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartSprintActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_planning_sprint(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'status' => SprintStatus::Planning,
        ]);

        $action = new StartSprintAction();
        $result = $action->execute($sprint);

        $this->assertEquals(SprintStatus::Active, $result->status);
        $this->assertEquals(now()->toDateString(), $result->start_date->toDateString());
    }

    public function test_cannot_start_when_active_sprint_exists(): void
    {
        $project = Project::factory()->create();
        Sprint::factory()->active()->create(['project_id' => $project->id]);
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'status' => SprintStatus::Planning,
        ]);

        $this->expectException(ActiveSprintAlreadyExistsException::class);

        $action = new StartSprintAction();
        $action->execute($sprint);
    }
}
