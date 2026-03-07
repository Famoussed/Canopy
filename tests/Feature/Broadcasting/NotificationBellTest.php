<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NotificationBell Livewire bileşeni testleri.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_notification_bell_renders(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertOk();
    }

    public function test_notification_bell_shows_unread_count(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Test'],
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'member_added',
            'data' => ['message' => 'Test 2'],
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 2);
    }

    public function test_notification_bell_shows_zero_when_no_unread(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0);
    }

    public function test_notification_bell_excludes_read_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['message' => 'Read'],
            'read_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'member_added',
            'data' => ['message' => 'Unread'],
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 1);
    }

    public function test_increment_unread_count_method(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 1);
    }
}
