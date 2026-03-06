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
     * P17: Task oluştur — Tüm proje üyeleri
     */
    public function create(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    /**
     * P19: Task durumunu değiştir — Owner + Moderator + Task Oluşturan
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

        // Member sadece kendi oluşturduğu task'ın durumunu değiştirebilir
        return $task->created_by === $user->id;
    }

    /**
     * P20: Task düzenle — Owner + Moderator + Task Oluşturan
     */
    public function update(User $user, Task $task): bool
    {
        $project = $task->userStory->project;
        $role = $this->getMemberRole($user, $project);

        if ($role === null) {
            return false;
        }

        // Owner ve Moderator her task'ı düzenleyebilir
        if ($role->isAtLeast(ProjectRole::Moderator)) {
            return true;
        }

        // Member sadece kendi oluşturduğu task'ı düzenleyebilir
        return $task->created_by === $user->id;
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
