<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Project\AddMemberAction;
use App\Actions\Project\RemoveMemberAction;
use App\Actions\Project\TransferOwnershipAction;
use App\Enums\ProjectRole;
use App\Events\Project\MemberAdded;
use App\Events\Project\MemberRemoved;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipService
{
    public function __construct(
        private readonly AddMemberAction $addAction,
        private readonly RemoveMemberAction $removeAction,
        private readonly TransferOwnershipAction $transferAction,
    ) {}

    public function add(Project $project, User $user, ProjectRole $role, User $addedBy): ProjectMembership
    {
        $membership = DB::transaction(function () use ($project, $user, $role) {
            return $this->addAction->execute($project, $user, $role);
        });

        try {
            MemberAdded::dispatch($project, $user, $membership, $addedBy);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for MemberAdded', ['error' => $e->getMessage()]);
        }

        return $membership;
    }

    public function remove(Project $project, User $user, User $removedBy): void
    {
        DB::transaction(function () use ($project, $user) {
            $this->removeAction->execute($project, $user);
        });

        MemberRemoved::dispatch($project, $user, $removedBy);
    }

    public function changeRole(Project $project, User $user, ProjectRole $newRole): ProjectMembership
    {
        $membership = $project->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $membership->update(['role' => $newRole]);

        return $membership->fresh();
    }

    public function changeRoleByUserId(Project $project, string $userId, ProjectRole $newRole): ProjectMembership
    {
        $user = User::findOrFail($userId);

        return $this->changeRole($project, $user, $newRole);
    }

    public function transferOwnership(Project $project, User $newOwner, User $currentOwner): void
    {
        DB::transaction(function () use ($project, $newOwner, $currentOwner) {
            $this->transferAction->execute($project, $newOwner, $currentOwner);
        });
    }

    public function getProjectMembers(Project $project): Collection
    {
        return $project->memberships()
            ->with('user')
            ->get()
            ->sortBy(fn ($membership) => match ($membership->role) {
                ProjectRole::Owner => 0,
                ProjectRole::Moderator => 1,
                default => 2,
            })
            ->values();
    }

    public function addByEmail(Project $project, string $email, ProjectRole $role, User $addedBy): ProjectMembership
    {
        $user = User::where('email', $email)->firstOrFail();

        return $this->add($project, $user, $role, $addedBy);
    }

    public function removeById(Project $project, string $userId, User $removedBy): void
    {
        $user = User::findOrFail($userId);

        $this->remove($project, $user, $removedBy);
    }
}
