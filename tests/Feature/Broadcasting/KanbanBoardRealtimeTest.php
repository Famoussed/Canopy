<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Enums\ProjectRole;
use App\Enums\StoryStatus;
use App\Events\Scrum\SprintClosed;
use App\Events\Scrum\SprintStarted;
use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskStatusChanged;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kanban Board'un gerçek zamanlı güncelleme testleri.
 *
 * Board, project kanalındaki event'leri dinlemeli ve board'u yenilemeli.
 */
class KanbanBoardRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Sprint $sprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->project->memberships()->create([
            'user_id' => $this->user->id,
            'role' => ProjectRole::Owner,
        ]);
        $this->sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. Listener Registration — getListeners() returns correct channel/event pairs
    // ═══════════════════════════════════════════════════════════════════

    public function test_board_registers_echo_listener_for_story_status_changed(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:project.{$this->project->id},.story.status-changed";
        $this->assertArrayHasKey($expectedKey, $listeners);
        $this->assertEquals('refreshBoard', $listeners[$expectedKey]);
    }

    public function test_board_registers_echo_listener_for_task_status_changed(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:project.{$this->project->id},.task.status-changed";
        $this->assertArrayHasKey($expectedKey, $listeners);
        $this->assertEquals('refreshBoard', $listeners[$expectedKey]);
    }

    public function test_board_registers_echo_listener_for_story_created(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:project.{$this->project->id},.story.created";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_board_registers_echo_listener_for_sprint_started(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:project.{$this->project->id},.sprint.started";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_board_registers_echo_listener_for_sprint_closed(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:project.{$this->project->id},.sprint.closed";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_board_listeners_use_project_specific_channel(): void
    {
        $otherProject = Project::factory()->create(['owner_id' => $this->user->id]);

        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        // Each listener key must contain THIS project's id, not another project's
        foreach (array_keys($listeners) as $key) {
            if (str_starts_with($key, 'echo-private:project.')) {
                $this->assertStringContainsString($this->project->id, $key);
                $this->assertStringNotContainsString($otherProject->id, $key);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. Event ↔ Listener alignment
    // ═══════════════════════════════════════════════════════════════════

    public function test_story_status_changed_event_channel_matches_board_listener(): void
    {
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $event = new StoryStatusChanged($story, 'new', 'in_progress', $this->user);

        // Event channel
        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        // Event broadcast name
        $broadcastName = $event->broadcastAs();

        // Livewire listener
        $this->actingAs($this->user);
        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_task_status_changed_event_channel_matches_board_listener(): void
    {
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['user_story_id' => $story->id]);
        $task->setRelation('userStory', $story);
        $event = new TaskStatusChanged($task, 'new', 'in_progress', $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();

        $this->actingAs($this->user);
        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_sprint_started_event_channel_matches_board_listener(): void
    {
        $event = new SprintStarted($this->sprint, $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();

        $this->actingAs($this->user);
        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    public function test_sprint_closed_event_channel_matches_board_listener(): void
    {
        $event = new SprintClosed($this->sprint, $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();

        $this->actingAs($this->user);
        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";
        $this->assertArrayHasKey($expectedKey, $listeners);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. refreshBoard method — clears computed caches
    // ═══════════════════════════════════════════════════════════════════

    public function test_refresh_board_can_be_called(): void
    {
        $this->actingAs($this->user);

        Livewire::test('scrum.kanban-board', ['project' => $this->project])
            ->call('refreshBoard')
            ->assertOk();
    }

    public function test_refresh_board_rerenders_with_latest_data(): void
    {
        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $this->sprint->id,
            'status' => StoryStatus::New,
            'title' => 'Board Story',
        ]);

        $this->actingAs($this->user);

        $component = Livewire::test('scrum.kanban-board', ['project' => $this->project]);
        $component->assertSee('Board Story');

        // Simulate another user changing the status directly in DB
        $story->update(['status' => StoryStatus::InProgress]);

        // After refreshBoard (triggered by Echo event), board should show new state
        $component->call('refreshBoard');

        // Re-render should pick up the new status
        $updatedStory = $story->fresh();
        $this->assertEquals(StoryStatus::InProgress, $updatedStory->status);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. Project channel authorization for board users
    // ═══════════════════════════════════════════════════════════════════

    public function test_project_member_can_access_project_channel(): void
    {
        $this->actingAs($this->user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-project.{$this->project->id}",
            'socket_id' => '12345.67890',
        ])->assertOk();
    }

    public function test_non_project_member_cannot_access_project_channel(): void
    {
        config(['broadcasting.default' => 'reverb']);

        $nonMember = User::factory()->create();

        $this->actingAs($nonMember);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-project.{$this->project->id}",
            'socket_id' => '12345.67890',
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_project_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-project.{$this->project->id}",
            'socket_id' => '12345.67890',
        ])->assertUnauthorized();
    }
}
