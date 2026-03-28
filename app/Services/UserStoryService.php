<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\CalculateStoryPointsAction;
use App\Actions\Scrum\ChangeStoryStatusAction;
use App\Actions\Scrum\CreateUserStoryAction;
use App\Actions\Scrum\MoveStoryToSprintAction;
use App\Actions\Scrum\ReorderBacklogAction;
use App\Enums\StoryStatus;
use App\Events\Scrum\SprintScopeChanged;
use App\Events\Scrum\StoryCreated;
use App\Events\Scrum\StoryStatusChanged;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

        StoryCreated::dispatch($story, $user);

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

        StoryStatusChanged::dispatch($story, $oldStatus, $newStatus->value, $user);

        return $story;
    }

    public function moveToSprint(UserStory $story, Sprint $sprint, User $user): UserStory
    {
        $result = DB::transaction(function () use ($story, $sprint, $user) {
            return $this->moveToSprintAction->execute($story, $sprint, $user);
        });

        foreach ($result['scopeChanges'] as ['sprint' => $affectedSprint, 'changeType' => $changeType]) {
            SprintScopeChanged::dispatch($affectedSprint, $result['story'], $changeType, $user);
        }

        return $result['story'];
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
