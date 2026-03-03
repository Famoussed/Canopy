<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Epic>
 */
class EpicFactory extends Factory
{
    protected $model = Epic::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'project_id' => Project::factory(),
            'color' => fake()->hexColor(),
            'status' => StoryStatus::New,
        ];
    }
}
