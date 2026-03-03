<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'project_id' => Project::factory(),
            'type' => fake()->randomElement(IssueType::cases()),
            'priority' => IssuePriority::Normal,
            'severity' => IssueSeverity::Minor,
            'status' => IssueStatus::New,
            'created_by' => User::factory(),
        ];
    }

    public function bug(): static
    {
        return $this->state(fn () => ['type' => IssueType::Bug]);
    }

    public function critical(): static
    {
        return $this->state(fn () => [
            'priority' => IssuePriority::High,
            'severity' => IssueSeverity::Critical,
        ]);
    }
}
