<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Scrum\SprintClosed;
use App\Models\UserStory;
use App\Enums\StoryStatus;

class ReturnUnfinishedStoriesToBacklog
{
    /**
     * Sprint kapatıldığında tamamlanmamış story'leri Backlog'a döndürür.
     * sprint_id = null yapılır.
     */
    public function handle(SprintClosed $event): void
    {
        UserStory::where('sprint_id', $event->sprint->id)
            ->where('status', '!=', StoryStatus::Done)
            ->update(['sprint_id' => null]);
    }
}
