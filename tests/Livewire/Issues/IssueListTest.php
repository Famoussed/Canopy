<?php

declare(strict_types=1);

namespace Tests\Livewire\Issues;

use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-05 & L-06: IssueList Livewire component testi.
 *
 * Issue listesi render, filtreleme, issue oluşturma, durum değişikliği ve silme testleri.
 */
class IssueListTest extends TestCase
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

    public function test_issue_list_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/issues"
        );

        $response->assertStatus(200);
        $response->assertSee("Issue'lar");
    }

    public function test_issue_list_displays_issues(): void
    {
        Issue::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Login Bug',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/projects/{$this->project->slug}/issues"
        );

        $response->assertSee('Login Bug');
    }

    public function test_create_issue_via_livewire(): void
    {
        Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('title', 'Yeni Bug')
            ->set('description', 'Bug açıklaması')
            ->set('type', 'bug')
            ->set('priority', 'normal')
            ->set('severity', 'minor')
            ->call('createIssue');

        $this->assertDatabaseHas('issues', [
            'project_id' => $this->project->id,
            'title' => 'Yeni Bug',
            'status' => IssueStatus::New->value,
        ]);
    }

    public function test_create_issue_validates_title(): void
    {
        Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('title', '')
            ->call('createIssue')
            ->assertHasErrors(['title']);
    }

    public function test_filter_issues_by_status(): void
    {
        Issue::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Open Bug',
            'status' => IssueStatus::New,
            'created_by' => $this->user->id,
        ]);

        Issue::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Done Bug',
            'status' => IssueStatus::Done,
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->set('statusFilter', 'new');

        $component->assertSee('Open Bug');
    }

    public function test_change_issue_status(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'status' => IssueStatus::New,
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('changeStatus', $issue->id, 'in_progress');

        $this->assertEquals(IssueStatus::InProgress, $issue->fresh()->status);
    }

    public function test_delete_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('deleteIssue', $issue->id);

        $this->assertDatabaseMissing('issues', ['id' => $issue->id]);
    }

    public function test_edit_issue(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Original Title',
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('issues.issue-list', ['project' => $this->project])
            ->call('editIssue', $issue->id)
            ->assertSet('editTitle', 'Original Title')
            ->set('editTitle', 'Updated Title')
            ->call('updateIssue');

        $this->assertEquals('Updated Title', $issue->fresh()->title);
    }
}
