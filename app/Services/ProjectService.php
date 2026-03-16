<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Project\AddMemberAction;
use App\Actions\Project\CreateProjectAction;
use App\Enums\ProjectRole;
use App\Events\Project\ProjectCreated;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(
        private readonly CreateProjectAction $createAction,
        private readonly AddMemberAction $addMemberAction,
    ) {}

    public function create(array $data, User $user): Project
    {
        return DB::transaction(function () use ($data, $user) {
            $project = $this->createAction->execute($data, $user);

            // BR-13: Oluşturan otomatik owner olur
            $this->addMemberAction->execute($project, $user, ProjectRole::Owner);

            ProjectCreated::dispatch($project, $user);

            return $project->load('owner');
        });
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh();
    }

    public function listForUser(string $userId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Project::forUser($userId)
            ->withDetails()
            ->latest()
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getProjectDetails(Project $project): Project
    {
        return $project->load(['owner', 'memberships'])->loadCount('members');
    }

    public function delete(Project $project): void
    {
        // BR-15: Soft delete
        $project->delete();
    }
}
