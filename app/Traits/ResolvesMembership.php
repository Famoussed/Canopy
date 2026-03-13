<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

trait ResolvesMembership
{
    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        $cached = request()->attributes->get('membership');
        if ($cached && $cached->project_id === $project->id && $cached->user_id === $user->id) {
            return $cached->role;
        }

        $membership = $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first();

        return $membership?->role;
    }

    private function isAtLeast(User $user, Project $project, ProjectRole $minimumRole): bool
    {
        $role = $this->getMemberRole($user, $project);

        if ($role === null) {
            return false;
        }

        return $role->isAtLeast($minimumRole);
    }
}
