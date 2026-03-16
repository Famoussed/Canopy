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
        private readonly CreateIssueAction $createAction,
        private readonly ChangeIssueStatusAction $changeStatusAction,
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

    public function list(Project $project, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $project->issues()->with(['creator', 'assignee']);

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }

        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }

        if (filled($filters['severity'] ?? null)) {
            $query->where('severity', $filters['severity']);
        }

        if (filled($filters['status'] ?? null)) {
            $statuses = explode(',', $filters['status']);
            $query->whereIn('status', $statuses);
        }

        if (($filters['assigned_to'] ?? null) === 'me') {
            $query->where('assigned_to', auth()->id());
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    public function getIssueDetails(Issue $issue): Issue
    {
        return $issue->load(['creator', 'assignee', 'attachments']);
    }
}
