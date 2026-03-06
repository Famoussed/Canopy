<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Project\AddMemberAction;
use App\Enums\ProjectRole;
use App\Exceptions\DuplicateMemberException;
use App\Exceptions\MaxMembersExceededException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AddMemberAction Unit Testi.
 *
 * Proje üye ekleme iş kurallarını doğrular:
 * - Maksimum 5 üye limiti (BR-11)
 * - Tekil üyelik kontrolü (BR-12)
 * - Başarılı üye ekleme
 *
 * Test Edilen Senaryolar:
 *  - test_cannot_add_member_when_max_limit_reached:
 *    Proje zaten 5 üyeye sahipken yeni üye eklemeye çalışılır.
 *    MaxMembersExceededException fırlatılması beklenir.
 *
 *  - test_cannot_add_duplicate_member:
 *    Zaten projede olan bir kullanıcı tekrar eklenmeye çalışılır.
 *    DuplicateMemberException fırlatılması beklenir.
 *
 *  - test_can_add_member_successfully:
 *    Limitlerin altında ve mevcut olmayan bir kullanıcı başarıyla eklenir.
 */
class AddMemberActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_add_member_when_max_limit_reached(): void
    {
        $project = Project::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $project->memberships()->create([
                'user_id' => User::factory()->create()->id,
                'role' => ProjectRole::Member,
            ]);
        }

        $newUser = User::factory()->create();

        $this->expectException(MaxMembersExceededException::class);

        app(AddMemberAction::class)->execute($project, $newUser, ProjectRole::Member);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $project->memberships()->create([
            'user_id' => $user->id,
            'role' => ProjectRole::Member,
        ]);

        $this->expectException(DuplicateMemberException::class);

        app(AddMemberAction::class)->execute($project, $user, ProjectRole::Member);
    }

    public function test_can_add_member_successfully(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $membership = app(AddMemberAction::class)->execute($project, $user, ProjectRole::Member);

        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals(ProjectRole::Member, $membership->role);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);
    }
}
