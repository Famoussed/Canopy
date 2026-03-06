<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Enums\ProjectRole;
use App\Exceptions\DuplicateMemberException;
use App\Exceptions\MaxMembersExceededException;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;

class AddMemberAction
{
    private const int MAX_MEMBERS = 5;

    /**
     * BR-11: Maksimum 5 üye kontrolü.
     * BR-12: Tekil üyelik kontrolü.
     *
     * @throws DuplicateMemberException
     * @throws MaxMembersExceededException
     */
    public function execute(Project $project, User $user, ProjectRole $role): ProjectMembership
    {
        if ($project->memberships()->count() >= self::MAX_MEMBERS) {
            throw new MaxMembersExceededException($project->id, self::MAX_MEMBERS);
        }

        $exists = $project->memberships()
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw new DuplicateMemberException($user->id, $project->id);
        }

        return $project->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
