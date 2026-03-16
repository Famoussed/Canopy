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

    public function list(Project $project, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $project->userStories()->with(['epic', 'creator']);

        if (filter_var($filters['backlog'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->backlog();
        }

        if (filled($filters['sprint_id'] ?? null)) {
            $query->where('sprint_id', $filters['sprint_id']);
        }

        if (filled($filters['epic_id'] ?? null)) {
            $query->where('epic_id', $filters['epic_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $statuses = explode(',', $filters['status']);
            $query->whereIn('status', $statuses);
        }

        return $query->orderBy('order')->paginate($filters['per_page'] ?? 50);
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
