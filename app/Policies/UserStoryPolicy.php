<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use App\Traits\ResolvesMembership;

class UserStoryPolicy
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
     * P10: Story oluştur — Owner + Moderator
     */
    public function create(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P11: Story düzenle — Owner + Moderator
     */
    public function update(User $user, UserStory $story): bool
    {
        return $this->isAtLeast($user, $story->project, ProjectRole::Moderator);
    }

    /**
     * P12: Story sil — Owner + Moderator
     */
    public function delete(User $user, UserStory $story): bool
    {
        return $this->isAtLeast($user, $story->project, ProjectRole::Moderator);
    }

    /**
     * P13: Story puanla — Owner + Moderator
     */
    public function estimate(User $user, UserStory $story): bool
    {
        return $this->isAtLeast($user, $story->project, ProjectRole::Moderator);
    }

    /**
     * P16: Sprint'e story taşı — Owner + Moderator
     */
    public function moveToSprint(User $user, UserStory $story): bool
    {
        return $this->isAtLeast($user, $story->project, ProjectRole::Moderator);
    }
}
