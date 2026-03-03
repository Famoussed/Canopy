<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'selim@canopy.dev')->first();
        $members = User::where('email', '!=', 'admin@canopy.dev')
            ->where('email', '!=', 'selim@canopy.dev')
            ->take(3)
            ->get();

        // Create demo project
        $project = Project::factory()->create([
            'name' => 'Canopy Platform',
            'slug' => 'canopy-platform',
            'description' => 'A modern project management platform with Scrum and Issue tracking.',
            'owner_id' => $owner->id,
        ]);

        // Add memberships
        $project->memberships()->create(['user_id' => $owner->id, 'role' => ProjectRole::Owner]);
        foreach ($members as $index => $member) {
            $project->memberships()->create([
                'user_id' => $member->id,
                'role' => $index === 0 ? ProjectRole::Moderator : ProjectRole::Member,
            ]);
        }

        // Create epics
        $epics = collect([
            'User Management' => '#6366F1',
            'Sprint Board' => '#F59E0B',
            'Issue Tracker' => '#EF4444',
        ])->map(fn ($color, $title) => Epic::factory()->create([
            'project_id' => $project->id,
            'title' => $title,
            'color' => $color,
        ]));

        // Create stories in backlog
        $stories = [];
        foreach ($epics as $epic) {
            for ($i = 1; $i <= 3; $i++) {
                $stories[] = UserStory::factory()->create([
                    'project_id' => $project->id,
                    'epic_id' => $epic->id,
                    'created_by' => $owner->id,
                    'order' => count($stories) + 1,
                ]);
            }
        }

        // Create a sprint with some stories
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'status' => SprintStatus::Active,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
        ]);

        // Move 3 stories to sprint
        foreach (array_slice($stories, 0, 3) as $story) {
            $story->update(['sprint_id' => $sprint->id, 'status' => StoryStatus::InProgress]);

            // Create tasks
            Task::factory()->count(2)->create([
                'user_story_id' => $story->id,
                'created_by' => $owner->id,
                'assigned_to' => $members->random()->id,
            ]);
        }

        // Create issues
        Issue::factory()->count(5)->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
        ]);

        Issue::factory()->bug()->critical()->count(2)->create([
            'project_id' => $project->id,
            'created_by' => $members->first()->id,
            'assigned_to' => $owner->id,
        ]);
    }
}
