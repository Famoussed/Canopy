<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class TransferOwnershipAction
{
    public function execute(Project $project, User $newOwner, User $currentOwner): void
    {
        // Yeni owner'ın membership'ini owner yap
        $project->memberships()
            ->where('user_id', $newOwner->id)
            ->update(['role' => ProjectRole::Owner]);

        // Eski owner'ı moderator yap
        $project->memberships()
            ->where('user_id', $currentOwner->id)
            ->update(['role' => ProjectRole::Moderator]);

        // Project tablosundaki owner_id güncelle
        $project->update(['owner_id' => $newOwner->id]);
    }
}
