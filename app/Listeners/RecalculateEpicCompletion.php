<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Scrum\CalculateEpicCompletionAction;
use App\Events\Scrum\StoryStatusChanged;

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
