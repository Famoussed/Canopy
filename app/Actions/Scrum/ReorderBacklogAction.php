<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Models\Project;
use App\Models\UserStory;

class ReorderBacklogAction
{
    /**
     * BR-02: Backlog sıralaması.
     *
     * @param array<int, string> $orderedIds  Story ID'leri sırasıyla
     */
    public function execute(Project $project, array $orderedIds): void
    {
        foreach ($orderedIds as $order => $storyId) {
            UserStory::where('id', $storyId)
                ->where('project_id', $project->id)
                ->whereNull('sprint_id')
                ->update(['order' => $order + 1]);
        }
    }
}
