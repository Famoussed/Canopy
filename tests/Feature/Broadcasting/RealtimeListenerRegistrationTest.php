<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tüm Livewire bileşenlerinin Echo dinleyicilerini doğru kaydettığını doğrular.
 * getListeners() → "echo-private:project.{id},.event-name" formatı kullanılmalı.
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

    // ═══════════════════════════════════════════════════════════════════
    // sprint-list
    // ═══════════════════════════════════════════════════════════════════

    public function test_sprint_list_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.sprint-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.sprint.started",
            $listeners
        );
    }

    public function test_sprint_list_registers_sprint_closed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.sprint-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.sprint.closed",
            $listeners
        );
    }

    public function test_sprint_list_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.sprint-list', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshSprints', $method);
            }
        }
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

        $listeners = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $story,
        ])->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.task.status-changed",
            $listeners
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

        $listeners = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $story,
        ])->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.task.assigned",
            $listeners
        );
    }

    public function test_story_detail_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);

        $sprint = \App\Models\Sprint::factory()->create(['project_id' => $this->project->id]);
        $story = \App\Models\UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $listeners = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $story,
        ])->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshStoryTasks', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // backlog
    // ═══════════════════════════════════════════════════════════════════

    public function test_backlog_registers_story_created_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.backlog', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.story.created",
            $listeners
        );
    }

    public function test_backlog_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.backlog', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshBacklog', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // issue-list
    // ═══════════════════════════════════════════════════════════════════

    public function test_issue_list_registers_issue_created_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('issues.issue-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.issue.created",
            $listeners
        );
    }

    public function test_issue_list_registers_issue_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('issues.issue-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.issue.status-changed",
            $listeners
        );
    }

    public function test_issue_list_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('issues.issue-list', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshIssues', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // analytics-dashboard
    // ═══════════════════════════════════════════════════════════════════

    public function test_analytics_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $listeners
        );
    }

    public function test_analytics_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.sprint.started",
            $listeners
        );
    }

    public function test_analytics_registers_sprint_closed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.sprint.closed",
            $listeners
        );
    }

    public function test_analytics_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('analytics.analytics-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshAnalytics', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // epic-list (NEW — currently has no listeners)
    // ═══════════════════════════════════════════════════════════════════

    public function test_epic_list_registers_story_created_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.epic-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.story.created",
            $listeners
        );
    }

    public function test_epic_list_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.epic-list', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $listeners
        );
    }

    public function test_epic_list_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('scrum.epic-list', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshEpics', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // project-dashboard (NEW — currently has no listeners)
    // ═══════════════════════════════════════════════════════════════════

    public function test_project_dashboard_registers_story_status_changed_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $listeners
        );
    }

    public function test_project_dashboard_registers_sprint_started_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.sprint.started",
            $listeners
        );
    }

    public function test_project_dashboard_registers_issue_created_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.issue.created",
            $listeners
        );
    }

    public function test_project_dashboard_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-dashboard', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshDashboard', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // project-settings (NEW — currently has no listeners)
    // ═══════════════════════════════════════════════════════════════════

    public function test_project_settings_registers_member_added_listener(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-settings', ['project' => $this->project])
            ->instance()->getListeners();

        $this->assertArrayHasKey(
            "echo-private:project.{$this->project->id},.member.added",
            $listeners
        );
    }

    public function test_project_settings_listeners_target_refresh_method(): void
    {
        $this->actingAs($this->user);
        $listeners = Livewire::test('projects.project-settings', ['project' => $this->project])
            ->instance()->getListeners();

        foreach ($listeners as $key => $method) {
            if (str_starts_with($key, 'echo-private:')) {
                $this->assertEquals('refreshMembers', $method);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Cross-component: All listeners use correct project-specific channel
    // ═══════════════════════════════════════════════════════════════════

    public function test_all_listeners_use_dot_prefix_for_custom_event_names(): void
    {
        $this->actingAs($this->user);

        $sprint = \App\Models\Sprint::factory()->create(['project_id' => $this->project->id]);
        $story = \App\Models\UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $components = [
            ['scrum.sprint-list', ['project' => $this->project]],
            ['scrum.story-detail', ['project' => $this->project, 'story' => $story]],
            ['scrum.backlog', ['project' => $this->project]],
            ['issues.issue-list', ['project' => $this->project]],
            ['analytics.analytics-dashboard', ['project' => $this->project]],
            ['scrum.epic-list', ['project' => $this->project]],
            ['projects.project-dashboard', ['project' => $this->project]],
            ['projects.project-settings', ['project' => $this->project]],
        ];

        foreach ($components as [$component, $params]) {
            $listeners = Livewire::test($component, $params)->instance()->getListeners();

            foreach (array_keys($listeners) as $key) {
                if (str_starts_with($key, 'echo-private:')) {
                    // After channel name and comma, event name MUST start with dot
                    $this->assertMatchesRegularExpression(
                        '/^echo-private:[\w.\-]+,\.[\w.\-]+$/',
                        $key,
                        "Component [{$component}] listener [{$key}] missing dot prefix"
                    );
                }
            }
        }
    }
}
