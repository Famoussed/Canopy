<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Events\Issue\IssueCreated;
use App\Events\Issue\IssueStatusChanged;
use App\Events\Project\MemberAdded;
use App\Events\Scrum\SprintClosed;
use App\Events\Scrum\SprintStarted;
use App\Events\Scrum\StoryCreated;
use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskAssigned;
use App\Events\Scrum\TaskStatusChanged;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event'lerin ShouldBroadcast implement ettiğini, doğru kanallara
 * doğru payload ile yayın yaptığını doğrulayan testler.
 */
class BroadcastingTest extends TestCase
{
    use RefreshDatabase;
    // ─── ShouldBroadcast Interface ───

    public function test_story_status_changed_implements_should_broadcast(): void
    {
        $event = $this->makeStoryStatusChangedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_task_status_changed_implements_should_broadcast(): void
    {
        $event = $this->makeTaskStatusChangedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_sprint_started_implements_should_broadcast(): void
    {
        $event = $this->makeSprintStartedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_sprint_closed_implements_should_broadcast(): void
    {
        $event = $this->makeSprintClosedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_story_created_implements_should_broadcast(): void
    {
        $event = $this->makeStoryCreatedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_task_assigned_implements_should_broadcast(): void
    {
        $event = $this->makeTaskAssignedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_member_added_implements_should_broadcast(): void
    {
        $event = $this->makeMemberAddedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_issue_created_implements_should_broadcast(): void
    {
        $event = $this->makeIssueCreatedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_issue_status_changed_implements_should_broadcast(): void
    {
        $event = $this->makeIssueStatusChangedEvent();

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    // ─── broadcastOn() — Project Channel ───

    public function test_story_status_changed_broadcasts_on_project_channel(): void
    {
        $event = $this->makeStoryStatusChangedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->story->project_id);
    }

    public function test_task_status_changed_broadcasts_on_project_channel(): void
    {
        $event = $this->makeTaskStatusChangedEvent();
        $projectId = $event->task->userStory->project_id;

        $this->assertBroadcastsOnProjectChannel($event, $projectId);
    }

    public function test_sprint_started_broadcasts_on_project_channel(): void
    {
        $event = $this->makeSprintStartedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->sprint->project_id);
    }

    public function test_sprint_closed_broadcasts_on_project_channel(): void
    {
        $event = $this->makeSprintClosedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->sprint->project_id);
    }

    public function test_story_created_broadcasts_on_project_channel(): void
    {
        $event = $this->makeStoryCreatedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->story->project_id);
    }

    public function test_issue_created_broadcasts_on_project_channel(): void
    {
        $event = $this->makeIssueCreatedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->issue->project_id);
    }

    public function test_issue_status_changed_broadcasts_on_project_channel(): void
    {
        $event = $this->makeIssueStatusChangedEvent();

        $this->assertBroadcastsOnProjectChannel($event, $event->issue->project_id);
    }

    // ─── broadcastOn() — User Channel ───

    public function test_task_assigned_broadcasts_on_user_channel(): void
    {
        $event = $this->makeTaskAssignedEvent();

        $channels = $event->broadcastOn();
        $channelNames = collect($channels)->map(fn ($ch) => $ch->name)->toArray();

        $this->assertContains("private-user.{$event->assignee->id}", $channelNames);
    }

    public function test_member_added_broadcasts_on_user_channel(): void
    {
        $event = $this->makeMemberAddedEvent();

        $channels = $event->broadcastOn();
        $channelNames = collect($channels)->map(fn ($ch) => $ch->name)->toArray();

        $this->assertContains("private-user.{$event->member->id}", $channelNames);
    }

    // ─── broadcastAs() ───

    public function test_story_status_changed_broadcast_name(): void
    {
        $event = $this->makeStoryStatusChangedEvent();

        $this->assertEquals('story.status-changed', $event->broadcastAs());
    }

    public function test_task_status_changed_broadcast_name(): void
    {
        $event = $this->makeTaskStatusChangedEvent();

        $this->assertEquals('task.status-changed', $event->broadcastAs());
    }

    public function test_sprint_started_broadcast_name(): void
    {
        $event = $this->makeSprintStartedEvent();

        $this->assertEquals('sprint.started', $event->broadcastAs());
    }

    public function test_sprint_closed_broadcast_name(): void
    {
        $event = $this->makeSprintClosedEvent();

        $this->assertEquals('sprint.closed', $event->broadcastAs());
    }

    public function test_story_created_broadcast_name(): void
    {
        $event = $this->makeStoryCreatedEvent();

        $this->assertEquals('story.created', $event->broadcastAs());
    }

    public function test_task_assigned_broadcast_name(): void
    {
        $event = $this->makeTaskAssignedEvent();

        $this->assertEquals('task.assigned', $event->broadcastAs());
    }

    public function test_member_added_broadcast_name(): void
    {
        $event = $this->makeMemberAddedEvent();

        $this->assertEquals('member.added', $event->broadcastAs());
    }

    public function test_issue_created_broadcast_name(): void
    {
        $event = $this->makeIssueCreatedEvent();

        $this->assertEquals('issue.created', $event->broadcastAs());
    }

    public function test_issue_status_changed_broadcast_name(): void
    {
        $event = $this->makeIssueStatusChangedEvent();

        $this->assertEquals('issue.status-changed', $event->broadcastAs());
    }

    // ─── broadcastWith() payload ───

    public function test_story_status_changed_payload_contains_required_fields(): void
    {
        $event = $this->makeStoryStatusChangedEvent();

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('story_id', $payload);
        $this->assertArrayHasKey('old_status', $payload);
        $this->assertArrayHasKey('new_status', $payload);
        $this->assertArrayHasKey('changed_by', $payload);
    }

    public function test_task_status_changed_payload_contains_required_fields(): void
    {
        $event = $this->makeTaskStatusChangedEvent();

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('task_id', $payload);
        $this->assertArrayHasKey('old_status', $payload);
        $this->assertArrayHasKey('new_status', $payload);
        $this->assertArrayHasKey('changed_by', $payload);
    }

    public function test_task_assigned_payload_contains_required_fields(): void
    {
        $event = $this->makeTaskAssignedEvent();

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('task_id', $payload);
        $this->assertArrayHasKey('assignee_id', $payload);
        $this->assertArrayHasKey('assigned_by', $payload);
    }

    public function test_issue_created_payload_contains_required_fields(): void
    {
        $event = $this->makeIssueCreatedEvent();

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('issue_id', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('created_by', $payload);
    }

    public function test_issue_status_changed_payload_contains_required_fields(): void
    {
        $event = $this->makeIssueStatusChangedEvent();

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('issue_id', $payload);
        $this->assertArrayHasKey('old_status', $payload);
        $this->assertArrayHasKey('new_status', $payload);
        $this->assertArrayHasKey('changed_by', $payload);
    }

    // ─── Helpers ───

    private function assertBroadcastsOnProjectChannel(object $event, string $projectId): void
    {
        $channels = $event->broadcastOn();
        $channelNames = collect($channels)->map(fn ($ch) => $ch->name)->toArray();

        $this->assertContains("private-project.{$projectId}", $channelNames);
    }

    private function makeStoryStatusChangedEvent(): StoryStatusChanged
    {
        $story = UserStory::factory()->create();
        $user = User::factory()->create();

        return new StoryStatusChanged($story, 'new', 'in_progress', $user);
    }

    private function makeTaskStatusChangedEvent(): TaskStatusChanged
    {
        $story = UserStory::factory()->create();
        $task = Task::factory()->create(['user_story_id' => $story->id]);
        $task->setRelation('userStory', $story);
        $user = User::factory()->create();

        return new TaskStatusChanged($task, 'new', 'in_progress', $user);
    }

    private function makeSprintStartedEvent(): SprintStarted
    {
        $sprint = Sprint::factory()->create();
        $user = User::factory()->create();

        return new SprintStarted($sprint, $user);
    }

    private function makeSprintClosedEvent(): SprintClosed
    {
        $sprint = Sprint::factory()->create();
        $user = User::factory()->create();

        return new SprintClosed($sprint, $user);
    }

    private function makeStoryCreatedEvent(): StoryCreated
    {
        $story = UserStory::factory()->create();
        $user = User::factory()->create();

        return new StoryCreated($story, $user);
    }

    private function makeTaskAssignedEvent(): TaskAssigned
    {
        $task = Task::factory()->create();
        $assignee = User::factory()->create();
        $assignedBy = User::factory()->create();

        return new TaskAssigned($task, $assignee, $assignedBy);
    }

    private function makeMemberAddedEvent(): MemberAdded
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $membership = $project->memberships()->create([
            'user_id' => $member->id,
            'role' => \App\Enums\ProjectRole::Member,
        ]);
        $addedBy = User::factory()->create();

        return new MemberAdded($project, $member, $membership, $addedBy);
    }

    private function makeIssueCreatedEvent(): IssueCreated
    {
        $issue = Issue::factory()->create();
        $user = User::factory()->create();

        return new IssueCreated($issue, $user);
    }

    private function makeIssueStatusChangedEvent(): IssueStatusChanged
    {
        $issue = Issue::factory()->create();
        $user = User::factory()->create();

        return new IssueStatusChanged($issue, 'new', 'in_progress', $user);
    }
}
