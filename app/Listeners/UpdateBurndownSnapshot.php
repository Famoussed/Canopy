<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Analytics\SnapshotDailyBurndownAction;
use App\Events\Scrum\StoryStatusChanged;

class UpdateBurndownSnapshot
{
    public function __construct(
        private readonly SnapshotDailyBurndownAction $action,
    ) {}

    public function handle(StoryStatusChanged $event): void
    {
        $story = $event->story;

        if ($story->sprint_id === null) {
            return;
        }

        $this->action->execute($story->sprint);
    }
}
