<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\CalculateStoryPointsAction;
use App\Actions\Scrum\ChangeStoryStatusAction;
use App\Actions\Scrum\CreateUserStoryAction;
use App\Actions\Scrum\MoveStoryToSprintAction;
use App\Actions\Scrum\ReorderBacklogAction;
use App\Enums\StoryStatus;
use App\Events\Scrum\StoryCreated;
use App\Events\Scrum\StoryStatusChanged;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Support\Facades\DB;

class UserStoryService
{
    public function __construct(
        private CreateUserStoryAction $createAction,
        private ChangeStoryStatusAction $changeStatusAction,
        private MoveStoryToSprintAction $moveToSprintAction,
        private CalculateStoryPointsAction $calculatePointsAction,
        private ReorderBacklogAction $reorderAction,
    ) {}

    public function create(array $data, Project $project, User $user): UserStory
    {
        return DB::transaction(function () use ($data, $project, $user) {
            $story = $this->createAction->execute($data, $project, $user);

            StoryCreated::dispatch($story, $user);

            return $story;
        });
    }

    public function update(UserStory $story, array $data): UserStory
    {
        $story->update($data);

        return $story->fresh();
    }

    public function delete(UserStory $story): void
    {
        $story->tasks()->delete();
        $story->storyPoints()->delete();
        $story->delete();
    }

    public function changeStatus(UserStory $story, StoryStatus $newStatus, User $user): UserStory
    {
        return DB::transaction(function () use ($story, $newStatus, $user) {
            $oldStatus = $story->status->value;

            $story = $this->changeStatusAction->execute($story, $newStatus);

            StoryStatusChanged::dispatch($story, $oldStatus, $newStatus->value, $user);

            return $story;
        });
    }

    public function moveToSprint(UserStory $story, Sprint $sprint, User $user): UserStory
    {
        return DB::transaction(function () use ($story, $sprint, $user) {
            return $this->moveToSprintAction->execute($story, $sprint, $user);
        });
    }

    public function estimate(UserStory $story, array $points): UserStory
    {
        return DB::transaction(function () use ($story, $points) {
            return $this->calculatePointsAction->execute($story, $points);
        });
    }

    public function reorder(Project $project, array $orderedIds): void
    {
        $this->reorderAction->execute($project, $orderedIds);
    }
}
