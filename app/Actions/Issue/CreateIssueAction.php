<?php

declare(strict_types=1);

namespace App\Actions\Issue;

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

class CreateIssueAction
{
    /**
     * BR-18: Issue varsayılan değerler.
     */
    public function execute(array $data, Project $project, User $creator): Issue
    {
        return Issue::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_id' => $project->id,
            'type' => $data['type'],
            'priority' => $data['priority'] ?? IssuePriority::Normal,
            'severity' => $data['severity'] ?? IssueSeverity::Minor,
            'status' => IssueStatus::New,
            'created_by' => $creator->id,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);
    }
}
