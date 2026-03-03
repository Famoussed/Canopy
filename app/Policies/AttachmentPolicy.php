<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;

class AttachmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * P24: Dosya ekle — tüm üyeler
     */
    public function create(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    /**
     * P25 + P26: Dosya sil
     * Owner/Moderator → herkesinki. Member → sadece kendi.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        // Attachment'ın project'ini belirlemek için attachable üzerinden gidilir
        if ($attachment->uploaded_by === $user->id) {
            return true; // Kendi dosyasını herkes silebilir
        }

        // Moderator+ herkesin dosyasını silebilir
        // Bu durumda project'e erişmemiz gerekir — attachable üzerinden
        return false; // Detaylı implementasyon attachable type'a göre yapılacak
    }

    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first()?->role;
    }
}
