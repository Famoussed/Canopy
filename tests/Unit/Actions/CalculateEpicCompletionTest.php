<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\CalculateEpicCompletionAction;
use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateEpicCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_epic_has_zero_completion(): void
    {
        $epic = Epic::factory()->create();

        $action = new CalculateEpicCompletionAction();
        $result = $action->execute($epic);

        $this->assertEquals(0, $result->completion_percentage);
        $this->assertEquals(StoryStatus::New, $result->status);
    }

    public function test_completion_calculated_correctly(): void
    {
        $epic = Epic::factory()->create();

        UserStory::factory()->done()->create(['epic_id' => $epic->id, 'project_id' => $epic->project_id]);
        UserStory::factory()->done()->create(['epic_id' => $epic->id, 'project_id' => $epic->project_id]);
        UserStory::factory()->create(['epic_id' => $epic->id, 'project_id' => $epic->project_id]);

        $action = new CalculateEpicCompletionAction();
        $result = $action->execute($epic);

        $this->assertEquals(66, $result->completion_percentage); // floor(2/3 * 100) = 66
        $this->assertEquals(StoryStatus::InProgress, $result->status);
    }

    public function test_all_done_makes_epic_done(): void
    {
        $epic = Epic::factory()->create();

        UserStory::factory()->done()->count(3)->create([
            'epic_id' => $epic->id,
            'project_id' => $epic->project_id,
        ]);

        $action = new CalculateEpicCompletionAction();
        $result = $action->execute($epic);

        $this->assertEquals(100, $result->completion_percentage);
        $this->assertEquals(StoryStatus::Done, $result->status);
    }
}
