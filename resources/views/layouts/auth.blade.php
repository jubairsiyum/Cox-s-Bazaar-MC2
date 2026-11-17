<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-canvas">
<head>
    <script>
      (function () {
        var root = document.documentElement, allowed = ['swift'], theme = 'swift';
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign In') — {{ config('app.name', 'Cox\'s Bazaar ERP') }}</title>
    <meta name="description" content="Cox's Bazaar Mobile Distribution ERP">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Cloak Style -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="min-h-full font-outfit antialiased bg-canvas text-ink flex flex-col justify-center py-12 sm:px-6 lg:px-8">

    {{-- Dark/Light Mode Switcher --}}
    <div class="fixed top-5 right-5 z-20">
        <button
            type="button"
            @click="$store.theme.toggle()"
            aria-label="Toggle dark/light theme"
            class="flex h-10 w-10 items-center justify-center rounded-xl border border-line bg-surface text-muted shadow-card transition hover:bg-soft hover:text-ink"
        >
            <x-svg.sun x-show="$store.theme.dark" class="size-5" />
            <x-svg.moon x-show="!$store.theme.dark" class="size-5" />
        </button>
    </div>

    {{-- Header / Brand --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="/" class="inline-flex items-center gap-3 transition-opacity hover:opacity-90">
            <img class="h-12 w-12 rounded-xl object-contain bg-surface p-1 border border-line shadow-sm" src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }} logo">
            <div class="text-left">
                <span class="block text-lg font-bold text-ink leading-tight">
                    {{ config('app.name', 'Cox\'s Bazaar ERP') }}
                </span>
                <span class="block text-xs text-muted font-medium">
                    Mobile Distribution Management
                </span>
            </div>
        </a>
    </div>

    {{-- Main Auth Card --}}
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="rounded-2xl border border-line bg-surface p-7 sm:p-9 shadow-card">
            @yield('content')
        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-muted">
            &copy; {{ date('Y') }} {{ config('app.name', 'Cox\'s Bazaar ERP') }}. All rights reserved.
        </p>
    </div>

    @stack('scripts')
</body>
</html>
