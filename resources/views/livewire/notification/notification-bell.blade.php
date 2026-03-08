<?php

use App\Models\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public int $unreadCount = 0;

    public bool $showPanel = false;

    public string $userId = '';

    public function mount(): void
    {
        $this->userId = auth()->id();

        $this->unreadCount = Notification::where('user_id', auth()->id())
            ->unread()
            ->count();
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "echo-private:user.{$this->userId},.notification.received" => 'incrementUnreadCount',
        ];
    }

    public function incrementUnreadCount(): void
    {
        $this->unreadCount++;
        unset($this->notifications);
    }

    public function togglePanel(): void
    {
        $this->showPanel = ! $this->showPanel;

        if ($this->showPanel) {
            unset($this->notifications);
        }
    }

    #[Computed]
    public function notifications(): \Illuminate\Support\Collection
    {
        return Notification::where('user_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Notification::where('user_id', auth()->id())
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->first();

        if ($notification) {
            $notification->markAsRead();
            $this->unreadCount = max(0, $this->unreadCount - 1);
            unset($this->notifications);
        }
    }

    public function markAllAsRead(): void
    {
        Notification::where('user_id', auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->unreadCount = 0;
        unset($this->notifications);
    }

    private function notificationLabel(string $type): string
    {
        return match ($type) {
            'story_status_changed' => 'Story durumu değişti',
            'task_status_changed' => 'Görev durumu değişti',
            'issue_status_changed' => 'Issue durumu değişti',
            'task_assigned' => 'Görev atandı',
            'member_added' => 'Projeye eklendi',
            default => 'Bildirim',
        };
    }

    private function notificationIcon(string $type): string
    {
        return match ($type) {
            'story_status_changed' => 'book-open',
            'task_status_changed' => 'clipboard-document-check',
            'issue_status_changed' => 'exclamation-triangle',
            'task_assigned' => 'user-plus',
            'member_added' => 'user-group',
            default => 'bell',
        };
    }
}

?>

<div class="relative" x-data="{ open: @entangle('showPanel') }">
    <flux:button variant="ghost" size="sm" icon="bell" aria-label="Bildirimler" wire:click="togglePanel">
        @if ($unreadCount > 0)
            <flux:badge color="red" size="sm" class="absolute -top-1 -right-1 min-w-5 h-5 flex items-center justify-center text-[10px]">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </flux:badge>
        @endif
    </flux:button>

    @if ($showPanel)
        <div
            x-show="open"
            x-on:click.outside="open = false; $wire.set('showPanel', false)"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute right-0 top-full mt-2 w-80 rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800 z-50 overflow-hidden"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading size="sm">Bildirimler</flux:heading>
                @if ($unreadCount > 0)
                    <flux:button variant="ghost" size="xs" wire:click="markAllAsRead">
                        Tümünü okundu işaretle
                    </flux:button>
                @endif
            </div>

            {{-- Notification List --}}
            <div class="max-h-80 overflow-y-auto">
                @forelse ($this->notifications as $notification)
                    <div
                        wire:key="notification-{{ $notification->id }}"
                        class="flex items-start gap-3 px-4 py-3 border-b border-zinc-100 dark:border-zinc-700/50 last:border-b-0 {{ $notification->read_at ? 'opacity-60' : 'bg-indigo-50/50 dark:bg-indigo-900/10' }}"
                    >
                        <div class="mt-0.5 shrink-0">
                            <flux:icon :name="$this->notificationIcon($notification->type)" variant="mini" class="size-4 text-zinc-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $this->notificationLabel($notification->type) }}
                            </p>
                            @if (isset($notification->data['message']))
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                    {{ $notification->data['message'] }}
                                </p>
                            @elseif (isset($notification->data['changed_by']))
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                                    {{ $notification->data['changed_by'] }} tarafından
                                </p>
                            @endif
                            <p class="text-[10px] text-zinc-400 dark:text-zinc-500 mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                        @unless ($notification->read_at)
                            <flux:button variant="ghost" size="xs" icon="check" wire:click="markAsRead('{{ $notification->id }}')" aria-label="Okundu işaretle" class="shrink-0" />
                        @endunless
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <flux:icon name="bell-slash" variant="outline" class="size-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                        <flux:text size="sm" class="text-zinc-400">Bildirim bulunmuyor</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
