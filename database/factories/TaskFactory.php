<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'user_story_id' => UserStory::factory(),
            'status' => TaskStatus::New,
            'created_by' => User::factory(),
        ];
    }

    public function assigned(?User $user = null): static
    {
        return $this->state(fn () => [
            'assigned_to' => $user?->id ?? User::factory(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => TaskStatus::InProgress])
            ->assigned();
    }
}
