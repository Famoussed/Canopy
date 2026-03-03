<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

class IssuePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * P21: Issue oluştur — tüm üyeler
     */
    public function create(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    /**
     * P22 + P23: Issue düzenle
     * Owner/Moderator → herkesinki. Member → sadece kendi.
     */
    public function update(User $user, Issue $issue): bool
    {
        $role = $this->getMemberRole($user, $issue->project);

        if ($role === null) {
            return false;
        }

        if ($role->isAtLeast(ProjectRole::Moderator)) {
            return true;
        }

        return $issue->created_by === $user->id;
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $this->isAtLeast($user, $issue->project, ProjectRole::Moderator);
    }

    public function changeStatus(User $user, Issue $issue): bool
    {
        $role = $this->getMemberRole($user, $issue->project);

        if ($role === null) {
            return false;
        }

        if ($role->isAtLeast(ProjectRole::Moderator)) {
            return true;
        }

        return $issue->created_by === $user->id;
    }

    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first()?->role;
    }

    private function isAtLeast(User $user, Project $project, ProjectRole $minimumRole): bool
    {
        $role = $this->getMemberRole($user, $project);

        return $role !== null && $role->isAtLeast($minimumRole);
    }
}
