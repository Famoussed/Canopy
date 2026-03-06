<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\ChangeStoryStatusAction;
use App\Enums\StoryStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-01 & U-02: ChangeStoryStatusAction testi.
 *
 * Geçerli ve geçersiz durum geçişlerini izole Action seviyesinde test eder.
 */
class ChangeStoryStatusActionTest extends TestCase
{
    use RefreshDatabase;

    private ChangeStoryStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ChangeStoryStatusAction;
    }

    public function test_new_story_can_transition_to_in_progress(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $result = $this->action->execute($story, StoryStatus::InProgress);

        $this->assertEquals(StoryStatus::InProgress, $result->status);
    }

    public function test_in_progress_story_can_transition_to_done(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::InProgress]);

        $result = $this->action->execute($story, StoryStatus::Done);

        $this->assertEquals(StoryStatus::Done, $result->status);
    }

    public function test_in_progress_story_can_transition_back_to_new(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::InProgress]);

        $result = $this->action->execute($story, StoryStatus::New);

        $this->assertEquals(StoryStatus::New, $result->status);
    }

    public function test_done_story_can_transition_to_in_progress(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::Done]);

        $result = $this->action->execute($story, StoryStatus::InProgress);

        $this->assertEquals(StoryStatus::InProgress, $result->status);
    }

    public function test_new_story_cannot_transition_to_done(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->action->execute($story, StoryStatus::Done);
    }

    public function test_done_story_cannot_transition_to_new(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::Done]);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->action->execute($story, StoryStatus::New);
    }
}
