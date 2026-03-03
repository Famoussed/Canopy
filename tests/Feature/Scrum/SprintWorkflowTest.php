<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint İş Akışı (Workflow) Testi
 *
 * Bu test sınıfı, Sprint yaşam döngüsünün tamamını test eder: oluşturma,
 * başlatma, ikinci Sprint kısıtlaması ve Sprint kapatma sırasındaki
 * tamamlanmamış story'lerin backlog'a döndürülmesi.
 * Her testten önce setUp() içinde bir Owner kullanıcısı ve proje oluşturulur.
 *
 * Test Edilen Senaryolar:
 *  - test_can_create_sprint:
 *    POST /api/projects/{slug}/sprints isteğiyle yeni Sprint oluşturulur.
 *    HTTP 201 dönmesi, Sprint adının doğru olması ve durumunun
 *    "planning" olması beklenir.
 *
 *  - test_can_start_sprint:
 *    "Planning" durumundaki Sprint başlatılır (POST .../sprints/{id}/start).
 *    HTTP 200 dönmesi ve durumunun "active" olması beklenir.
 *
 *  - test_cannot_start_second_sprint:
 *    Projede zaten aktif bir Sprint varken ikinci bir Sprint başlatılmaya
 *    çalışılır. HTTP 422 (Unprocessable Entity) dönmesi beklenir.
 *    (İş Kuralı: Aynı anda yalnızca bir aktif Sprint olabilir.)
 *
 *  - test_close_sprint_returns_unfinished_stories_to_backlog:
 *    Aktif bir Sprint'e "InProgress" durumunda bir User Story eklenir,
 *    ardından Sprint kapatılır (POST .../sprints/{id}/close).
 *    HTTP 200 dönmesi, Sprint durumunun "closed" olması ve tamamlanmamış
 *    story'nin sprint_id alanının null'a dönmesi (backlog'a geri atılması)
 *    beklenir. (BR-08: Tamamlanmamış story'ler backlog'a döner.)
 */
class SprintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->memberships()->create([
            'user_id' => $this->owner->id,
            'role' => ProjectRole::Owner,
        ]);
    }

    public function test_can_create_sprint(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->slug}/sprints",
            [
                'name' => 'Sprint 1',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
            ]
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Sprint 1')
            ->assertJsonPath('data.status', 'planning');
    }

    public function test_can_start_sprint(): void
    {
        $sprint = Sprint::factory()->create([
            'project_id' => $this->project->id,
            'status' => SprintStatus::Planning,
        ]);

        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint->id}/start"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_cannot_start_second_sprint(): void
    {
        Sprint::factory()->active()->create(['project_id' => $this->project->id]);
        $sprint2 = Sprint::factory()->create(['project_id' => $this->project->id]);

        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint2->id}/start"
        );

        $response->assertStatus(422);
    }

    public function test_close_sprint_returns_unfinished_stories_to_backlog(): void
    {
        $sprint = Sprint::factory()->active()->create(['project_id' => $this->project->id]);
        $story = UserStory::factory()->inProgress()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $sprint->id,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->postJson(
            "/api/projects/{$this->project->slug}/sprints/{$sprint->id}/close"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // BR-08: Unfinished story goes back to backlog
        $this->assertNull($story->fresh()->sprint_id);
    }
}
