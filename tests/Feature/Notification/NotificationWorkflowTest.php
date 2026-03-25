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
 * NotificationService üzerinden bildirim iş akışını test eder.
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

    public function test_unread_notifications_count_increments_on_send(): void
    {
        $service = app(NotificationService::class);

        $this->assertEquals(0, $service->getUnreadCount($this->user));

        $service->send($this->user, 'task_assigned', ['task_title' => 'Test']);
        $service->send($this->user, 'story_status_changed', ['story_title' => 'Story']);

        $this->assertEquals(2, $service->getUnreadCount($this->user));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Test'],
        ]);

        $service = app(NotificationService::class);
        $service->markAsRead($notification->id, $this->user);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'task_assigned', 'data' => ['msg' => '1']]);
        Notification::create(['user_id' => $this->user->id, 'type' => 'story_status_changed', 'data' => ['msg' => '2']]);

        $service = app(NotificationService::class);
        $service->markAllAsRead($this->user);

        $this->assertEquals(0, $this->user->notifications()->unread()->count());
    }

    public function test_unread_count_decrements_after_mark_as_read(): void
    {
        $service = app(NotificationService::class);

        $n1 = Notification::create(['user_id' => $this->user->id, 'type' => 'task_assigned', 'data' => []]);
        Notification::create(['user_id' => $this->user->id, 'type' => 'member_added', 'data' => []]);

        $this->assertEquals(2, $service->getUnreadCount($this->user));

        $service->markAsRead($n1->id, $this->user);

        $this->assertEquals(1, $service->getUnreadCount($this->user));
    }

    public function test_read_notifications_are_not_counted_as_unread(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Read'],
            'read_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'story_status_changed',
            'data' => ['message' => 'Unread'],
        ]);

        $service = app(NotificationService::class);

        $this->assertEquals(1, $service->getUnreadCount($this->user));
    }
}
