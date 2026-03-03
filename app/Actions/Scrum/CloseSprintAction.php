<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Models\Sprint;

class CloseSprintAction
{
    /**
     * BR-08: Sprint kapatma — tamamlanmamış story'ler backlog'a döner.
     */
    public function execute(Sprint $sprint): Sprint
    {
        $sprint->transitionTo(SprintStatus::Closed->value);

        // BR-08: Done olmayan story'ler backlog'a
        $sprint->userStories()
            ->where('status', '!=', StoryStatus::Done)
            ->update(['sprint_id' => null]);

        return $sprint->fresh();
    }
}
