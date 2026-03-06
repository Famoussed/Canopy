<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\StoryStatus;
use App\Models\UserStory;

class ChangeStoryStatusAction
{
    /**
     * State Machine üzerinden durum geçişi.
     */
    public function execute(UserStory $story, StoryStatus $newStatus): UserStory
    {
        $story->transitionTo($newStatus->value);

        return $story->fresh();
    }
}
