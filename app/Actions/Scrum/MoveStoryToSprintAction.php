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
     *
     * @return array{story: UserStory, scopeChanges: array<array{sprint: Sprint, changeType: string}>}
     */
    public function execute(UserStory $story, Sprint $sprint, User $user): array
    {
        $previousSprintId = $story->sprint_id;

        $story->update(['sprint_id' => $sprint->id]);

        $scopeChanges = [];

        if ($sprint->status === SprintStatus::Active) {
            $this->detectScopeChangeAction->execute($sprint, $story, 'added', $user);
            $scopeChanges[] = ['sprint' => $sprint, 'changeType' => 'added'];
        }

        if ($previousSprintId !== null) {
            $previousSprint = Sprint::find($previousSprintId);

            if ($previousSprint?->status === SprintStatus::Active) {
                $this->detectScopeChangeAction->execute($previousSprint, $story, 'removed', $user);
                $scopeChanges[] = ['sprint' => $previousSprint, 'changeType' => 'removed'];
            }
        }

        return ['story' => $story->fresh(), 'scopeChanges' => $scopeChanges];
    }
}
