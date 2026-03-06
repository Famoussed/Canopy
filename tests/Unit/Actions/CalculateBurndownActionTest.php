<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Analytics\CalculateBurndownAction;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-08 & U-09: CalculateBurndownAction testi.
 *
 * İdeal çizgi hesaplama ve scope change algılama dahil burndown verisi doğrulaması.
 */
class CalculateBurndownActionTest extends TestCase
{
    use RefreshDatabase;

    private CalculateBurndownAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculateBurndownAction;
    }

    public function test_ideal_line_starts_at_total_points(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $project->id,
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        UserStory::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 10,
        ]);

        UserStory::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 5,
        ]);

        $result = $this->action->execute($sprint);

        $this->assertEquals(15.0, $result['total_points']);
        $this->assertEquals(15.0, $result['ideal_line'][0]);
        $this->assertEquals(0.0, end($result['ideal_line']));
    }

    public function test_empty_sprint_returns_zero_points(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
        ]);

        $result = $this->action->execute($sprint);

        $this->assertEquals(0.0, $result['total_points']);
        $this->assertNotEmpty($result['ideal_line']);
    }

    public function test_scope_changes_are_included(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $project->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 8,
        ]);

        // Scope change kaydı ekle
        $sprint->scopeChanges()->create([
            'user_story_id' => $story->id,
            'change_type' => 'added',
            'changed_at' => now()->subDay(),
            'changed_by' => $project->owner_id,
        ]);

        $result = $this->action->execute($sprint);

        $this->assertNotEmpty($result['scope_changes']);
        $this->assertEquals('added', $result['scope_changes'][0]['type']);
    }

    public function test_result_structure_is_correct(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
        ]);

        $result = $this->action->execute($sprint);

        $this->assertArrayHasKey('sprint', $result);
        $this->assertArrayHasKey('total_points', $result);
        $this->assertArrayHasKey('ideal_line', $result);
        $this->assertArrayHasKey('actual_line', $result);
        $this->assertArrayHasKey('scope_changes', $result);
        $this->assertArrayHasKey('name', $result['sprint']);
        $this->assertArrayHasKey('start_date', $result['sprint']);
        $this->assertArrayHasKey('end_date', $result['sprint']);
    }
}
