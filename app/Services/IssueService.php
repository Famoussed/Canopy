<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Issue\ChangeIssueStatusAction;
use App\Actions\Issue\CreateIssueAction;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Events\Issue\IssueCreated;
use App\Events\Issue\IssueStatusChanged;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

        IssueCreated::dispatch($issue, $user);

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

        IssueStatusChanged::dispatch($issue, $oldStatus, $newStatus->value, $user);

        return $issue;
    }

    public function list(Project $project, array $filters): LengthAwarePaginator
    {
        return $project->issues()
            ->with(['creator', 'assignee'])
            ->filter($filters, auth()->id())
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getIssueDetails(Issue $issue): Issue
    {
        return $issue->load(['creator', 'assignee', 'attachments']);
    }

    public function getProjectIssueCounts(Project $project): array
    {
        $counts = $project->issues()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as bug_count,
                SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END) as critical_count
            ', [
                IssueStatus::Done->value,
                IssueType::Bug->value,
                IssueSeverity::Critical->value,
            ])->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'open' => (int) ($counts->open_count ?? 0),
            'bugs' => (int) ($counts->bug_count ?? 0),
            'critical' => (int) ($counts->critical_count ?? 0),
        ];
    }

    public function getFilteredIssues(Project $project, array $filters): LengthAwarePaginator
    {
        return $this->list($project, $filters);
    }
}
