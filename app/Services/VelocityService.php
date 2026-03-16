<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;

class VelocityService
{
    public function getVelocityData(Project $project, int $sprintCount = 5): array
    {
        $sprints = $project->sprints()
            ->closed()
            ->latest()
            ->limit($sprintCount)
            ->withSum(['userStories as completed_points' => fn($q) => $q->where('status', \App\Enums\StoryStatus::Done)], 'total_points')
            ->get()
            ->reverse()
            ->values();

        $sprintData = $sprints->map(fn($sprint) => [
            'name' => $sprint->name,
            'completed_points' => (float) ($sprint->completed_points ?? 0),
        ])->toArray();

        $totalPoints = array_sum(array_column($sprintData, 'completed_points'));
        $averageVelocity = count($sprintData) > 0 ? round($totalPoints / count($sprintData), 1) : 0.0;

        return [
            'sprints' => $sprintData,
            'average_velocity' => $averageVelocity,
        ];
    }
}
