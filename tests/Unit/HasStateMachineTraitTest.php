<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StoryStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Sprint;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * U-12: HasStateMachine trait testi.
 *
 * canTransitionTo, transitionTo ve availableTransitions metodlarını test eder.
 */
class HasStateMachineTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_transition_to_returns_true_for_valid_transition(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $this->assertTrue($story->canTransitionTo('in_progress'));
    }

    public function test_can_transition_to_returns_false_for_invalid_transition(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $this->assertFalse($story->canTransitionTo('done'));
    }

    public function test_transition_to_changes_status(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $story->transitionTo('in_progress');

        $this->assertEquals(StoryStatus::InProgress, $story->fresh()->status);
    }

    public function test_transition_to_throws_on_invalid_transition(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::New]);

        $this->expectException(InvalidStatusTransitionException::class);

        $story->transitionTo('done');
    }

    public function test_available_transitions_returns_correct_list(): void
    {
        $story = UserStory::factory()->create(['status' => StoryStatus::InProgress]);

        $transitions = $story->availableTransitions();

        $this->assertContains('new', $transitions);
        $this->assertContains('done', $transitions);
        $this->assertNotContains('in_progress', $transitions);
    }

    public function test_sprint_state_machine_works(): void
    {
        $sprint = Sprint::factory()->create();

        $this->assertTrue($sprint->canTransitionTo('active'));
        $this->assertFalse($sprint->canTransitionTo('closed'));

        $sprint->transitionTo('active');
        $this->assertTrue($sprint->canTransitionTo('closed'));
    }
}
