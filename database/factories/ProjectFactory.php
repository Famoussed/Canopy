<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'owner_id' => User::factory(),
            'settings' => [
                'modules' => ['scrum' => true, 'issues' => true],
                'estimation_roles' => ['UX', 'Design', 'Frontend', 'Backend'],
            ],
        ];
    }
}
