<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Sprint;

class SnapshotDailyBurndownAction
{
    /**
     * Günlük burndown snapshot'ı — Scheduler veya Listener tarafından tetiklenir.
     */
    public function execute(Sprint $sprint): void
    {
        // Sprint aktif değilse snapshot alma
        if ($sprint->status->value !== 'active') {
            return;
        }

        // Burndown verisini hesapla ve cache'e kaydet
        $burndownAction = app(CalculateBurndownAction::class);
        $data = $burndownAction->execute($sprint);

        cache()->put(
            "burndown.{$sprint->id}.".now()->toDateString(),
            $data,
            now()->addDays(90)
        );
    }
}
