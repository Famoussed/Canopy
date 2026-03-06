<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Issue\ChangeIssueStatusAction;
use App\Actions\Issue\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Events\Issue\IssueCreated;
use App\Events\Issue\IssueStatusChanged;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueService
{
    public function __construct(
        private CreateIssueAction $createAction,
        private ChangeIssueStatusAction $changeStatusAction,
    ) {}

    public function create(array $data, Project $project, User $user): Issue
    {
        return DB::transaction(function () use ($data, $project, $user) {
            $issue = $this->createAction->execute($data, $project, $user);

            IssueCreated::dispatch($issue, $user);

            return $issue;
        });
    }

    public function update(Issue $issue, array $data): Issue
    {
        $issue->update($data);

        return $issue->fresh();
    }

    public function delete(Issue $issue): void
    {
        $issue->delete();
    }

    public function changeStatus(Issue $issue, IssueStatus $newStatus, User $user): Issue
    {
        return DB::transaction(function () use ($issue, $newStatus, $user) {
            $oldStatus = $issue->status->value;

            $issue = $this->changeStatusAction->execute($issue, $newStatus);

            IssueStatusChanged::dispatch($issue, $oldStatus, $newStatus->value, $user);

            return $issue;
        });
    }
}
