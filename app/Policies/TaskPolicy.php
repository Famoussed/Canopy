<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * P17: Task oluştur — Owner + Moderator
     */
    public function create(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    /**
     * P19: Kendi task durumunu değiştir — tüm üyeler (kendi task'ları)
     */
    public function changeStatus(User $user, Task $task): bool
    {
        $project = $task->userStory->project;
        $role = $this->getMemberRole($user, $project);

        if ($role === null) {
            return false;
        }

        // Owner ve Moderator her task'ı değiştirebilir
        if ($role->isAtLeast(ProjectRole::Moderator)) {
            return true;
        }

        // Member sadece kendine atanmış task
        return $task->assigned_to === $user->id;
    }

    /**
     * P20: Herhangi bir task'ı düzenle — Owner + Moderator
     */
    public function update(User $user, Task $task): bool
    {
        return $this->isAtLeast($user, $task->userStory->project, ProjectRole::Moderator);
    }

    /**
     * P18: Task başkasına ata — Owner + Moderator
     */
    public function assign(User $user, Task $task): bool
    {
        return $this->isAtLeast($user, $task->userStory->project, ProjectRole::Moderator);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->isAtLeast($user, $task->userStory->project, ProjectRole::Moderator);
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
