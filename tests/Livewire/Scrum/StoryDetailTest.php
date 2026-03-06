<?php

declare(strict_types=1);

namespace Tests\Livewire\Scrum;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-11 & L-12: StoryDetail Livewire component testi.
 *
 * Story detay sayfasında Epic atama, Task atama ve Task status yönetimi testleri.
 *
 * Test Edilen Senaryolar:
 * - test_story_detail_page_renders: Story detay sayfasının doğru render edilmesi.
 * - test_story_displays_epic_badge: Story'ye bağlı Epic badge gösterimi.
 * - test_assign_epic_to_story: Story'ye epic atama işlemi.
 * - test_remove_epic_from_story: Story'den epic kaldırma işlemi.
 * - test_epic_dropdown_shows_project_epics: Epic dropdown'ında proje epic'leri listelenir.
 * - test_assign_member_to_task: Task'a proje üyesi atama.
 * - test_unassign_member_from_task: Task'tan üye kaldırma.
 * - test_task_shows_assignee_name: Atanmış task'ta üye adı gösterimi.
 * - test_change_task_status_to_in_progress: Task durumunu New'den InProgress'e geçirme.
 * - test_change_task_status_to_done: Task durumunu InProgress'den Done'a geçirme.
 * - test_unassigned_task_cannot_start: Atanmamış task başlatılamaz (BR-16).
 * - test_task_status_dropdown_shows_available_transitions: Her task için sadece geçerli geçişler gösterilir.
 * - test_member_can_change_status_of_task_they_created: Kendi oluşturduğu task'ın durumunu değiştirebilir.
 * - test_member_cannot_change_status_of_task_created_by_others: Başkasının oluşturduğu task'ın durumunu değiştiremez.
 */
class StoryDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private UserStory $story;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->project->memberships()->create([
            'user_id' => $this->user->id,
            'role' => ProjectRole::Owner,
        ]);
        $this->story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ─── Page Render ───

    public function test_story_detail_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/stories/{$this->story->id}"
        );

        $response->assertStatus(200);
        $response->assertSee($this->story->title);
    }

    // ─── Epic Assignment ───

    public function test_story_displays_epic_badge(): void
    {
        $epic = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Auth Epic',
        ]);

        $this->story->update(['epic_id' => $epic->id]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/stories/{$this->story->id}"
        );

        $response->assertSee('Auth Epic');
    }

    public function test_assign_epic_to_story(): void
    {
        $epic = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Payment Epic',
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->set('selectedEpicId', $epic->id)
            ->call('updateEpic');

        $this->assertEquals($epic->id, $this->story->fresh()->epic_id);
    }

    public function test_remove_epic_from_story(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);
        $this->story->update(['epic_id' => $epic->id]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->set('selectedEpicId', '')
            ->call('updateEpic');

        $this->assertNull($this->story->fresh()->epic_id);
    }

    public function test_epic_dropdown_shows_project_epics(): void
    {
        $epic1 = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Frontend Epic',
        ]);
        $epic2 = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Backend Epic',
        ]);

        // Another project's epic — should NOT appear
        Epic::factory()->create(['title' => 'Other Project Epic']);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/stories/{$this->story->id}"
        );

        $response->assertSee('Frontend Epic');
        $response->assertSee('Backend Epic');
        $response->assertDontSee('Other Project Epic');
    }

    // ─── Task Assignment ───

    public function test_assign_member_to_task(): void
    {
        $member = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::New,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('assignTask', $task->id, $member->id);

        $this->assertEquals($member->id, $task->fresh()->assigned_to);
    }

    public function test_unassign_member_from_task(): void
    {
        $member = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        $task = Task::factory()->assigned($member)->create([
            'user_story_id' => $this->story->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('assignTask', $task->id, '');

        $this->assertNull($task->fresh()->assigned_to);
    }

    public function test_task_shows_assignee_name(): void
    {
        $member = User::factory()->create(['name' => 'Ahmet Yılmaz']);
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        Task::factory()->assigned($member)->create([
            'user_story_id' => $this->story->id,
            'title' => 'Design Task',
            'status' => TaskStatus::InProgress,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/stories/{$this->story->id}"
        );

        $response->assertSee('Ahmet Yılmaz');
    }

    // ─── Task Status Management ───

    public function test_change_task_status_to_in_progress(): void
    {
        $task = Task::factory()->assigned($this->user)->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::New,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('changeTaskStatus', $task->id, 'in_progress');

        $this->assertEquals(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_change_task_status_to_done(): void
    {
        $task = Task::factory()->assigned($this->user)->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::InProgress,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('changeTaskStatus', $task->id, 'done');

        $this->assertEquals(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_unassigned_task_cannot_start(): void
    {
        $task = Task::factory()->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::New,
            'assigned_to' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('changeTaskStatus', $task->id, 'in_progress');

        // Task should remain New because of BR-16
        $this->assertEquals(TaskStatus::New, $task->fresh()->status);
    }

    public function test_task_status_dropdown_shows_available_transitions(): void
    {
        $task = Task::factory()->assigned($this->user)->create([
            'user_story_id' => $this->story->id,
            'status' => TaskStatus::New,
        ]);

        // New task can only go to InProgress
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/stories/{$this->story->id}"
        );

        $response->assertSee('Devam Ediyor');
    }

    public function test_member_can_change_status_of_task_they_created(): void
    {
        $member = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        $task = Task::factory()->assigned($member)->create([
            'user_story_id' => $this->story->id,
            'created_by' => $member->id,
            'status' => TaskStatus::New,
        ]);

        Livewire::actingAs($member)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('changeTaskStatus', $task->id, 'in_progress');

        $this->assertEquals(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_member_cannot_change_status_of_task_created_by_others(): void
    {
        $member = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        // Task was created by the owner, not the member
        $task = Task::factory()->assigned($member)->create([
            'user_story_id' => $this->story->id,
            'created_by' => $this->user->id,
            'status' => TaskStatus::New,
        ]);

        Livewire::actingAs($member)
            ->test('scrum.story-detail', [
                'project' => $this->project,
                'story' => $this->story,
            ])
            ->call('changeTaskStatus', $task->id, 'in_progress');

        // Status remains unchanged due to authorization failure
        $this->assertEquals(TaskStatus::New, $task->fresh()->status);
    }
}
