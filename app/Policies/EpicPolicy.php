<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Epic;
use App\Models\Project;
use App\Models\User;
use App\Traits\ResolvesMembership;

class EpicPolicy
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
     * P7: Epic oluştur — Owner + Moderator
     */
    public function create(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P8: Epic düzenle — Owner + Moderator
     */
    public function update(User $user, Epic $epic): bool
    {
        return $this->isAtLeast($user, $epic->project, ProjectRole::Moderator);
    }

    /**
     * P9: Epic sil — Owner + Moderator
     */
    public function delete(User $user, Epic $epic): bool
    {
        return $this->isAtLeast($user, $epic->project, ProjectRole::Moderator);
    }
}
