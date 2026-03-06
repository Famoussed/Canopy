<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F-13 & F-14: Bildirim oluşturma ve okundu işaretleme testleri.
 *
 * Bildirim API endpoint'lerini ve NotificationService'i test eder.
 */
class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_notification_can_be_created_via_service(): void
    {
        $service = app(NotificationService::class);

        $service->send($this->user, 'task_assigned', [
            'task_title' => 'Design Navbar',
            'project' => 'Canopy',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
        ]);
    }

    public function test_user_can_list_unread_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Test'],
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'story_created',
            'data' => ['message' => 'Test 2'],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.unread_count', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Test'],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/notifications/mark-read', [
            'id' => $notification->id,
        ]);

        $response->assertStatus(204);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Test 1'],
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'story_created',
            'data' => ['message' => 'Test 2'],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(204);
        $this->assertEquals(0, $this->user->notifications()->unread()->count());
    }

    public function test_read_notifications_not_shown_in_list(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Read'],
            'read_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'story_created',
            'data' => ['message' => 'Unread'],
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $response->assertJsonCount(1, 'data');
    }
}
