<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-15 & F-16: Burndown ve Velocity API endpoint testleri.
 *
 * Analytics endpoint'lerinin doğru veri döndüğünü test eder.
 */
class AnalyticsEndpointTest extends TestCase
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

    public function test_burndown_endpoint_returns_data(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
        ]);

        UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 10,
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint->id}/burndown"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    public function test_velocity_endpoint_returns_data(): void
    {
        $sprint = Sprint::factory()->closed()->create([
            'project_id' => $this->project->id,
        ]);

        UserStory::factory()->done()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'total_points' => 20,
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/projects/{$this->project->slug}/velocity"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    public function test_burndown_requires_authentication(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint->id}/burndown"
        );

        $response->assertStatus(401);
    }

    public function test_non_member_cannot_access_burndown(): void
    {
        $outsider = User::factory()->create();
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($outsider)->getJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint->id}/burndown"
        );

        $response->assertStatus(403);
    }
}
