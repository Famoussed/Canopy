<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\SprintStatus;
use App\Exceptions\ActiveSprintAlreadyExistsException;
use App\Models\Sprint;

class StartSprintAction
{
    /**
     * BR-05: Aynı anda 1 aktif sprint.
     *
     * @throws ActiveSprintAlreadyExistsException
     */
    public function execute(Sprint $sprint): Sprint
    {
        $hasActive = Sprint::where('project_id', $sprint->project_id)
            ->where('status', SprintStatus::Active)
            ->exists();

        if ($hasActive) {
            throw new ActiveSprintAlreadyExistsException($sprint->project_id);
        }

        $sprint->update([
            'status' => SprintStatus::Active,
            'start_date' => now()->toDateString(),
        ]);

        return $sprint->fresh();
    }
}
