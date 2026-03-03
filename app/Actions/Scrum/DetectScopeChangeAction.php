<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Events\Scrum\SprintScopeChanged;
use App\Models\Sprint;
use App\Models\SprintScopeChange;
use App\Models\User;
use App\Models\UserStory;

class DetectScopeChangeAction
{
    /**
     * BR-09: Sprint scope change kaydı oluştur.
     */
    public function execute(Sprint $sprint, UserStory $story, string $changeType, User $changedBy): SprintScopeChange
    {
        $record = SprintScopeChange::create([
            'sprint_id' => $sprint->id,
            'user_story_id' => $story->id,
            'change_type' => $changeType,
            'changed_at' => now(),
            'changed_by' => $changedBy->id,
        ]);

        SprintScopeChanged::dispatch($sprint, $story, $changeType, $changedBy);

        return $record;
    }
}
