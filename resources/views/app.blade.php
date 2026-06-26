<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Agenthys Project Management') }}</title>

        {{-- Editorial type: Instrument Serif (display), Instrument Sans (body), JetBrains Mono (code). Bunny Fonts = privacy-first. --}}
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|instrument-serif:400,400i|jetbrains-mono:400,500,600" rel="stylesheet">

        {{-- Theme init before paint (no flash): persisted choice, else OS preference, else dark. --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('ph-theme');
                    if (t !== 'light' && t !== 'dark') {
                        t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                    }
                    document.documentElement.setAttribute('data-theme', t);
                } catch (e) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
