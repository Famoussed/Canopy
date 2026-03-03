<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Models\UserStory;

class CalculateStoryPointsAction
{
    /**
     * BR-04: Toplam puan hesaplama.
     * Story points: [{role_name: 'UX', points: 3}, {role_name: 'Frontend', points: 8}]
     */
    public function execute(UserStory $story, array $points): UserStory
    {
        // Mevcut puanları sil ve yeniden oluştur
        $story->storyPoints()->delete();

        foreach ($points as $point) {
            $story->storyPoints()->create([
                'role_name' => $point['role_name'],
                'points' => $point['points'],
            ]);
        }

        // total_points güncelle
        $totalPoints = $story->storyPoints()->sum('points');
        $story->update(['total_points' => $totalPoints]);

        return $story->fresh()->load('storyPoints');
    }
}
