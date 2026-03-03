<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Scrum\StoryStatusChanged;
use App\Actions\Scrum\CalculateEpicCompletionAction;

class RecalculateEpicCompletion
{
    public function __construct(
        private readonly CalculateEpicCompletionAction $action,
    ) {}

    public function handle(StoryStatusChanged $event): void
    {
        $story = $event->story;

        if ($story->epic_id === null) {
            return;
        }

        $this->action->execute($story->epic);
    }
}
