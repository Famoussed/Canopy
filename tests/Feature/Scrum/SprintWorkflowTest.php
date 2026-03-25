<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use App\Services\SprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint İş Akışı (Workflow) Testi
 *
 * Sprint yaşam döngüsünün tamamını test eder: oluşturma, başlatma, ikinci
 * Sprint kısıtlaması ve Sprint kapatma sırasındaki tamamlanmamış story'lerin
 * backlog'a döndürülmesi. SprintService üzerinden test edilir.
 */
class SprintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->memberships()->create([
            'user_id' => $this->owner->id,
            'role' => ProjectRole::Owner,
        ]);
    }

    public function test_can_create_sprint(): void
    {
        $service = app(SprintService::class);

        $sprint = $service->create([
            'name' => 'Sprint 1',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
        ], $this->project);

        $this->assertEquals('Sprint 1', $sprint->name);
        $this->assertEquals(SprintStatus::Planning, $sprint->status);
        $this->assertDatabaseHas('sprints', [
            'project_id' => $this->project->id,
            'name' => 'Sprint 1',
        ]);
    }

    public function test_can_start_sprint(): void
    {
        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Planning,
        ]);

        $service = app(SprintService::class);
        $started = $service->start($sprint, $this->owner);

        $this->assertEquals(SprintStatus::Active, $started->status);
    }

    public function test_cannot_start_second_sprint(): void
    {
        Sprint::factory()->active()->create(['project_id' => $this->project->id]);
        $sprint2 = Sprint::factory()->create(['project_id' => $this->project->id, 'status' => SprintStatus::Planning]);

        $service = app(SprintService::class);

        $this->expectException(\App\Exceptions\ActiveSprintAlreadyExistsException::class);
        $service->start($sprint2, $this->owner);
    }

    public function test_close_sprint_returns_unfinished_stories_to_backlog(): void
    {
        $sprint = Sprint::factory()->active()->create(['project_id' => $this->project->id]);
        $story = UserStory::factory()->inProgress()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'created_by' => $this->owner->id,
        ]);

        $service = app(SprintService::class);
        $closed = $service->close($sprint, $this->owner);

        $this->assertEquals(SprintStatus::Closed, $closed->status);

        // BR-08: Unfinished story goes back to backlog
        $this->assertNull($story->fresh()->sprint_id);
    }
}
