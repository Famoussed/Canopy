<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\SprintStatus;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;

class MoveStoryToSprintAction
{
    public function __construct(
        private DetectScopeChangeAction $detectScopeChangeAction,
    ) {}

    /**
     * BR-09: Active sprint'e taşıma → scope change kaydı.
     */
    public function execute(UserStory $story, Sprint $sprint, User $user): UserStory
    {
        $previousSprintId = $story->sprint_id;

        $story->update(['sprint_id' => $sprint->id]);

        // BR-09: Scope change algılama
        if ($sprint->status === SprintStatus::Active) {
            $this->detectScopeChangeAction->execute($sprint, $story, 'added', $user);
        }

        // Eski sprint'ten çıkarılıyorsa ve o sprint active ise
        if ($previousSprintId) {
            $previousSprint = Sprint::find($previousSprintId);
            if ($previousSprint?->status === SprintStatus::Active) {
                $this->detectScopeChangeAction->execute($previousSprint, $story, 'removed', $user);
            }
        }

        return $story->fresh();
    }
}
