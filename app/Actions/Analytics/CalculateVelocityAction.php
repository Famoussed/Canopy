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
            ->get()
            ->reverse()
            ->values();

        $sprintData = $sprints->map(function ($sprint) {
            $completedPoints = (float) $sprint->userStories()
                ->where('status', StoryStatus::Done)
                ->sum('total_points');

            return [
                'name' => $sprint->name,
                'completed_points' => $completedPoints,
            ];
        })->toArray();

        $totalPoints = array_sum(array_column($sprintData, 'completed_points'));
        $averageVelocity = count($sprintData) > 0 ? round($totalPoints / count($sprintData), 1) : 0;

        return [
            'sprints' => $sprintData,
            'average_velocity' => $averageVelocity,
        ];
    }
}
