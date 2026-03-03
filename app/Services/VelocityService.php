<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Analytics\CalculateVelocityAction;
use App\Models\Project;

class VelocityService
{
    public function __construct(
        private CalculateVelocityAction $calculateAction,
    ) {}

    public function getVelocityData(Project $project, int $sprintCount = 5): array
    {
        return $this->calculateAction->execute($project, $sprintCount);
    }
}
