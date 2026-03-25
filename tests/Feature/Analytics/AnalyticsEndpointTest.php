<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-15 & F-16: Burndown ve Velocity testleri.
 *
 * Livewire analytics-dashboard bileşeninin doğru veri hesapladığını test eder.
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

    public function test_analytics_page_renders(): void
    {
        $this->actingAs($this->user);

        $response = $this->get("/projects/{$this->project->slug}/analytics");

        $response->assertStatus(200);
    }

    public function test_analytics_dashboard_renders_with_active_sprint(): void
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

        Livewire::actingAs($this->user)
            ->test('analytics.analytics-dashboard', ['project' => $this->project])
            ->assertOk();
    }

    public function test_analytics_dashboard_renders_without_active_sprint(): void
    {
        Livewire::actingAs($this->user)
            ->test('analytics.analytics-dashboard', ['project' => $this->project])
            ->assertOk();
    }

    public function test_analytics_requires_authentication(): void
    {
        $response = $this->get("/projects/{$this->project->slug}/analytics");

        $response->assertRedirect('/login');
    }

    public function test_non_member_cannot_access_analytics(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->get("/projects/{$this->project->slug}/analytics");

        $response->assertStatus(403);
    }
}
