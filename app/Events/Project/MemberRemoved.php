<?php

declare(strict_types=1);

namespace App\Events\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberRemoved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly User $member,
        public readonly User $removedBy,
    ) {}
}
