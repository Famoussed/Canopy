<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalculateBurndownAction
{
    public function execute(Sprint $sprint): array
    {
        $startDate = Carbon::parse($sprint->start_date);
        $endDate = Carbon::parse($sprint->end_date);
        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = $startDate->diffInDays($endDate);

        // Sprint'teki toplam puanlar
        $totalPoints = (float) $sprint->userStories()->sum('total_points');

        // İdeal çizgi
        $idealLine = [];
        $dailyBurn = $totalDays > 0 ? $totalPoints / $totalDays : 0;

        foreach ($period as $index => $date) {
            $idealLine[] = round($totalPoints - ($dailyBurn * $index), 1);
        }

        // Gerçek çizgi — done olan story puanlarını gün bazında çıkar
        $actualLine = [];
        $remainingPoints = $totalPoints;

        foreach ($period as $date) {
            if ($date->isFuture()) {
                $actualLine[] = null;
                continue;
            }

            $completedToday = $sprint->userStories()
                ->where('status', StoryStatus::Done)
                ->whereDate('updated_at', '<=', $date)
                ->sum('total_points');

            $actualLine[] = round($totalPoints - (float) $completedToday, 1);
        }

        // Scope changes
        $scopeChanges = $sprint->scopeChanges()
            ->with('userStory')
            ->get()
            ->map(fn ($change) => [
                'date' => $change->changed_at->toDateString(),
                'type' => $change->change_type,
                'points_delta' => (float) ($change->userStory->total_points ?? 0),
            ])
            ->values()
            ->toArray();

        return [
            'sprint' => [
                'name' => $sprint->name,
                'start_date' => $sprint->start_date,
                'end_date' => $sprint->end_date,
            ],
            'total_points' => $totalPoints,
            'ideal_line' => $idealLine,
            'actual_line' => $actualLine,
            'scope_changes' => $scopeChanges,
        ];
    }
}
