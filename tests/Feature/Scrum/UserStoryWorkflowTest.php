<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use App\Enums\StoryStatus;
use App\Services\UserStoryService;
use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Story Workflow Testleri
 *
 * Bu test sınıfı; User Story kayıtlarının CRUD işlemleri, Backlog'a varsayılan kaydı
 * ve Scrum (durum geçişleri) kurallarının düzgün çalışıp çalışmadığını kontrol eder.
 *
 * Test Edilen Senaryolar:
 * - test_story_created_in_backlog_with_new_status: Yeni story'nin sprint'siz backlog'a eklendiği.
 * - test_story_can_be_moved_to_sprint: Story'nin Backlog'dan Sprint'e alınması.
 * - test_valid_status_transitions: Story durumlarının sırayla geçişi.
 * - test_invalid_status_transition_throws_exception: Yasaklı geçişlerin engellenmesi.
 */
class UserStoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected UserStoryService $storyService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->storyService = app(UserStoryService::class);
    }

    public function test_story_created_in_backlog_with_new_status(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'title' => 'Test Story',
        ];

        $story = $this->storyService->create($data, $this->project, $this->user);

        $this->assertInstanceOf(UserStory::class, $story);
        $this->assertDatabaseHas('user_stories', [
            'id' => $story->id,
            'project_id' => $this->project->id,
            'status' => 'new',
            'sprint_id' => null, // Backlog varsayımı
        ]);
    }

    public function test_story_can_be_moved_to_sprint(): void
    {
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $sprint = Sprint::factory()->create(['project_id' => $this->project->id]);

        $this->storyService->moveToSprint($story, $sprint, $this->user);

        $this->assertEquals($sprint->id, $story->fresh()->sprint_id);
    }

    public function test_valid_status_transitions(): void
    {
        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'status' => StoryStatus::New->value
        ]);

        $this->storyService->changeStatus($story, StoryStatus::InProgress, $this->user);
        $this->assertEquals(StoryStatus::InProgress, $story->fresh()->status);

        $this->storyService->changeStatus($story, StoryStatus::Done, $this->user);
        $this->assertEquals(StoryStatus::Done, $story->fresh()->status);
    }

    public function test_invalid_status_transition_throws_exception(): void
    {
        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'status' => StoryStatus::Done->value
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        // Done status'ündeki bir User Story tekrar New'e dönemez
        $this->storyService->changeStatus($story, StoryStatus::New, $this->user);
    }
}
