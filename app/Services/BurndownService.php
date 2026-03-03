<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Analytics\CalculateBurndownAction;
use App\Models\Sprint;

class BurndownService
{
    public function __construct(
        private CalculateBurndownAction $calculateAction,
    ) {}

    public function getBurndownData(Sprint $sprint): array
    {
        return $this->calculateAction->execute($sprint);
    }
}
