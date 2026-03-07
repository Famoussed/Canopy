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
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $story = DB::transaction(function () use ($data, $project, $user) {
            return $this->createAction->execute($data, $project, $user);
        });

        try {
            StoryCreated::dispatch($story, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for StoryCreated', ['error' => $e->getMessage()]);
        }

        return $story;
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
        $oldStatus = $story->status->value;

        $story = DB::transaction(function () use ($story, $newStatus) {
            return $this->changeStatusAction->execute($story, $newStatus);
        });

        try {
            StoryStatusChanged::dispatch($story, $oldStatus, $newStatus->value, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for StoryStatusChanged', ['error' => $e->getMessage()]);
        }

        return $story;
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
