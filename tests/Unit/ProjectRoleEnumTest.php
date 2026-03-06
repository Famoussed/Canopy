<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProjectRole;
use PHPUnit\Framework\TestCase;

/**
 * U-11: ProjectRole enum hiyerarşi testi.
 *
 * Rol sıralamasının (rank) ve isAtLeast() metodunun doğru çalıştığını test eder.
 */
class ProjectRoleEnumTest extends TestCase
{
    public function test_owner_has_highest_rank(): void
    {
        $this->assertEquals(3, ProjectRole::Owner->rank());
        $this->assertEquals(2, ProjectRole::Moderator->rank());
        $this->assertEquals(1, ProjectRole::Member->rank());
    }

    public function test_owner_is_at_least_all_roles(): void
    {
        $this->assertTrue(ProjectRole::Owner->isAtLeast(ProjectRole::Owner));
        $this->assertTrue(ProjectRole::Owner->isAtLeast(ProjectRole::Moderator));
        $this->assertTrue(ProjectRole::Owner->isAtLeast(ProjectRole::Member));
    }

    public function test_moderator_is_at_least_moderator_and_member(): void
    {
        $this->assertFalse(ProjectRole::Moderator->isAtLeast(ProjectRole::Owner));
        $this->assertTrue(ProjectRole::Moderator->isAtLeast(ProjectRole::Moderator));
        $this->assertTrue(ProjectRole::Moderator->isAtLeast(ProjectRole::Member));
    }

    public function test_member_is_only_at_least_member(): void
    {
        $this->assertFalse(ProjectRole::Member->isAtLeast(ProjectRole::Owner));
        $this->assertFalse(ProjectRole::Member->isAtLeast(ProjectRole::Moderator));
        $this->assertTrue(ProjectRole::Member->isAtLeast(ProjectRole::Member));
    }

    public function test_labels_are_defined(): void
    {
        $this->assertNotEmpty(ProjectRole::Owner->label());
        $this->assertNotEmpty(ProjectRole::Moderator->label());
        $this->assertNotEmpty(ProjectRole::Member->label());
    }
}
