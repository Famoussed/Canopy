<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStory>
 */
class UserStoryFactory extends Factory
{
    protected $model = UserStory::class;

    public function definition(): array
    {
        static $order = 0;
        $order++;

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'project_id' => Project::factory(),
            'status' => StoryStatus::New,
            'order' => $order,
            'total_points' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => StoryStatus::InProgress]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => StoryStatus::Done]);
    }
}
