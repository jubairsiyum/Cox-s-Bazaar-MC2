<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-gray-50 dark:bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign In') — {{ config('app.name', 'Cox\'s Bazaar ERP') }}</title>
    <meta name="description" content="Cox's Bazaar Mobile Distribution ERP">
    <meta name="theme-color" content="#465fff">
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

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    this.theme = savedTheme === 'dark' ? 'dark' : 'light';
                    this.updateTheme();
                },
                theme: 'light',
                resolvedTheme: 'light',
                set(value) {
                    value = value === 'dark' ? 'dark' : 'light';
                    this.theme = value;
                    localStorage.setItem('theme', value);
                    this.updateTheme();
                    window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }));
                },
                toggle() {
                    this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
                },
                updateTheme() {
                    const html = document.documentElement;
                    const isDark = this.theme === 'dark';
                    if (isDark) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                    this.resolvedTheme = isDark ? 'dark' : 'light';
                    html.setAttribute('data-color-scheme', this.resolvedTheme);
                }
            });
        });
    </script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-color-scheme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-color-scheme', 'light');
            }
        })();
    </script>
</head>
<body class="min-h-full font-outfit antialiased bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">

    {{-- Dark/Light Mode Switcher --}}
    <div class="fixed top-5 right-5 z-20">
        <button
            type="button"
            @click="$store.theme.toggle()"
            aria-label="Toggle dark/light theme"
            class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" class="size-5" />
            <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" class="size-5" />
        </button>
    </div>

    {{-- Header / Brand --}}
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="/" class="inline-flex items-center gap-3 transition-opacity hover:opacity-90">
            <img class="h-12 w-12 rounded-xl object-contain bg-white p-1 border border-gray-200 dark:border-gray-800 shadow-sm" src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name') }} logo">
            <div class="text-left">
                <span class="block text-lg font-bold text-gray-900 dark:text-white leading-tight">
                    {{ config('app.name', 'Cox\'s Bazaar ERP') }}
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400 font-medium">
                    Mobile Distribution Management
                </span>
            </div>
        </a>
    </div>

    {{-- Main Auth Card --}}
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="rounded-2xl border border-gray-200 bg-white p-7 sm:p-9 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            @yield('content')
        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
            &copy; {{ date('Y') }} {{ config('app.name', 'Cox\'s Bazaar ERP') }}. All rights reserved.
        </p>
    </div>

    @stack('scripts')
</body>
</html>
