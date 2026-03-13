<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Project;

class CalculateVelocityAction
{
    public function execute(Project $project, int $sprintCount = 5): array
    {
        $sprints = $project->sprints()
            ->where('status', SprintStatus::Closed)
            ->orderByDesc('created_at')
            ->limit($sprintCount)
            ->withSum(['userStories as completed_points' => function ($q) {
                $q->where('status', StoryStatus::Done);
            }], 'total_points')
            ->get()
            ->reverse()
            ->values();

        $sprintData = $sprints->map(fn ($sprint) => [
            'name' => $sprint->name,
            'completed_points' => (float) ($sprint->completed_points ?? 0),
        ])->toArray();

        $totalPoints = array_sum(array_column($sprintData, 'completed_points'));
        $averageVelocity = count($sprintData) > 0 ? round($totalPoints / count($sprintData), 1) : 0;

        return [
            'sprints' => $sprintData,
            'average_velocity' => $averageVelocity,
        ];
    }
}
