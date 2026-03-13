<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rol Tabanlı Erişim Kontrolü (RBAC) Testi
 *
 * Bu test sınıfı, proje içindeki farklı rollerin (Owner, Moderator, Member)
 * çeşitli işlemlere erişim yetkilerinin doğru uygulanıp uygulanmadığını test eder.
 * Her testten önce setUp() içinde bir proje ve 3 farklı rolde kullanıcı oluşturulur:
 *   - owner: Proje sahibi (Owner rolü)
 *   - moderator: Moderatör (Moderator rolü)
 *   - member: Üye (Member rolü)
 *
 * Test Edilen Senaryolar:
 *  - test_member_cannot_create_story:
 *    "Member" rolündeki kullanıcı User Story oluşturmaya çalışır.
 *    HTTP 403 dönmesi beklenir. (Member story oluşturamaz.)
 *
 *  - test_moderator_can_create_story:
 *    "Moderator" rolündeki kullanıcı User Story oluşturur.
 *    HTTP 201 dönmesi beklenir. (Moderator story oluşturabilir.)
 *
 *  - test_member_can_create_issue:
 *    "Member" rolündeki kullanıcı Issue (bug report) oluşturur.
 *    HTTP 201 dönmesi beklenir. (Member issue oluşturabilir.)
 *
 *  - test_non_member_cannot_access_project:
 *    Projeye üye olmayan bir dış kullanıcı proje içeriğine erişmeye çalışır.
 *    HTTP 403 dönmesi beklenir. (Proje üyesi olmayanlar erişemez.)
 *
 *  - test_super_admin_can_access_any_project:
 *    Super Admin rolündeki kullanıcı, üyesi olmadığı bir projeye erişir.
 *    HTTP 200 dönmesi beklenir. (Super Admin her projeye erişebilir.)
 *
 *  - test_owner_cannot_be_removed:
 *    Moderatör, proje sahibini (Owner) projeden çıkarmaya çalışır.
 *    Owner'ın project_memberships tablosunda hâlâ mevcut olması beklenir.
 *    (BR-14: Proje sahibi projeden çıkarılamaz.)
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $moderator;

    private User $member;

    private Project $project;

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

    public function test_member_cannot_create_story(): void
    {
        $response = $this->actingAs($this->member)->postJson(
            "/api/projects/{$this->project->slug}/stories",
            ['title' => 'Test Story']
        );

        $response->assertStatus(403);
    }

    public function test_moderator_can_create_story(): void
    {
        $response = $this->actingAs($this->moderator)->postJson(
            "/api/projects/{$this->project->slug}/stories",
            ['title' => 'Test Story']
        );

        $response->assertStatus(201);
    }

    public function test_member_can_create_issue(): void
    {
        $response = $this->actingAs($this->member)->postJson(
            "/api/projects/{$this->project->slug}/issues",
            ['title' => 'Bug Report', 'type' => 'bug']
        );

        $response->assertStatus(201);
    }

    public function test_non_member_cannot_access_project(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->getJson(
            "/api/projects/{$this->project->slug}/stories"
        );

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_any_project(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->getJson(
            "/api/projects/{$this->project->slug}/stories"
        );

        $response->assertStatus(200);
    }

    public function test_owner_cannot_be_removed(): void
    {
        $response = $this->actingAs($this->moderator)->deleteJson(
            "/api/projects/{$this->project->slug}/members/{$this->owner->id}"
        );

        // Should fail — owner cannot be removed (BR-14)
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
        ]);
    }
}
