<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\IssueStatus;
use App\Models\ActivityLog;
use App\Models\Epic;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTraitRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_project_trait_resolves_project_relationship(): void
    {
        $epic = Epic::factory()->create();

        $this->assertInstanceOf(Project::class, $epic->project);
        $this->assertTrue($epic->project->is($epic->fresh()->project));
    }

    public function test_project_activity_logs_returns_logs_by_project_id(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $anotherProject = Project::factory()->create();
        $epic = Epic::factory()->create(['project_id' => $project->id]);

        ActivityLog::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'epic.updated',
            'subject_type' => Epic::class,
            'subject_id' => $epic->id,
            'changes' => ['title' => 'Updated'],
        ]);

        ActivityLog::create([
            'project_id' => $anotherProject->id,
            'user_id' => $user->id,
            'action' => 'epic.created',
            'subject_type' => Epic::class,
            'subject_id' => $epic->id,
            'changes' => ['title' => 'Another Project'],
        ]);

        $this->assertCount(1, $project->activityLogs);
    }

    public function test_auditable_trait_uses_subject_polymorphic_relation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $epic = Epic::factory()->create(['project_id' => $project->id]);
        $issue = Issue::factory()->create([
            'project_id' => $project->id,
            'status' => IssueStatus::New,
        ]);

        ActivityLog::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'epic.updated',
            'subject_type' => Epic::class,
            'subject_id' => $epic->id,
            'changes' => ['status' => 'in_progress'],
        ]);

        ActivityLog::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'issue.updated',
            'subject_type' => Issue::class,
            'subject_id' => $issue->id,
            'changes' => ['status' => 'done'],
        ]);

        $this->assertCount(1, $epic->activityLogs);
        $this->assertSame('epic.updated', $epic->activityLogs->first()->action);
    }
}
