<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\CloseSprintAction;
use App\Actions\Scrum\StartSprintAction;
use App\Events\Scrum\SprintClosed;
use App\Events\Scrum\SprintStarted;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SprintService
{
    public function __construct(
        private readonly StartSprintAction $startAction,
        private readonly CloseSprintAction $closeAction,
    ) {}

    public function create(array $data, Project $project): Sprint
    {
        return $project->sprints()->create([
            ...$data,
            'status' => \App\Enums\SprintStatus::Planning,
        ]);
    }

    public function update(Sprint $sprint, array $data): Sprint
    {
        $sprint->update($data);

        return $sprint->fresh();
    }

    public function delete(Sprint $sprint): void
    {
        // Sprint'teki story'ler backlog'a döner
        $sprint->userStories()->update(['sprint_id' => null]);

        $sprint->delete();
    }

    /**
     * BR-05: Tek aktif sprint kuralı kontrol edilir.
     */
    public function start(Sprint $sprint, User $user): Sprint
    {
        $sprint = DB::transaction(function () use ($sprint) {
            return $this->startAction->execute($sprint);
        });

        try {
            SprintStarted::dispatch($sprint, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for SprintStarted', ['error' => $e->getMessage()]);
        }

        return $sprint;
    }

    /**
     * BR-08: Sprint kapatma — tamamlanmamış story'ler backlog'a döner.
     */
    public function close(Sprint $sprint, User $user): Sprint
    {
        $sprint = DB::transaction(function () use ($sprint) {
            return $this->closeAction->execute($sprint);
        });

        try {
            SprintClosed::dispatch($sprint, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for SprintClosed', ['error' => $e->getMessage()]);
        }

        return $sprint;
    }
}
