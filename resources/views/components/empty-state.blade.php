@props(['icon' => 'folder-open', 'heading' => 'Henüz içerik yok', 'description' => '', 'actionText' => null, 'actionUrl' => null])

<flux:card class="text-center py-16">
    <div class="flex flex-col items-center gap-4">
        <flux:icon :name="$icon" class="size-12 text-zinc-300" />
        <div>
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            @if ($description)
                <flux:subheading>{{ $description }}</flux:subheading>
            @endif
        </div>
        @if ($actionText && $actionUrl)
            <flux:button variant="primary" icon="plus" :href="$actionUrl" wire:navigate>
                {{ $actionText }}
            </flux:button>
        @endif
        {{ $slot }}
    </div>
</flux:card>
