<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Enums\IssueStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Services\IssueService;
use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue Workflow Testleri
 *
 * Bu test sınıfı; Issue (Hata/Görev) kayıtlarının oluşturulması, güncellenmesi,
 * durum geçişleri (State Machine kuralları) ve filtreleme işlemlerinin
 * projenin iş kurallarına uygun şekilde çalıştığını doğrular.
 *
 * Test Edilen Senaryolar:
 * - test_user_can_create_issue: Yeni bir issue oluşturulması ve varsayılanların kontrolü.
 * - test_issue_can_be_updated: Mevcut bir issue'nun güncellenmesi.
 * - test_issue_can_be_deleted: Issue'nun silinmesi.
 * - test_valid_status_transitions: Geçerli durum geçişleri (Open -> In Progress vb.).
 * - test_invalid_status_transition_throws_exception: Geçersiz durum geçişinde hata fırlatılması.
 */
class IssueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected IssueService $issueService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->issueService = app(IssueService::class);
    }

    public function test_user_can_create_issue(): void
    {
        $data = [
            'title' => 'Test Issue',
            'description' => 'Test Description',
            'type' => IssueType::Bug->value,
            'priority' => IssuePriority::High->value,
            'severity' => IssueSeverity::Critical->value,
        ];

        $issue = $this->issueService->create($data, $this->project, $this->user);

        $this->assertInstanceOf(Issue::class, $issue);
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'project_id' => $this->project->id,
            'title' => 'Test Issue',
            'type' => 'bug',
            'priority' => 'high',
            'severity' => 'critical',
            'status' => 'new', // Varsayılan durum IssueStatus::New
            'created_by' => $this->user->id,
        ]);
    }

    public function test_issue_can_be_updated(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Old Title'
        ]);

        $updateData = [
            'title' => 'New Title',
            'priority' => IssuePriority::Low->value,
        ];

        $updatedIssue = $this->issueService->update($issue, $updateData);

        $this->assertEquals('New Title', $updatedIssue->title);
        $this->assertEquals(IssuePriority::Low, $updatedIssue->priority);
    }

    public function test_issue_can_be_deleted(): void
    {
        $issue = Issue::factory()->create(['project_id' => $this->project->id]);

        $this->issueService->delete($issue);

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    public function test_valid_status_transitions(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'status' => IssueStatus::New->value
        ]);

        // New -> InProgress
        $this->issueService->changeStatus($issue, IssueStatus::InProgress, $this->user);
        $this->assertEquals(IssueStatus::InProgress, $issue->fresh()->status);

        // InProgress -> Done
        $this->issueService->changeStatus($issue, IssueStatus::Done, $this->user);
        $this->assertEquals(IssueStatus::Done, $issue->fresh()->status);
    }

    public function test_invalid_status_transition_throws_exception(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'status' => IssueStatus::Done->value
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        // Done status'ünden New'e doğrudan geçiş engellenmelidir (State Machine kuralları gereği)
        $this->issueService->changeStatus($issue, IssueStatus::New, $this->user);
    }
}
