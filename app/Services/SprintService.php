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
use Illuminate\Support\Facades\DB;

class SprintService
{
    public function __construct(
        private StartSprintAction $startAction,
        private CloseSprintAction $closeAction,
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
        return DB::transaction(function () use ($sprint, $user) {
            $sprint = $this->startAction->execute($sprint);

            SprintStarted::dispatch($sprint, $user);

            return $sprint;
        });
    }

    /**
     * BR-08: Sprint kapatma — tamamlanmamış story'ler backlog'a döner.
     */
    public function close(Sprint $sprint, User $user): Sprint
    {
        return DB::transaction(function () use ($sprint, $user) {
            $sprint = $this->closeAction->execute($sprint);

            SprintClosed::dispatch($sprint, $user);

            return $sprint;
        });
    }
}
