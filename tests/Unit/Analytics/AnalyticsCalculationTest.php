<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Actions\Analytics\CalculateBurndownAction;
use App\Actions\Analytics\CalculateVelocityAction;
use App\Actions\Analytics\SnapshotDailyBurndownAction;
use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $user;

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

    // ═══════════════════════════════════════════════════════════════════
    // SnapshotDailyBurndownAction — operator precedence bug
    // ═══════════════════════════════════════════════════════════════════

    public function test_snapshot_caches_data_for_active_sprint(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 10,
            'status' => StoryStatus::New,
        ]);

        app(SnapshotDailyBurndownAction::class)->execute($sprint);

        $cacheKey = "burndown.{$sprint->id}.".now()->toDateString();
        $this->assertNotNull(cache($cacheKey));
    }

    public function test_snapshot_skips_closed_sprint(): void
    {
        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Closed,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(1),
        ]);

        app(SnapshotDailyBurndownAction::class)->execute($sprint);

        $cacheKey = "burndown.{$sprint->id}.".now()->toDateString();
        $this->assertNull(cache($cacheKey));
    }

    public function test_snapshot_skips_planning_sprint(): void
    {
        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Planning,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(14),
        ]);

        app(SnapshotDailyBurndownAction::class)->execute($sprint);

        $cacheKey = "burndown.{$sprint->id}.".now()->toDateString();
        $this->assertNull(cache($cacheKey));
    }

    // ═══════════════════════════════════════════════════════════════════
    // CalculateBurndownAction
    // ═══════════════════════════════════════════════════════════════════

    public function test_burndown_returns_correct_structure(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDays(4),
        ]);

        $data = app(CalculateBurndownAction::class)->execute($sprint);

        $this->assertArrayHasKey('sprint', $data);
        $this->assertArrayHasKey('total_points', $data);
        $this->assertArrayHasKey('ideal_line', $data);
        $this->assertArrayHasKey('actual_line', $data);
        $this->assertArrayHasKey('scope_changes', $data);
    }

    public function test_burndown_ideal_line_starts_at_total_and_ends_at_zero(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(5),
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 20,
            'status' => StoryStatus::New,
        ]);

        $data = app(CalculateBurndownAction::class)->execute($sprint);

        $this->assertEquals(20.0, $data['total_points']);
        $this->assertEquals(20, $data['ideal_line'][0]);
        $idealLast = end($data['ideal_line']);
        $this->assertEquals(0, $idealLast);
    }

    public function test_burndown_actual_line_reflects_completed_stories(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
            'start_date' => now()->subDays(3),
            'end_date' => now()->addDays(4),
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 10,
            'status' => StoryStatus::Done,
            'updated_at' => now()->subDay(),
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 5,
            'status' => StoryStatus::New,
        ]);

        $data = app(CalculateBurndownAction::class)->execute($sprint);

        // total = 15, one 10-pt story done = 5 remaining today
        $this->assertEquals(15.0, $data['total_points']);

        // actual_line for today should reflect completed story
        $todayIndex = 3; // subDays(3) → index 3 is today
        $this->assertNotNull($data['actual_line'][$todayIndex]);
        $this->assertEquals(5.0, $data['actual_line'][$todayIndex]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CalculateVelocityAction
    // ═══════════════════════════════════════════════════════════════════

    public function test_velocity_returns_correct_structure(): void
    {
        $data = app(CalculateVelocityAction::class)->execute($this->project);

        $this->assertArrayHasKey('sprints', $data);
        $this->assertArrayHasKey('average_velocity', $data);
    }

    public function test_velocity_calculates_points_from_closed_sprints(): void
    {
        $sprint1 = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Closed,
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint1->id,
            'total_points' => 20,
            'status' => StoryStatus::Done,
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint1->id,
            'total_points' => 10,
            'status' => StoryStatus::InProgress, // Not done, shouldn't count
        ]);

        $sprint2 = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Closed,
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint2->id,
            'total_points' => 30,
            'status' => StoryStatus::Done,
        ]);

        $data = app(CalculateVelocityAction::class)->execute($this->project);

        $this->assertCount(2, $data['sprints']);
        $this->assertEquals(25.0, $data['average_velocity']); // (20+30)/2
    }

    public function test_velocity_ignores_active_sprints(): void
    {
        Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $data = app(CalculateVelocityAction::class)->execute($this->project);

        $this->assertCount(0, $data['sprints']);
        $this->assertEquals(0, $data['average_velocity']);
    }

    public function test_velocity_returns_zero_when_no_closed_sprints(): void
    {
        $data = app(CalculateVelocityAction::class)->execute($this->project);

        $this->assertEquals(0, $data['average_velocity']);
        $this->assertEmpty($data['sprints']);
    }
}
