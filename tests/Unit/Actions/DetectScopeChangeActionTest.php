<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\DetectScopeChangeAction;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-06: DetectScopeChangeAction testi.
 *
 * Sprint scope change kaydı oluşturulmasını test eder.
 */
class DetectScopeChangeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_scope_change_record(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create(['project_id' => $project->id]);
        $story = UserStory::factory()->create(['project_id' => $project->id]);
        $user = User::factory()->create();

        $action = new DetectScopeChangeAction;
        $record = $action->execute($sprint, $story, 'added', $user);

        $this->assertDatabaseHas('sprint_scope_changes', [
            'id' => $record->id,
            'sprint_id' => $sprint->id,
            'user_story_id' => $story->id,
            'change_type' => 'added',
            'changed_by' => $user->id,
        ]);
    }

    public function test_creates_removed_scope_change(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->active()->create(['project_id' => $project->id]);
        $story = UserStory::factory()->create(['project_id' => $project->id]);
        $user = User::factory()->create();

        $action = new DetectScopeChangeAction;
        $record = $action->execute($sprint, $story, 'removed', $user);

        $this->assertEquals('removed', $record->change_type);
        $this->assertEquals($sprint->id, $record->sprint_id);
    }
}
