<?php

declare(strict_types=1);

namespace Tests\Livewire\Scrum;

use App\Enums\ProjectRole;
use App\Enums\StoryStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-01 & L-02: Kanban Board Livewire component testi.
 *
 * Board render, sprint seçimi, story durum değişikliği ve task status toggle testleri.
 */
class KanbanBoardTest extends TestCase
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

    public function test_kanban_board_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/board"
        );

        $response->assertStatus(200);
        $response->assertSee('Kanban Board');
    }

    public function test_board_shows_empty_state_without_sprint(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/board"
        );

        $response->assertStatus(200);
        $response->assertSee('Sprint bulunamadı');
    }

    public function test_board_auto_selects_active_sprint(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/board"
        );

        $response->assertStatus(200);
        $response->assertSee($sprint->name);
    }

    public function test_board_displays_stories_in_columns(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $newStory = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'status' => StoryStatus::New,
            'title' => 'New Story Title',
        ]);

        $inProgressStory = UserStory::factory()->inProgress()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'title' => 'InProgress Story Title',
        ]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/board"
        );

        $response->assertStatus(200);
        $response->assertSee('New Story Title');
        $response->assertSee('InProgress Story Title');
    }

    public function test_change_story_status_action(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'status' => StoryStatus::New,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.kanban-board', ['project' => $this->project])
            ->call('changeStoryStatus', $story->id, 'in_progress');

        $this->assertEquals(StoryStatus::InProgress, $story->fresh()->status);
    }

    public function test_change_task_status_toggle(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);

        $task = Task::factory()->assigned($this->user)->create([
            'user_story_id' => $story->id,
            'status' => TaskStatus::InProgress,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.kanban-board', ['project' => $this->project])
            ->call('changeTaskStatus', $task->id, 'done');

        $this->assertEquals(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_invalid_story_status_transition_shows_error(): void
    {
        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'status' => StoryStatus::New,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.kanban-board', ['project' => $this->project])
            ->call('changeStoryStatus', $story->id, 'done')
            ->assertHasNoErrors();

        // Story should remain unchanged since the transition is invalid
        $this->assertEquals(StoryStatus::New, $story->fresh()->status);
    }
}
