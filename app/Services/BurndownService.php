<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BurndownService
{
    public function getBurndownData(Sprint $sprint): array
    {
        $startDate = Carbon::parse($sprint->start_date);
        $endDate = Carbon::parse($sprint->end_date);
        $period = CarbonPeriod::create($startDate, $endDate);

        // Sprint'teki toplam puanlar
        $totalPoints = (float) $sprint->userStories()->sum('total_points');

        return [
            'sprint' => [
                'name' => $sprint->name,
                'start_date' => $sprint->start_date,
                'end_date' => $sprint->end_date,
            ],
            'total_points' => $totalPoints,
            'ideal_line' => $this->calculateIdealLine($totalPoints, $startDate, $endDate, $period),
            'actual_line' => $this->calculateActualLine($sprint, $totalPoints, $period),
            'scope_changes' => $this->getScopeChanges($sprint),
        ];
    }

    private function calculateIdealLine(float $totalPoints, Carbon $startDate, Carbon $endDate, CarbonPeriod $period): array
    {
        $totalDays = $startDate->diffInDays($endDate);
        // Eğer süre 0 ise (aynı gün) doğrudan 0'a düşür, aksi halde günlük düşüş hesapla
        $dailyBurn = $totalDays > 0 ? $totalPoints / $totalDays : $totalPoints;

        $idealLine = [];
        foreach ($period as $index => $date) {
            $idealLine[] = round($totalPoints - ($dailyBurn * $index), 1);
        }

        return $idealLine;
    }

    private function calculateActualLine(Sprint $sprint, float $totalPoints, CarbonPeriod $period): array
    {
        $doneStories = $sprint->userStories()
            ->where('status', StoryStatus::Done)
            ->select('id', 'total_points', 'updated_at')
            ->get();

        $actualLine = [];

        foreach ($period as $date) {
            if ($date->isFuture()) {
                $actualLine[] = null;
                continue;
            }

            $completedPoints = $doneStories
                ->filter(fn ($s) => $s->updated_at->startOfDay()->lte($date))
                ->sum('total_points');

            $actualLine[] = round($totalPoints - (float) $completedPoints, 1);
        }

        return $actualLine;
    }

    private function getScopeChanges(Sprint $sprint): array
    {
        return $sprint->scopeChanges()
            ->with('userStory')
            ->get()
            ->map(fn ($change) => [
                'date' => $change->changed_at->toDateString(),
                'type' => $change->change_type,
                'points_delta' => (float) ($change->userStory->total_points ?? 0),
            ])
            ->values()
            ->toArray();
    }
}
