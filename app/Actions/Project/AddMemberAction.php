<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Enums\ProjectRole;
use App\Exceptions\DuplicateMemberException;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;

class AddMemberAction
{
    /**
     * BR-12: Tekil üyelik kontrolü.
     *
     * @throws DuplicateMemberException
     */
    public function execute(Project $project, User $user, ProjectRole $role): ProjectMembership
    {
        $exists = $project->memberships()
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw new DuplicateMemberException();
        }

        return $project->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
