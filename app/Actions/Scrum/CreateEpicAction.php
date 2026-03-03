<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Models\Epic;
use App\Models\Project;
use App\Models\User;

class CreateEpicAction
{
    public function execute(array $data, Project $project, User $user): Epic
    {
        return $project->epics()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366F1',
        ]);
    }
}
