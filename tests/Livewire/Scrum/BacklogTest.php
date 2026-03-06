<?php

declare(strict_types=1);

namespace Tests\Livewire\Scrum;

use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-03 & L-04: Backlog Livewire component testi.
 *
 * Backlog render, story oluşturma, filtreleme, sıralama ve sprint'e taşıma testleri.
 */
class BacklogTest extends TestCase
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

    public function test_backlog_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/backlog"
        );

        $response->assertStatus(200);
        $response->assertSee('Backlog');
    }

    public function test_backlog_shows_empty_state(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/backlog"
        );

        $response->assertStatus(200);
        $response->assertSee('Backlog boş');
    }

    public function test_backlog_displays_stories(): void
    {
        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Test Backlog Story',
        ]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/backlog"
        );

        $response->assertSee('Test Backlog Story');
    }

    public function test_create_story_via_backlog(): void
    {
        Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->set('newStoryTitle', 'Yeni Test Story')
            ->call('createStory');

        $this->assertDatabaseHas('user_stories', [
            'project_id' => $this->project->id,
            'title' => 'Yeni Test Story',
            'status' => StoryStatus::New->value,
        ]);
    }

    public function test_create_story_validates_title(): void
    {
        Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->set('newStoryTitle', '')
            ->call('createStory')
            ->assertHasErrors(['newStoryTitle']);
    }

    public function test_create_story_resets_form(): void
    {
        Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->set('newStoryTitle', 'Reset Test')
            ->call('createStory')
            ->assertSet('newStoryTitle', '')
            ->assertSet('showCreateForm', false);
    }

    public function test_backlog_filter_by_epic(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);

        $epicStory = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'epic_id' => $epic->id,
            'title' => 'Epic Story',
        ]);

        $otherStory = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Other Story',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->set('filterEpicId', $epic->id);

        // After filtering, the stories computed property should only include epic stories
        $component->assertSee('Epic Story');
    }

    public function test_move_story_to_sprint(): void
    {
        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Planning,
        ]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->call('moveToSprint', $story->id, $sprint->id);

        $this->assertEquals($sprint->id, $story->fresh()->sprint_id);
    }

    public function test_reorder_stories(): void
    {
        $story1 = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'order' => 1,
        ]);
        $story2 = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'order' => 2,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.backlog', ['project' => $this->project])
            ->call('reorder', [$story2->id, $story1->id]);

        $this->assertEquals(1, $story2->fresh()->order);
        $this->assertEquals(2, $story1->fresh()->order);
    }
}
