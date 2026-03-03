<?php

declare(strict_types=1);

namespace App\Events\Project;

use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly User $member,
        public readonly ProjectMembership $membership,
        public readonly User $addedBy,
    ) {}
}
