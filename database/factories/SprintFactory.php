<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    protected $model = Sprint::class;

    public function definition(): array
    {
        return [
            'name' => 'Sprint '.fake()->unique()->numberBetween(1, 100),
            'project_id' => Project::factory(),
            'status' => SprintStatus::Planning,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => SprintStatus::Active]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => SprintStatus::Closed]);
    }
}
