<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Exceptions\OwnerCannotBeRemovedException;
use App\Models\Project;
use App\Models\User;

class RemoveMemberAction
{
    /**
     * BR-14: Owner çıkarılamaz.
     *
     * @throws OwnerCannotBeRemovedException
     */
    public function execute(Project $project, User $user): void
    {
        $membership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($membership->isOwner()) {
            throw new OwnerCannotBeRemovedException;
        }

        $membership->delete();
    }
}
