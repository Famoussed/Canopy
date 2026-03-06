<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Services\UserStoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Story Oluşturma Testi
 *
 * Bu test sınıfı User Story oluşturma sürecinin hem API endpoint'i hem de
 * Service katmanı üzerinden doğru çalıştığını doğrular. Livewire backlog
 * bileşenindeki `createStory` metodunun çağırdığı Service metodu ile
 * API controller'ın çağırdığı aynı Service metodunun argüman uyumluluğu
 * burada garanti altına alınır.
 *
 * Test Edilen Senaryolar:
 * - test_owner_can_create_story_via_api: Owner rolündeki kullanıcı API üzerinden story oluşturur.
 * - test_moderator_can_create_story_via_api: Moderator rolündeki kullanıcı API üzerinden story oluşturur.
 * - test_member_cannot_create_story_via_api: Member rolündeki kullanıcı story oluşturamaz (403).
 * - test_service_creates_story_with_correct_defaults: Service üzerinden story oluşturulduğunda varsayılan alanlar doğru olur.
 * - test_story_creation_requires_title: Title alanı olmadan story oluşturulamaz (422).
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
        $response = $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->slug}/stories", [
                'title' => 'Kullanıcı giriş yapabilmeli',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_stories', [
            'project_id' => $this->project->id,
            'title' => 'Kullanıcı giriş yapabilmeli',
            'status' => 'new',
            'sprint_id' => null,
        ]);
    }

    public function test_moderator_can_create_story_via_api(): void
    {
        $response = $this->actingAs($this->moderator)
            ->postJson("/api/projects/{$this->project->slug}/stories", [
                'title' => 'Moderator story',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_stories', [
            'title' => 'Moderator story',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_member_cannot_create_story_via_api(): void
    {
        $response = $this->actingAs($this->member)
            ->postJson("/api/projects/{$this->project->slug}/stories", [
                'title' => 'Member story attempt',
            ]);

        $response->assertStatus(403);
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
        $response = $this->actingAs($this->owner)
            ->postJson("/api/projects/{$this->project->slug}/stories", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
