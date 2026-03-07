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
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IssueService
{
    public function __construct(
        private CreateIssueAction $createAction,
        private ChangeIssueStatusAction $changeStatusAction,
    ) {}

    public function create(array $data, Project $project, User $user): Issue
    {
        $issue = DB::transaction(function () use ($data, $project, $user) {
            return $this->createAction->execute($data, $project, $user);
        });

        try {
            IssueCreated::dispatch($issue, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for IssueCreated', ['error' => $e->getMessage()]);
        }

        return $issue;
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
        $oldStatus = $issue->status->value;

        $issue = DB::transaction(function () use ($issue, $newStatus) {
            return $this->changeStatusAction->execute($issue, $newStatus);
        });

        try {
            IssueStatusChanged::dispatch($issue, $oldStatus, $newStatus->value, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for IssueStatusChanged', ['error' => $e->getMessage()]);
        }

        return $issue;
    }
}
