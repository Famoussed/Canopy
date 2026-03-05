<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name', 'Canopy') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center">
    <div class="w-full max-w-md px-6">
        <div class="mb-8 text-center">
            <a href="/" wire:navigate class="inline-flex items-center gap-2">
                <div class="size-9 rounded-xl bg-indigo-600 flex items-center justify-center">
                    <flux:icon name="squares-2x2" variant="mini" class="size-5 text-white" />
                </div>
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">Canopy</span>
            </a>
        </div>

        {{ $slot }}
    </div>

    @fluxScripts
</body>
</html>
