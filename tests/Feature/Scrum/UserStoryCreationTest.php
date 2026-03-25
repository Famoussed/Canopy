<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use App\Services\UserStoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * User Story Oluşturma Testi
 *
 * User Story oluşturma sürecinin Policy katmanı, Service katmanı ve
 * Livewire form validasyonu üzerinden doğru çalıştığını doğrular.
 */
class UserStoryCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $moderator;

    protected User $member;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->moderator = User::factory()->create();
        $this->member = User::factory()->create();

        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->project->memberships()->createMany([
            ['user_id' => $this->owner->id, 'role' => ProjectRole::Owner],
            ['user_id' => $this->moderator->id, 'role' => ProjectRole::Moderator],
            ['user_id' => $this->member->id, 'role' => ProjectRole::Member],
        ]);
    }

    public function test_owner_can_create_story_via_api(): void
    {
        $this->assertTrue($this->owner->can('create', [UserStory::class, $this->project]));

        $service = app(UserStoryService::class);
        $story = $service->create(['title' => 'Kullanıcı giriş yapabilmeli'], $this->project, $this->owner);

        $this->assertDatabaseHas('user_stories', [
            'project_id' => $this->project->id,
            'title' => 'Kullanıcı giriş yapabilmeli',
            'sprint_id' => null,
        ]);
    }

    public function test_moderator_can_create_story_via_api(): void
    {
        $this->assertTrue($this->moderator->can('create', [UserStory::class, $this->project]));

        $service = app(UserStoryService::class);
        $story = $service->create(['title' => 'Moderator story'], $this->project, $this->moderator);

        $this->assertDatabaseHas('user_stories', [
            'title' => 'Moderator story',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_member_cannot_create_story_via_api(): void
    {
        $this->assertFalse($this->member->can('create', [UserStory::class, $this->project]));
    }

    public function test_service_creates_story_with_correct_defaults(): void
    {
        $service = app(UserStoryService::class);

        $story = $service->create(
            ['title' => 'Service layer story'],
            $this->project,
            $this->owner
        );

        $this->assertEquals('Service layer story', $story->title);
        $this->assertEquals($this->project->id, $story->project_id);
        $this->assertEquals($this->owner->id, $story->created_by);
        $this->assertEquals('new', $story->status->value);
        $this->assertNull($story->sprint_id);
        $this->assertGreaterThanOrEqual(1, $story->order);
    }

    public function test_story_creation_requires_title(): void
    {
        Livewire::actingAs($this->owner)
            ->test('scrum.backlog', ['project' => $this->project])
            ->set('form.title', '')
            ->call('createStory')
            ->assertHasErrors(['form.title']);
    }
}
