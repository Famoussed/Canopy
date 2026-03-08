<?php

declare(strict_types=1);

namespace Tests\Livewire\Scrum;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoryEstimationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private Project $project;

    private UserStory $story;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->moderator = User::factory()->create();
        $this->member = User::factory()->create();

        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->project->memberships()->create(['user_id' => $this->owner->id, 'role' => ProjectRole::Owner]);
        $this->project->memberships()->create(['user_id' => $this->moderator->id, 'role' => ProjectRole::Moderator]);
        $this->project->memberships()->create(['user_id' => $this->member->id, 'role' => ProjectRole::Member]);

        $sprint = Sprint::factory()->create(['project_id' => $this->project->id]);
        $this->story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Render: Puanlama formu yetkiliye görünmeli
    // ═══════════════════════════════════════════════════════════════════

    public function test_owner_sees_estimation_form(): void
    {
        $this->actingAs($this->owner);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])->assertSee('Puanla');
    }

    public function test_moderator_sees_estimation_form(): void
    {
        $this->actingAs($this->moderator);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])->assertSee('Puanla');
    }

    public function test_member_cannot_see_estimation_form(): void
    {
        $this->actingAs($this->member);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])->assertDontSeeHtml('wire:submit="saveEstimation"');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Form: Proje estimation_roles'a göre dinamik alanlar
    // ═══════════════════════════════════════════════════════════════════

    public function test_estimation_form_shows_project_roles(): void
    {
        $this->actingAs($this->owner);

        // Default roles: UX, Design, Frontend, Backend
        $component = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ]);

        $component->assertSee('UX');
        $component->assertSee('Design');
        $component->assertSee('Frontend');
        $component->assertSee('Backend');
    }

    public function test_estimation_form_shows_custom_roles(): void
    {
        $this->project->update([
            'settings' => ['estimation_roles' => ['QA', 'DevOps']],
        ]);

        $this->actingAs($this->owner);

        $component = Livewire::test('scrum.story-detail', [
            'project' => $this->project->fresh(),
            'story' => $this->story,
        ]);

        $component->assertSee('QA');
        $component->assertSee('DevOps');
    }

    // ═══════════════════════════════════════════════════════════════════
    // Save: Puanları kaydetme
    // ═══════════════════════════════════════════════════════════════════

    public function test_owner_can_save_story_points(): void
    {
        $this->actingAs($this->owner);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])
            ->set('estimationPoints.UX', '3')
            ->set('estimationPoints.Frontend', '8')
            ->call('saveEstimation');

        $this->assertDatabaseHas('story_points', [
            'user_story_id' => $this->story->id,
            'role_name' => 'UX',
            'points' => '3.00',
        ]);

        $this->assertDatabaseHas('story_points', [
            'user_story_id' => $this->story->id,
            'role_name' => 'Frontend',
            'points' => '8.00',
        ]);

        // total_points güncellenmeli
        $this->assertEquals(11.0, $this->story->fresh()->total_points);
    }

    public function test_moderator_can_save_story_points(): void
    {
        $this->actingAs($this->moderator);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])
            ->set('estimationPoints.Backend', '5')
            ->call('saveEstimation');

        $this->assertDatabaseHas('story_points', [
            'user_story_id' => $this->story->id,
            'role_name' => 'Backend',
            'points' => '5.00',
        ]);
    }

    public function test_member_cannot_save_story_points(): void
    {
        $this->actingAs($this->member);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])
            ->set('estimationPoints.UX', '3')
            ->call('saveEstimation')
            ->assertForbidden();
    }

    public function test_estimation_updates_existing_points(): void
    {
        // Önceden bir puan var
        $this->story->storyPoints()->create(['role_name' => 'UX', 'points' => 2]);
        $this->story->update(['total_points' => 2]);

        $this->actingAs($this->owner);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story->fresh(),
        ])
            ->set('estimationPoints.UX', '5')
            ->set('estimationPoints.Frontend', '8')
            ->call('saveEstimation');

        // Eski puan silinip yenisi yazılmalı
        $this->assertEquals(1, $this->story->storyPoints()->where('role_name', 'UX')->count());
        $this->assertEquals(5.0, (float) $this->story->storyPoints()->where('role_name', 'UX')->first()->points);
        $this->assertEquals(13.0, $this->story->fresh()->total_points);
    }

    public function test_estimation_skips_zero_and_empty_values(): void
    {
        $this->actingAs($this->owner);

        Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story,
        ])
            ->set('estimationPoints.UX', '5')
            ->set('estimationPoints.Design', '0')
            ->set('estimationPoints.Frontend', '')
            ->set('estimationPoints.Backend', '3')
            ->call('saveEstimation');

        // Sadece nonzero kayıtlar
        $this->assertEquals(2, $this->story->storyPoints()->count());
        $this->assertEquals(8.0, $this->story->fresh()->total_points);
    }

    public function test_existing_points_preloaded_in_form(): void
    {
        $this->story->storyPoints()->create(['role_name' => 'UX', 'points' => 5]);
        $this->story->storyPoints()->create(['role_name' => 'Backend', 'points' => 13]);
        $this->story->update(['total_points' => 18]);

        $this->actingAs($this->owner);

        $component = Livewire::test('scrum.story-detail', [
            'project' => $this->project,
            'story' => $this->story->fresh(),
        ]);

        $component->assertSet('estimationPoints.UX', '5');
        $component->assertSet('estimationPoints.Backend', '13');
    }
}
