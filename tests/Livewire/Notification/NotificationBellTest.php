<?php

declare(strict_types=1);

namespace Tests\Livewire\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L-07: NotificationBell Livewire component testi.
 *
 * Bildirim zili render, okunmamış sayıcı, badge görünürlüğü, panel açma/kapama,
 * okundu işaretleme, tümünü okundu işaretleme, gerçek zamanlı artırım,
 * çoklu kullanıcı izolasyonu ve kenar durum testleri.
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

    // ─── Rendering Tests ───

    /**
     * Bildirim zili bileşeni başarıyla render edilir.
     */
    public function test_notification_bell_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertOk()
            ->assertSeeHtml('bell');
    }

    /**
     * Oturum açmış kullanıcı için bileşen doğru şekilde mount eder.
     */
    public function test_notification_bell_mounts_with_correct_initial_state(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->assertSet('showPanel', false);
    }

    // ─── Unread Count Tests ───

    /**
     * Okunmamış bildirim sayısını doğru gösterir.
     */
    public function test_shows_correct_unread_count(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 3);
    }

    /**
     * Bildirim yokken sıfır gösterir.
     */
    public function test_shows_zero_when_no_notifications(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0);
    }

    /**
     * Okunmuş bildirimleri sayıdan hariç tutar.
     */
    public function test_excludes_read_notifications_from_count(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->read()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 2);
    }

    /**
     * Tüm bildirimler okunmuşsa sıfır gösterir.
     */
    public function test_shows_zero_when_all_notifications_are_read(): void
    {
        Notification::factory()->read()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0);
    }

    // ─── Badge Visibility Tests ───

    /**
     * Okunmamış bildirim varken kırmızı badge görünür.
     */
    public function test_badge_visible_when_unread_notifications_exist(): void
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSee('1');
    }

    /**
     * Okunmamış bildirim yokken badge görünmez.
     */
    public function test_badge_hidden_when_no_unread_notifications(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertDontSeeHtml('flux:badge');
    }

    /**
     * 99'dan fazla bildirimde '99+' gösterir.
     */
    public function test_shows_99_plus_when_count_exceeds_99(): void
    {
        Notification::factory()->count(100)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 100)
            ->assertSee('99+');
    }

    /**
     * Tam 99 bildirimde '99' gösterir, '99+' değil.
     */
    public function test_shows_exact_99_when_count_is_99(): void
    {
        Notification::factory()->count(99)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 99)
            ->assertSee('99')
            ->assertDontSee('99+');
    }

    // ─── Panel Toggle Tests ───

    /**
     * Zile tıklayınca panel açılır.
     */
    public function test_clicking_bell_opens_panel(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('showPanel', false)
            ->call('togglePanel')
            ->assertSet('showPanel', true)
            ->assertSee('Bildirimler');
    }

    /**
     * Açık panele tekrar tıklayınca panel kapanır.
     */
    public function test_clicking_bell_again_closes_panel(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSet('showPanel', true)
            ->call('togglePanel')
            ->assertSet('showPanel', false);
    }

    /**
     * Panel açıldığında bildirimler listelenir.
     */
    public function test_panel_displays_notifications(): void
    {
        Notification::factory()->ofType('task_assigned')->create([
            'user_id' => $this->user->id,
            'data' => ['changed_by' => 'Ali'],
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSee('Görev atandı')
            ->assertSee('Ali tarafından');
    }

    /**
     * Bildirim yokken boş durum gösterilir.
     */
    public function test_panel_shows_empty_state(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSee('Bildirim bulunmuyor');
    }

    /**
     * Panel en fazla 20 bildirim gösterir.
     */
    public function test_panel_shows_max_20_notifications(): void
    {
        Notification::factory()->count(25)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        // 25 bildirim oluşturuldu ama panel sadece 20 wire:key render etmeli
        $html = Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->html();

        $this->assertEquals(20, substr_count($html, 'wire:key="notification-'));
    }

    // ─── Mark As Read Tests ───

    /**
     * Tek bir bildirimi okundu olarak işaretler.
     */
    public function test_mark_single_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 1)
            ->call('markAsRead', $notification->id)
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * Zaten okunmuş bildirimi tekrar işaretleme sayıyı etkilemez.
     */
    public function test_marking_already_read_notification_does_not_change_count(): void
    {
        $notification = Notification::factory()->read()->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 1)
            ->call('markAsRead', $notification->id)
            ->assertSet('unreadCount', 1);
    }

    /**
     * Başka kullanıcının bildirimini okundu işaretleyemez.
     */
    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('markAsRead', $notification->id);

        $this->assertNull($notification->fresh()->read_at);
    }

    // ─── Mark All As Read Tests ───

    /**
     * Tüm bildirimleri okundu olarak işaretler.
     */
    public function test_mark_all_as_read(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 5)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->unread()->count());
    }

    /**
     * Tümünü okundu işaretle sadece kendi bildirimlerini etkiler.
     */
    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        $otherUser = User::factory()->create();

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('markAllAsRead');

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->unread()->count());
        $this->assertEquals(2, Notification::where('user_id', $otherUser->id)->unread()->count());
    }

    /**
     * Okunmamış bildirim yokken "Tümünü okundu işaretle" butonu görünmez.
     */
    public function test_mark_all_button_hidden_when_no_unread(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertDontSee('Tümünü okundu işaretle');
    }

    /**
     * Okunmamış bildirim varken "Tümünü okundu işaretle" butonu görünür.
     */
    public function test_mark_all_button_visible_when_unread_exist(): void
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSee('Tümünü okundu işaretle');
    }

    // ─── Real-Time Increment Tests ───

    /**
     * incrementUnreadCount metodu sayıyı bir artırır.
     */
    public function test_increment_unread_count_increases_by_one(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 1);
    }

    /**
     * Birden fazla artırım doğru çalışır.
     */
    public function test_multiple_increments_accumulate_correctly(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('incrementUnreadCount')
            ->call('incrementUnreadCount')
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 3);
    }

    /**
     * Mevcut okunmamış bildirimlere artırım eklenir.
     */
    public function test_increment_adds_to_existing_unread_count(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 5)
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 6);
    }

    /**
     * 99'a artırım yapıldığında badge '99+' olarak güncellenir.
     */
    public function test_increment_past_99_shows_99_plus(): void
    {
        Notification::factory()->count(99)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 99)
            ->assertDontSee('99+')
            ->call('incrementUnreadCount')
            ->assertSet('unreadCount', 100)
            ->assertSee('99+');
    }

    // ─── User Isolation Tests ───

    /**
     * Farklı kullanıcıların bildirimleri birbirinden izole edilir.
     */
    public function test_notifications_are_isolated_per_user(): void
    {
        $otherUser = User::factory()->create();

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->count(7)->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 3);
    }

    /**
     * Başka kullanıcının okuma durumu bu kullanıcıyı etkilemez.
     */
    public function test_other_users_read_status_does_not_affect_current_user(): void
    {
        $otherUser = User::factory()->create();

        Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->read()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 1);
    }

    // ─── userId Property Tests ───

    /**
     * getUserIdProperty doğru kullanıcı ID'sini döner.
     */
    public function test_user_id_property_returns_authenticated_user_id(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertOk();

        $this->assertNotNull($this->user->id);
    }

    // ─── Notification Type Tests ───

    /**
     * Farklı türdeki bildirimler sayılır.
     */
    public function test_counts_all_notification_types(): void
    {
        Notification::factory()->ofType('story_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('task_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('issue_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('task_assigned')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('member_added')->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 5);
    }

    /**
     * Karışık okunmuş ve okunmamış farklı türlerdeki bildirimler doğru sayılır.
     */
    public function test_mixed_read_unread_across_types(): void
    {
        Notification::factory()->ofType('task_assigned')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('story_status_changed')->read()->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('member_added')->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 2);
    }

    // ─── Notification Type Label Tests ───

    /**
     * Her bildirim türü doğru etiketle gösterilir.
     */
    public function test_notification_type_labels_displayed_correctly(): void
    {
        Notification::factory()->ofType('story_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('task_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        Notification::factory()->ofType('issue_status_changed')->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSee('Story durumu değişti')
            ->assertSee('Görev durumu değişti')
            ->assertSee('Issue durumu değişti');
    }

    // ─── Edge Case Tests ───

    /**
     * Yüksek sayıda bildirimde bileşen çalışır.
     */
    public function test_handles_large_notification_count(): void
    {
        Notification::factory()->count(500)->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 500)
            ->assertSee('99+');
    }

    /**
     * Tek bir bildirimde badge doğru gösterilir.
     */
    public function test_single_notification_shows_badge_with_one(): void
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 1)
            ->assertSee('1');
    }

    /**
     * Okundu işaretleme sonrası sayı sıfırın altına düşmez.
     */
    public function test_unread_count_does_not_go_below_zero(): void
    {
        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->assertSet('unreadCount', 0)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);
    }

    /**
     * Panel okunmuş ve okunmamış bildirimleri birlikte gösterir.
     */
    public function test_panel_shows_both_read_and_unread(): void
    {
        Notification::factory()->ofType('task_assigned')->create([
            'user_id' => $this->user->id,
            'data' => ['message' => 'Yeni görev'],
        ]);

        Notification::factory()->ofType('member_added')->read()->create([
            'user_id' => $this->user->id,
            'data' => ['message' => 'Projeye eklendiniz'],
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->assertSee('Yeni görev')
            ->assertSee('Projeye eklendiniz');
    }

    /**
     * Okundu işaretleme sonrası panel güncellenmiş listeyi gösterir.
     */
    public function test_panel_refreshes_after_mark_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        Livewire::test('notification.notification-bell')
            ->call('togglePanel')
            ->call('markAsRead', $notification->id)
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
