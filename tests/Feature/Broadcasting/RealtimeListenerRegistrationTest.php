<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Attributes\On;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tüm Livewire bileşenlerinin Echo dinleyicilerini #[On] attribute olarak
 * doğru kaydettiğini doğrular.
 *
 * Livewire 4'te getListeners() public API'den kaldırıldı.
 * #[On('echo-private:channel.{property.id},.event')] attribute sözdizimi
 * Reflection API ile doğrulanır.
 */
class RealtimeListenerRegistrationTest extends TestCase
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

    // ─── Reflection Helpers ─────────────────────────────────────────────

    /**
     * Verilen component metodunun #[On] attribute'larından event string'lerini döner.
     * Placeholder'lar ({project.id} gibi) gerçek değerlerle değiştirilir.
     *
     * @param  array<string, string>  $bindings  ['project.id' => 'uuid', ...]
     * @return list<string>
     */
    private function getResolvedListeners(object $instance, string $method, array $bindings = []): array
    {
        $reflection = new \ReflectionMethod($instance, $method);

        $events = [];
        foreach ($reflection->getAttributes(On::class) as $attr) {
            $event = $attr->newInstance()->event;
            foreach ($bindings as $placeholder => $value) {
                $event = str_replace('{'.$placeholder.'}', $value, $event);
            }
            $events[] = $event;
        }

        return $events;
    }

    /** @return array<string, string> */
    private function projectBindings(): array
    {
        return ['project.id' => $this->project->id];
    }

    // ═══════════════════════════════════════════════════════════════════
    // sprint-list
    // ═══════════════════════════════════════════════════════════════════

    public function test_sprint_list_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.sprint-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshSprints', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.started",
            $events
        );
    }

    public function test_sprint_list_registers_sprint_closed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.sprint-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshSprints', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.closed",
            $events
        );
    }

    public function test_sprint_list_registers_scope_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.sprint-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshSprints', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.scope-changed",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // story-detail
    // ═══════════════════════════════════════════════════════════════════

    public function test_story_detail_registers_task_status_changed_listener(): void
    {
        $this->actingAs($this->user);

        $sprint = \App\Models\Sprint::factory()->create(['project_id' => $this->project->id]);
        $story = \App\Models\UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $instance = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $story,
        ])->instance();

        $events = $this->getResolvedListeners($instance, 'refreshStoryTasks', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.task.status-changed",
            $events
        );
    }

    public function test_story_detail_registers_task_assigned_listener(): void
    {
        $this->actingAs($this->user);

        $sprint = \App\Models\Sprint::factory()->create(['project_id' => $this->project->id]);
        $story = \App\Models\UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $instance = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $story,
        ])->instance();

        $events = $this->getResolvedListeners($instance, 'refreshStoryTasks', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.task.assigned",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // backlog
    // ═══════════════════════════════════════════════════════════════════

    public function test_backlog_registers_story_created_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.backlog', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBacklog', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.created",
            $events
        );
    }

    public function test_backlog_registers_scope_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.backlog', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBacklog', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.scope-changed",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // issue-list
    // ═══════════════════════════════════════════════════════════════════

    public function test_issue_list_registers_issue_created_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('issues.issue-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshIssues', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.issue.created",
            $events
        );
    }

    public function test_issue_list_registers_issue_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('issues.issue-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshIssues', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.issue.status-changed",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // analytics-dashboard
    // ═══════════════════════════════════════════════════════════════════

    public function test_analytics_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshAnalytics', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $events
        );
    }

    public function test_analytics_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshAnalytics', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.started",
            $events
        );
    }

    public function test_analytics_registers_sprint_closed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshAnalytics', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.closed",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // epic-list
    // ═══════════════════════════════════════════════════════════════════

    public function test_epic_list_registers_story_created_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.epic-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshEpics', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.created",
            $events
        );
    }

    public function test_epic_list_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.epic-list', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshEpics', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // project-dashboard
    // ═══════════════════════════════════════════════════════════════════

    public function test_project_dashboard_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('projects.project-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshDashboard', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $events
        );
    }

    public function test_project_dashboard_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('projects.project-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshDashboard', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.started",
            $events
        );
    }

    public function test_project_dashboard_registers_issue_created_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('projects.project-dashboard', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshDashboard', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.issue.created",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // project-settings
    // ═══════════════════════════════════════════════════════════════════

    public function test_project_settings_registers_member_added_listener(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('projects.project-settings', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshMembers', $this->projectBindings());

        $this->assertContains(
            "echo-private:project.{$this->project->id},.member.added",
            $events
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // Cross-component: dot prefix for custom event names
    // ═══════════════════════════════════════════════════════════════════

    public function test_all_listeners_use_dot_prefix_for_custom_event_names(): void
    {
        $this->actingAs($this->user);

        $sprint = \App\Models\Sprint::factory()->create(['project_id' => $this->project->id]);
        $story = \App\Models\UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $componentMethods = [
            ['scrum.sprint-list', ['project' => $this->project], 'refreshSprints'],
            ['scrum.story-detail', ['project' => $this->project, 'story' => $story], 'refreshStoryTasks'],
            ['scrum.backlog', ['project' => $this->project], 'refreshBacklog'],
            ['issues.issue-list', ['project' => $this->project], 'refreshIssues'],
            ['analytics.analytics-dashboard', ['project' => $this->project], 'refreshAnalytics'],
            ['scrum.epic-list', ['project' => $this->project], 'refreshEpics'],
            ['projects.project-dashboard', ['project' => $this->project], 'refreshDashboard'],
            ['projects.project-settings', ['project' => $this->project], 'refreshMembers'],
        ];

        foreach ($componentMethods as [$component, $params, $method]) {
            $instance = Livewire::test($component, $params)->instance();
            $events = $this->getResolvedListeners($instance, $method, $this->projectBindings());

            foreach ($events as $event) {
                if (str_starts_with($event, 'echo-private:')) {
                    $this->assertMatchesRegularExpression(
                        '/^echo-private:[\w.\-]+,\.[\w.\-]+$/',
                        $event,
                        "Component [{$component}] listener [{$event}] missing dot prefix"
                    );
                }
            }
        }
    }
}
