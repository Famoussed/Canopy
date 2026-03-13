<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SprintScopeChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Sprint $sprint,
        public readonly UserStory $story,
        public readonly string $changeType, // 'added' or 'removed'
        public readonly User $changedBy,
    ) {}
}
