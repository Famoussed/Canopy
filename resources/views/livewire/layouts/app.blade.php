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
<body class="min-h-screen bg-white dark:bg-zinc-800">
    {{ $slot }}

    @fluxScripts

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.interceptRequest(({ onError }) => {
                onError(({ response, preventDefault }) => {
                    if (response.status === 419) {
                        preventDefault();
                        if (confirm('Oturum süresi doldu. Sayfayı yenilemek ister misiniz?')) {
                            window.location.reload();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
