<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Scrum\CreateEpicAction;
use App\Models\Epic;
use App\Models\Project;
use App\Models\User;

class EpicService
{
    public function __construct(
        private CreateEpicAction $createAction,
    ) {}

    public function create(array $data, Project $project, User $user): Epic
    {
        return $this->createAction->execute($data, $project, $user);
    }

    public function update(Epic $epic, array $data): Epic
    {
        $epic->update($data);

        return $epic->fresh();
    }

    public function delete(Epic $epic): void
    {
        // Epic'e bağlı story'lerin epic_id'si null yapılır
        $epic->userStories()->update(['epic_id' => null]);

        $epic->delete();
    }
}
