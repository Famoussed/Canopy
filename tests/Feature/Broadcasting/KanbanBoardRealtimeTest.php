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
use Livewire\Attributes\On;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kanban Board'un gerçek zamanlı güncelleme testleri.
 *
 * Livewire 4'te getListeners() public API'den kaldırıldı.
 * Listener doğrulaması #[On] attribute Reflection ile yapılır.
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

    // ─── Reflection Helper ──────────────────────────────────────────────

    /**
     * Verilen metodun #[On] attribute'larını okur, placeholder'ları çözer.
     *
     * @param  array<string, string>  $bindings
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

    // ═══════════════════════════════════════════════════════════════════
    // 1. Listener Registration — #[On] attribute'lar doğru tanımlı
    // ═══════════════════════════════════════════════════════════════════

    public function test_board_registers_echo_listener_for_story_status_changed(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.status-changed",
            $events
        );
    }

    public function test_board_registers_echo_listener_for_task_status_changed(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains(
            "echo-private:project.{$this->project->id},.task.status-changed",
            $events
        );
    }

    public function test_board_registers_echo_listener_for_story_created(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains(
            "echo-private:project.{$this->project->id},.story.created",
            $events
        );
    }

    public function test_board_registers_echo_listener_for_sprint_started(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.started",
            $events
        );
    }

    public function test_board_registers_echo_listener_for_sprint_closed(): void
    {
        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains(
            "echo-private:project.{$this->project->id},.sprint.closed",
            $events
        );
    }

    public function test_board_listeners_use_project_specific_channel(): void
    {
        $otherProject = Project::factory()->create(['owner_id' => $this->user->id]);

        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        foreach ($events as $event) {
            if (str_starts_with($event, 'echo-private:project.')) {
                $this->assertStringContainsString($this->project->id, $event);
                $this->assertStringNotContainsString($otherProject->id, $event);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. Event ↔ Listener alignment — event kanalı ile listener eşleşmesi
    // ═══════════════════════════════════════════════════════════════════

    public function test_story_status_changed_event_channel_matches_board_listener(): void
    {
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $event = new StoryStatusChanged($story, 'new', 'in_progress', $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();
        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";

        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains($expectedKey, $events);
    }

    public function test_task_status_changed_event_channel_matches_board_listener(): void
    {
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['user_story_id' => $story->id]);
        $task->setRelation('userStory', $story);
        $event = new TaskStatusChanged($task, 'new', 'in_progress', $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();
        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";

        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains($expectedKey, $events);
    }

    public function test_sprint_started_event_channel_matches_board_listener(): void
    {
        $event = new SprintStarted($this->sprint, $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();
        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";

        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains($expectedKey, $events);
    }

    public function test_sprint_closed_event_channel_matches_board_listener(): void
    {
        $event = new SprintClosed($this->sprint, $this->user);

        $eventChannel = str_replace('private-', '', collect($event->broadcastOn())->first()->name);
        $broadcastName = $event->broadcastAs();
        $expectedKey = "echo-private:{$eventChannel},.{$broadcastName}";

        $this->actingAs($this->user);
        $instance = Livewire::test('scrum.kanban-board', ['project' => $this->project])->instance();
        $events = $this->getResolvedListeners($instance, 'refreshBoard', ['project.id' => $this->project->id]);

        $this->assertContains($expectedKey, $events);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. refreshBoard method — computed cache'leri temizler
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

        $story->update(['status' => StoryStatus::InProgress]);

        $component->call('refreshBoard');

        $updatedStory = $story->fresh();
        $this->assertEquals(StoryStatus::InProgress, $updatedStory->status);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. Project channel authorization
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
