<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Enums\TaskStatus;
use App\Events\Issue\IssueCreated;
use App\Events\Issue\IssueStatusChanged;
use App\Events\Project\MemberAdded;
use App\Events\Project\MemberRemoved;
use App\Events\Project\ProjectCreated;
use App\Events\Scrum\SprintClosed;
use App\Events\Scrum\SprintStarted;
use App\Events\Scrum\StoryCreated;
use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskAssigned;
use App\Events\Scrum\TaskStatusChanged;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\IssueService;
use App\Services\MembershipService;
use App\Services\ProjectService;
use App\Services\SprintService;
use App\Services\TaskService;
use App\Services\UserStoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tüm servislerin doğru event'leri dispatch ettiğini doğrulayan testler.
 */
class EventDispatchTest extends TestCase
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

    // ─── Story Events ───

    public function test_story_creation_dispatches_story_created_event(): void
    {
        Event::fake([StoryCreated::class]);

        app(UserStoryService::class)->create(
            ['title' => 'Test story'],
            $this->project,
            $this->user,
        );

        Event::assertDispatched(StoryCreated::class, function ($event) {
            return $event->story->title === 'Test story'
                && $event->creator->id === $this->user->id;
        });
    }

    public function test_story_status_change_dispatches_event(): void
    {
        Event::fake([StoryStatusChanged::class]);

        $story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        app(UserStoryService::class)->changeStatus($story, StoryStatus::InProgress, $this->user);

        Event::assertDispatched(StoryStatusChanged::class, function ($event) use ($story) {
            return $event->story->id === $story->id
                && $event->oldStatus === 'new'
                && $event->newStatus === 'in_progress';
        });
    }

    // ─── Task Events ───

    public function test_task_status_change_dispatches_event(): void
    {
        Event::fake([TaskStatusChanged::class]);

        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->assigned($this->user)->create([
            'user_story_id' => $story->id,
        ]);

        app(TaskService::class)->changeStatus($task, TaskStatus::InProgress, $this->user);

        Event::assertDispatched(TaskStatusChanged::class, function ($event) use ($task) {
            return $event->task->id === $task->id
                && $event->oldStatus === 'new'
                && $event->newStatus === 'in_progress';
        });
    }

    public function test_task_assignment_dispatches_event(): void
    {
        Event::fake([TaskAssigned::class]);

        $assignee = User::factory()->create();
        $story = UserStory::factory()->create(['project_id' => $this->project->id]);
        $task = Task::factory()->create(['user_story_id' => $story->id]);

        app(TaskService::class)->assign($task, $assignee, $this->user);

        Event::assertDispatched(TaskAssigned::class, function ($event) use ($task, $assignee) {
            return $event->task->id === $task->id
                && $event->assignee->id === $assignee->id;
        });
    }

    // ─── Sprint Events ───

    public function test_sprint_start_dispatches_event(): void
    {
        Event::fake([SprintStarted::class]);

        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Planning,
        ]);

        app(SprintService::class)->start($sprint, $this->user);

        Event::assertDispatched(SprintStarted::class, function ($event) use ($sprint) {
            return $event->sprint->id === $sprint->id
                && $event->startedBy->id === $this->user->id;
        });
    }

    public function test_sprint_close_dispatches_event(): void
    {
        Event::fake([SprintClosed::class]);

        $sprint = Sprint::factory()->active()->create([
            'project_id' => $this->project->id,
        ]);

        app(SprintService::class)->close($sprint, $this->user);

        Event::assertDispatched(SprintClosed::class, function ($event) use ($sprint) {
            return $event->sprint->id === $sprint->id
                && $event->closedBy->id === $this->user->id;
        });
    }

    // ─── Project Events ───

    public function test_project_creation_dispatches_event(): void
    {
        Event::fake([ProjectCreated::class]);

        app(ProjectService::class)->create(
            ['name' => 'Broadcast Test Project'],
            $this->user,
        );

        Event::assertDispatched(ProjectCreated::class, function ($event) {
            return $event->project->name === 'Broadcast Test Project'
                && $event->creator->id === $this->user->id;
        });
    }

    // ─── Member Events ───

    public function test_member_added_dispatches_event(): void
    {
        Event::fake([MemberAdded::class]);

        $newMember = User::factory()->create();

        app(MembershipService::class)->add(
            $this->project,
            $newMember,
            ProjectRole::Member,
            $this->user,
        );

        Event::assertDispatched(MemberAdded::class, function ($event) use ($newMember) {
            return $event->member->id === $newMember->id
                && $event->addedBy->id === $this->user->id;
        });
    }

    public function test_member_removed_dispatches_event(): void
    {
        Event::fake([MemberRemoved::class]);

        $member = User::factory()->create();
        $this->project->memberships()->create([
            'user_id' => $member->id,
            'role' => ProjectRole::Member,
        ]);

        app(MembershipService::class)->remove($this->project, $member, $this->user);

        Event::assertDispatched(MemberRemoved::class, function ($event) use ($member) {
            return $event->member->id === $member->id
                && $event->removedBy->id === $this->user->id;
        });
    }

    // ─── Issue Events ───

    public function test_issue_creation_dispatches_event(): void
    {
        Event::fake([IssueCreated::class]);

        app(IssueService::class)->create(
            ['title' => 'Test Bug', 'type' => IssueType::Bug],
            $this->project,
            $this->user,
        );

        Event::assertDispatched(IssueCreated::class, function ($event) {
            return $event->issue->title === 'Test Bug'
                && $event->creator->id === $this->user->id;
        });
    }

    public function test_issue_status_change_dispatches_event(): void
    {
        Event::fake([IssueStatusChanged::class]);

        $issue = \App\Models\Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        app(IssueService::class)->changeStatus($issue, IssueStatus::InProgress, $this->user);

        Event::assertDispatched(IssueStatusChanged::class, function ($event) use ($issue) {
            return $event->issue->id === $issue->id
                && $event->oldStatus === 'new'
                && $event->newStatus === 'in_progress';
        });
    }
}
