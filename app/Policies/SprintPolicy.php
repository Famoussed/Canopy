<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Traits\ResolvesMembership;

class SprintPolicy
{
    use ResolvesMembership;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * P14: Sprint oluştur — Owner + Moderator
     */
    public function create(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P15: Sprint başlat / kapat — Owner + Moderator
     */
    public function manage(User $user, Sprint $sprint): bool
    {
        return $this->isAtLeast($user, $sprint->project, ProjectRole::Moderator);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $this->isAtLeast($user, $sprint->project, ProjectRole::Moderator);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $this->isAtLeast($user, $sprint->project, ProjectRole::Moderator);
    }
}
