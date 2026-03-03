<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SprintClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Sprint $sprint,
        public readonly User $closedBy,
    ) {}
}
