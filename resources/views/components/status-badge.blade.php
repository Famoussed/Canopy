@props(['status'])

@php
    $color = match(true) {
        method_exists($status, 'color') => $status->color(),
        default => 'zinc',
    };
    $label = method_exists($status, 'label') ? $status->label() : $status->value;
@endphp

<flux:badge size="sm" :color="$color" {{ $attributes }}>
    {{ $label }}
</flux:badge>
