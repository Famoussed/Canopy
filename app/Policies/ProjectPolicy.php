<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Super admin bypass.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * P29: Proje detaylarını görüntüle — tüm üyeler
     */
    public function view(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    /**
     * P1: Proje ayarlarını düzenle — sadece Owner
     */
    public function update(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    /**
     * P2: Projeyi sil — sadece Owner
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    /**
     * P3: Sahipliği devret — sadece Owner
     */
    public function transferOwnership(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    /**
     * P4: Üye ekle — Owner + Moderator
     */
    public function addMember(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P5: Üye çıkar — Owner + Moderator
     */
    public function removeMember(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P6: Üye rolünü değiştir — sadece Owner
     */
    public function changeRole(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    // ─── Helpers ───

    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
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
