<?php

declare(strict_types=1);

namespace App\Actions\Issue;

use App\Enums\IssueStatus;
use App\Models\Issue;

class ChangeIssueStatusAction
{
    public function execute(Issue $issue, IssueStatus $newStatus): Issue
    {
        $issue->transitionTo($newStatus);

        return $issue->fresh();
    }
}
