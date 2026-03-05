@props(['label', 'value', 'icon' => null, 'trend' => null, 'trendUp' => null])

<flux:card class="p-4">
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <flux:subheading class="text-xs uppercase tracking-wide">{{ $label }}</flux:subheading>
            <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $value }}</p>
        </div>
        @if ($icon)
            <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 p-3">
                <flux:icon :name="$icon" class="size-6 text-indigo-600" />
            </div>
        @endif
    </div>
    @if ($trend)
        <div class="mt-2 flex items-center gap-1 text-xs {{ $trendUp ? 'text-emerald-600' : 'text-red-500' }}">
            <flux:icon :name="$trendUp ? 'arrow-trending-up' : 'arrow-trending-down'" variant="mini" class="size-4" />
            <span>{{ $trend }}</span>
        </div>
    @endif
</flux:card>
