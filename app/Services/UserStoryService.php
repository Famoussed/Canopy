<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\CalculateStoryPointsAction;
use App\Actions\Scrum\ChangeStoryStatusAction;
use App\Actions\Scrum\CreateUserStoryAction;
use App\Actions\Scrum\MoveStoryToSprintAction;
use App\Actions\Scrum\ReorderBacklogAction;
use App\Enums\SprintStatus;
use App\Enums\StoryStatus;
use App\Events\Scrum\SprintScopeChanged;
use App\Events\Scrum\StoryCreated;
use App\Events\Scrum\StoryStatusChanged;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserStoryService
{
    public function __construct(
        private readonly CreateUserStoryAction $createAction,
        private readonly ChangeStoryStatusAction $changeStatusAction,
        private readonly MoveStoryToSprintAction $moveToSprintAction,
        private readonly CalculateStoryPointsAction $calculatePointsAction,
        private readonly ReorderBacklogAction $reorderAction,
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
        $previousSprintId = $story->sprint_id;

        $story = DB::transaction(function () use ($story, $sprint, $user) {
            return $this->moveToSprintAction->execute($story, $sprint, $user);
        });

        if ($sprint->status === SprintStatus::Active) {
            SprintScopeChanged::dispatch($sprint, $story, 'added', $user);
        }

        if ($previousSprintId !== null) {
            $previousSprint = Sprint::find($previousSprintId);

            if ($previousSprint?->status === SprintStatus::Active) {
                SprintScopeChanged::dispatch($previousSprint, $story, 'removed', $user);
            }
        }

        return $story;
    }

    public function estimate(UserStory $story, array $points): UserStory
    {
        return DB::transaction(function () use ($story, $points) {
            return $this->calculatePointsAction->execute($story, $points);
        });
    }

    public function reorder(Project $project, array $orderedIds): void
    {
        DB::transaction(function () use ($project, $orderedIds) {
            $this->reorderAction->execute($project, $orderedIds);
        });
    }

    public function list(Project $project, array $filters): LengthAwarePaginator
    {
        return $project->userStories()
            ->with(['epic', 'creator'])
            ->filter($filters)
            ->ordered()
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getStoryDetails(UserStory $story): UserStory
    {
        return $story->load(['tasks', 'storyPoints', 'epic', 'creator', 'attachments']);
    }

    public function moveToSprintById(UserStory $story, string $sprintId, User $user): UserStory
    {
        $sprint = Sprint::findOrFail($sprintId);

        return $this->moveToSprint($story, $sprint, $user);
    }
}
