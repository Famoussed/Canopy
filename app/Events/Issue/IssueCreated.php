<?php

declare(strict_types=1);

namespace App\Events\Issue;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Issue $issue,
        public readonly User $creator,
    ) {}
}
