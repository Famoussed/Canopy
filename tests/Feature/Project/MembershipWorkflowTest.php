<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Actions\Project\AddMemberAction;
use App\Actions\Project\RemoveMemberAction;
use App\Enums\ProjectRole;
use App\Exceptions\DuplicateMemberException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Üyelik Workflow Testleri
 *
 * Bu test sınıfı Project üzerindeki üye ekleme, çıkarma ve yetki süreçlerini doğrular.
 * ProjectService yalnızca create/update/delete sarmalar; üyelik işlemleri
 * AddMemberAction ve RemoveMemberAction üzerinden doğrudan test edilir.
 *
 * Test Edilen Senaryolar:
 * - test_project_owner_can_add_member: Proje sahibinin yeni takım üyesi eklemesi.
 * - test_cannot_add_duplicate_member: Zaten takımda olan birinin tekrar eklenmesi durumunda fırlatılacak hata.
 * - test_member_role_can_be_updated: Üye rolünün güncellenmesi (membership modeli üzerinden).
 * - test_member_can_be_removed: Proje üyesinin projeden çıkarılması.
 */
class MembershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Project $project;
    protected AddMemberAction $addMemberAction;
    protected RemoveMemberAction $removeMemberAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->addMemberAction = app(AddMemberAction::class);
        $this->removeMemberAction = app(RemoveMemberAction::class);
    }

    public function test_project_owner_can_add_member(): void
    {
        $this->addMemberAction->execute($this->project, $this->member, ProjectRole::Member);

        // Tablo adı: project_memberships (project_user pivot değil)
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'role' => ProjectRole::Member->value,
        ]);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $this->addMemberAction->execute($this->project, $this->member, ProjectRole::Member);

        $this->expectException(DuplicateMemberException::class);

        // BR-12: Zaten ekli olan birini tekrar eklemeye çalışmak DuplicateMemberException firlatmali
        $this->addMemberAction->execute($this->project, $this->member, ProjectRole::Member);
    }

    public function test_member_role_can_be_updated(): void
    {
        // AddMemberAction membership kaydini doner; dogrudan update() cagrilabilir
        $membership = $this->addMemberAction->execute($this->project, $this->member, ProjectRole::Member);

        $membership->update(['role' => ProjectRole::Moderator]);

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'role' => ProjectRole::Moderator->value,
        ]);
    }

    public function test_member_can_be_removed(): void
    {
        $this->addMemberAction->execute($this->project, $this->member, ProjectRole::Member);

        $this->removeMemberAction->execute($this->project, $this->member);

        // Uye project_memberships tablosundan silinmis olmali
        $this->assertDatabaseMissing('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
        ]);
    }
}
