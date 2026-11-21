@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name', "Cox's Bazaar MC2") }}</title>

    {{-- Same anti-flash logic as the main layout: saved dark mode + color theme before first paint. --}}
    <script>
        (function () {
            var root = document.documentElement, allowed = ['swift', 'ocean', 'aurora', 'ember', 'emerald', 'graphite'], theme = 'swift';
            try {
                var c = localStorage.getItem('color-theme');
                if (c && allowed.indexOf(c) > -1) theme = c;
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
                if (localStorage.getItem('dir') === 'rtl') root.setAttribute('dir', 'rtl');
            } catch (e) {}
            root.setAttribute('data-theme', theme);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="au-body" x-data="authStage">
    <x-auth.decor />
    <main class="au-stage">
        {{ $slot }}
    </main>
</body>
</html>
