<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;

class CreateUserStoryAction
{
    /**
     * BR-01: Varsayılan story durumu 'new', sprint_id = null.
     * BR-02: Yeni story backlog'un sonuna eklenir.
     */
    public function execute(array $data, Project $project, User $creator): UserStory
    {
        $maxOrder = UserStory::where('project_id', $project->id)
            ->whereNull('sprint_id')
            ->max('order') ?? 0;

        return UserStory::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_id' => $project->id,
            'epic_id' => $data['epic_id'] ?? null,
            'created_by' => $creator->id,
            'status' => StoryStatus::New,
            'order' => $maxOrder + 1,
        ]);
    }
}
