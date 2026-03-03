<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\StoryStatus;
use App\Models\Epic;

class CalculateEpicCompletionAction
{
    /**
     * BR-11: Epic tamamlanma yüzdesi hesapla.
     */
    public function execute(Epic $epic): Epic
    {
        $totalStories = $epic->userStories()->count();

        if ($totalStories === 0) {
            $epic->update([
                'completion_percentage' => 0,
                'status' => StoryStatus::New,
            ]);

            return $epic->fresh();
        }

        $doneStories = $epic->userStories()
            ->where('status', StoryStatus::Done)
            ->count();

        $percentage = (int) floor(($doneStories / $totalStories) * 100);

        // Epic status otomatik güncelle
        $status = match (true) {
            $percentage === 0 => StoryStatus::New,
            $percentage === 100 => StoryStatus::Done,
            default => StoryStatus::InProgress,
        };

        $epic->update([
            'completion_percentage' => $percentage,
            'status' => $status,
        ]);

        return $epic->fresh();
    }
}
