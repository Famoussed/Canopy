<?php

declare(strict_types=1);

namespace Tests\Livewire\Notification;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4: Notification Bell Repositioning Tests
 *
 * Bildirim zilinin sidebar'dan çıkarılıp header alanına taşınmasını doğrular.
 * Popup yönünün yukarıdan aşağıya değiştiğini kontrol eder.
 */
class NotificationBellPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->project->memberships()->create([
            'user_id' => $this->user->id,
            'role' => ProjectRole::Owner,
        ]);
    }

    public function test_notification_bell_is_in_content_header_bar(): void
    {
        $response = $this->actingAs($this->user)->get("/projects/{$this->project->slug}");
        $response->assertStatus(200);

        $html = $response->getContent();

        // The notification bell should be inside a header bar in the main content area
        // We look for a data-notification-header wrapper around the bell
        $this->assertMatchesRegularExpression(
            '/data-notification-header.*notification-bell/s',
            $html,
            'Notification bell should be inside a header bar with data-notification-header attribute.'
        );
    }

    public function test_notification_bell_appears_in_header_area(): void
    {
        $response = $this->actingAs($this->user)->get("/projects/{$this->project->slug}");
        $response->assertStatus(200);

        $html = $response->getContent();

        // Notification bell should be in the page content (outside sidebar)
        $this->assertStringContainsString('notification-bell', $html);
    }

    public function test_notification_bell_popup_opens_downward(): void
    {
        $component = \Livewire\Livewire::actingAs($this->user)
            ->test('notification.notification-bell')
            ->set('showPanel', true);

        $html = $component->html();

        // Popup should use top-full (opens downward), NOT bottom-full (opens upward)
        $this->assertStringContainsString('top-full', $html);
        $this->assertStringNotContainsString('bottom-full', $html);
    }

    public function test_notification_bell_visible_on_project_pages(): void
    {
        // Test on multiple project pages
        $pages = [
            "/projects/{$this->project->slug}",
            "/projects/{$this->project->slug}/backlog",
            "/projects/{$this->project->slug}/issues",
            "/projects/{$this->project->slug}/sprints",
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->user)->get($page);
            $response->assertStatus(200);
            $this->assertStringContainsString('notification-bell', $response->getContent(),
                "Notification bell should be visible on {$page}");
        }
    }

    public function test_notification_bell_not_visible_for_guests(): void
    {
        $response = $this->get("/projects/{$this->project->slug}");

        // Should redirect to login for unauthenticated users
        $response->assertRedirect();
    }
}
