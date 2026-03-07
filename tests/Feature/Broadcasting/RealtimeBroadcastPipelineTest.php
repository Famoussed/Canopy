<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Actions\Notification\SendNotificationAction;
use App\Events\Notification\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gerçek zamanlı bildirim broadcasting pipeline'ının uçtan uca testi.
 *
 * Pipeline: Action→Event dispatch→Broadcast channel/name/payload→Echo→Livewire listener
 */
class RealtimeBroadcastPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. NotificationSent Event Configuration
    // ═══════════════════════════════════════════════════════════════════

    public function test_notification_sent_implements_should_broadcast(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
    }

    public function test_notification_sent_broadcasts_on_private_user_channel(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);

        $channels = $event->broadcastOn();
        $channelNames = collect($channels)->map(fn ($ch) => $ch->name)->toArray();

        $this->assertContains("private-user.{$this->user->id}", $channelNames);
    }

    public function test_notification_sent_broadcast_as_returns_custom_name(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);

        $this->assertEquals('notification.received', $event->broadcastAs());
    }

    public function test_notification_sent_payload_contains_required_fields(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'data' => ['task_id' => 'abc-123', 'task_title' => 'Test Task'],
        ]);

        $event = new NotificationSent($notification, $this->user->id);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertEquals($notification->id, $payload['id']);
        $this->assertEquals('task_assigned', $payload['type']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. SendNotificationAction → Event Dispatch
    // ═══════════════════════════════════════════════════════════════════

    public function test_send_notification_action_dispatches_notification_sent_event(): void
    {
        Event::fake([NotificationSent::class]);

        app(SendNotificationAction::class)->execute(
            user: $this->user,
            type: 'task_assigned',
            data: ['task_title' => 'Pipeline Test'],
        );

        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) {
            return $event->userId === $this->user->id
                && $event->notification->type === 'task_assigned';
        });
    }

    public function test_send_notification_action_dispatches_event_with_correct_user_id(): void
    {
        Event::fake([NotificationSent::class]);

        $recipient = User::factory()->create();

        app(SendNotificationAction::class)->execute(
            user: $recipient,
            type: 'member_added',
            data: ['project_name' => 'Test Project'],
        );

        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) use ($recipient) {
            return $event->userId === $recipient->id;
        });
    }

    public function test_send_notification_action_creates_notification_in_database(): void
    {
        Event::fake([NotificationSent::class]);

        app(SendNotificationAction::class)->execute(
            user: $this->user,
            type: 'story_status_changed',
            data: ['story_title' => 'DB Test'],
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'story_status_changed',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. Channel Authorization
    // ═══════════════════════════════════════════════════════════════════

    public function test_authenticated_user_can_access_own_private_channel(): void
    {
        $this->actingAs($this->user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$this->user->id}",
            'socket_id' => '12345.67890',
        ])->assertOk();
    }

    public function test_authenticated_user_cannot_access_other_users_private_channel(): void
    {
        config(['broadcasting.default' => 'reverb']);

        $otherUser = User::factory()->create();

        $this->actingAs($this->user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$otherUser->id}",
            'socket_id' => '12345.67890',
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_private_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$this->user->id}",
            'socket_id' => '12345.67890',
        ])->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. Livewire Echo Listener Configuration
    // ═══════════════════════════════════════════════════════════════════

    public function test_notification_bell_sets_user_id_property_on_mount(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('userId', $this->user->id);
    }

    public function test_notification_bell_registers_echo_listener_for_user_channel(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test('notification.notification-bell');

        $listeners = $component->instance()->getListeners();

        $expectedKey = "echo-private:user.{$this->user->id},.notification.received";
        $this->assertArrayHasKey($expectedKey, $listeners);
        $this->assertEquals('incrementUnreadCount', $listeners[$expectedKey]);
    }

    public function test_echo_listener_channel_matches_event_broadcast_channel(): void
    {
        // Event side: channel name
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);
        $eventChannelName = collect($event->broadcastOn())->first()->name;

        // Livewire side: listener key
        $this->actingAs($this->user);
        $component = Livewire::test('notification.notification-bell');
        $listeners = $component->instance()->getListeners();
        $listenerKey = array_keys($listeners)[0];

        // Extract channel from listener: "echo-private:user.{uuid},.event" → "user.{uuid}"
        $listenerChannel = str_replace('echo-private:', '', explode(',', $listenerKey)[0]);

        // Event channel: "private-user.{uuid}" → "user.{uuid}"
        $eventChannel = str_replace('private-', '', $eventChannelName);

        $this->assertEquals($eventChannel, $listenerChannel);
    }

    public function test_echo_listener_event_name_matches_broadcast_as_with_dot_prefix(): void
    {
        // Event side
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);
        $broadcastName = $event->broadcastAs();

        // Livewire side
        $this->actingAs($this->user);
        $component = Livewire::test('notification.notification-bell');
        $listeners = $component->instance()->getListeners();
        $listenerKey = array_keys($listeners)[0];

        // Extract event name: after the comma, with dot prefix
        $listenerEventName = explode(',', $listenerKey)[1];

        // broadcastAs() custom name requires dot prefix in Echo listener
        $this->assertEquals(".{$broadcastName}", $listenerEventName);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. Livewire incrementUnreadCount Method (Echo callback)
    // ═══════════════════════════════════════════════════════════════════

    public function test_increment_unread_count_increments_by_one(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 1);
    }

    public function test_multiple_echo_triggers_increment_count_correctly(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('incrementUnreadCount')
            ->call('incrementUnreadCount')
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 3);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. Full Pipeline: Action → DB → Event → Channel match → Listener
    // ═══════════════════════════════════════════════════════════════════

    public function test_full_pipeline_action_dispatches_event_on_matching_channel(): void
    {
        Event::fake([NotificationSent::class]);

        app(SendNotificationAction::class)->execute(
            user: $this->user,
            type: 'task_assigned',
            data: ['task_title' => 'Full Pipeline'],
        );

        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) {
            $channels = $event->broadcastOn();
            $channelNames = collect($channels)->map(fn ($ch) => $ch->name)->toArray();

            return in_array("private-user.{$this->user->id}", $channelNames)
                && $event->broadcastAs() === 'notification.received'
                && $event->userId === $this->user->id;
        });
    }

    public function test_full_pipeline_event_channel_and_name_align_with_livewire_listener(): void
    {
        // 1. Event broadcasts on correct channel with correct name
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);
        $event = new NotificationSent($notification, $this->user->id);

        $eventChannelName = collect($event->broadcastOn())->first()->name;
        $this->assertStringContainsString($this->user->id, $eventChannelName);
        $this->assertEquals('notification.received', $event->broadcastAs());

        // 2. Livewire listener is configured for exactly this channel + event
        $this->actingAs($this->user);
        $component = Livewire::test('notification.notification-bell');
        $listeners = $component->instance()->getListeners();

        $expectedListenerKey = "echo-private:user.{$this->user->id},.notification.received";
        $this->assertArrayHasKey($expectedListenerKey, $listeners);

        // 3. Mount already counted the existing notification (unreadCount = 1)
        $component->assertSet('unreadCount', 1);

        // 4. The handler method works when invoked (simulates Echo trigger)
        $component->call('incrementUnreadCount')
            ->assertSet('unreadCount', 2);
    }

    public function test_pipeline_all_notification_types_dispatch_same_broadcast_event(): void
    {
        Event::fake([NotificationSent::class]);

        $types = ['task_assigned', 'story_status_changed', 'task_status_changed', 'issue_status_changed', 'member_added'];

        foreach ($types as $type) {
            app(SendNotificationAction::class)->execute(
                user: $this->user,
                type: $type,
                data: ['test' => true],
            );
        }

        Event::assertDispatched(NotificationSent::class, count($types));

        // All use same broadcastAs, so same Livewire listener handles them
        Event::assertDispatched(NotificationSent::class, function (NotificationSent $event) {
            return $event->broadcastAs() === 'notification.received';
        });
    }
}
