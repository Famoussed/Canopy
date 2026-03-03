<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class CreateProjectAction
{
    public function execute(array $data, User $owner): Project
    {
        return Project::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'owner_id' => $owner->id,
            'settings' => $data['settings'] ?? [
                'modules' => ['scrum' => true, 'issues' => true],
                'estimation_roles' => ['UX', 'Design', 'Frontend', 'Backend'],
            ],
        ]);
    }
}
