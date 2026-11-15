<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-gray-50 dark:bg-gray-900">

<head>
    <script>
        (function () {
            if (localStorage.getItem('dir') === 'rtl') document.documentElement.setAttribute('dir', 'rtl');
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title & Meta --}}
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'ERP') }}</title>
    <meta name="description" content="@yield('meta_description', 'Cox\'s Bazaar Mobile Distribution ERP')">
    <meta name="theme-color" content="#004EFC">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    @include('layouts.head-meta')

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Cloak Style -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Theme & Sidebar Stores -->
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
                    html.dataset['theme'] = this.resolvedTheme;
                    html.style.colorScheme = this.resolvedTheme;
                    if (document.body) {
                        document.body.dataset['theme'] = this.resolvedTheme;
                        document.body.style.colorScheme = this.resolvedTheme;
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: false,
                isMobileOpen: false,
                isHovered: false,

                init() {
                    const savedState = localStorage.getItem('sidebarExpanded');
                    if (window.innerWidth >= 1280) {
                        this.isExpanded = savedState === null ? true : savedState === 'true';
                    } else {
                        this.isExpanded = false;
                    }
                    this.isMobileOpen = false;

                    window.addEventListener('resize', () => {
                        this.handleResize();
                    });
                },

                handleResize() {
                    if (window.innerWidth < 1280) {
                        if (this.isMobileOpen) {
                             this.isMobileOpen = false;
                        }
                    } else {
                        this.isMobileOpen = false;
                        const savedState = localStorage.getItem('sidebarExpanded');
                        this.isExpanded = savedState === null ? true : savedState === 'true';
                    }
                },

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    this.isMobileOpen = false;
                    if (window.innerWidth >= 1280) {
                        localStorage.setItem('sidebarExpanded', this.isExpanded);
                    }
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Prevent flash of unstyled theme -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const isDark = savedTheme === 'dark';
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-color-scheme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-color-scheme', 'light');
            }
        })();
    </script>

    @stack('head')
</head>

<body class="bg-gray-50 dark:bg-gray-900 font-outfit antialiased">

    <div class="min-h-screen xl:flex sidebar-expanded" x-data :class="{ 'sidebar-expanded': $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen }">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="flex-1 min-w-0 ml-0 ltr:xl:ml-[90px] rtl:xl:ml-0 rtl:xl:mr-[90px] [.sidebar-expanded_&]:ltr:xl:ml-[290px] [.sidebar-expanded_&]:rtl:xl:ml-0 [.sidebar-expanded_&]:rtl:xl:mr-[290px] transition-all duration-300 ease-in-out">
            <!-- App header -->
            @include('layouts.app-header')

            <!-- Main Content Area -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>
    </div>

    {{-- Offline Banner --}}
    <div class="offline-banner" id="offline-banner">
        ⚠ You are offline. Transaction actions require an internet connection.
    </div>

    {{-- Seed the toast store with server flash messages --}}
    @php
        $flashToasts = collect(['success', 'error', 'warning'])
            ->filter(fn (string $type) => session()->has($type))
            ->map(fn (string $type) => [
                'message' => session($type),
                'type' => $type,
                'duration' => $type === 'success' ? 4000 : ($type === 'error' ? 6000 : 5000),
            ])
            ->values();
    @endphp
    <script>
        window.flashToasts = @json($flashToasts);
    </script>

    {{-- Toast Notifications --}}
    <div
        x-data="toastHost"
        class="fixed top-4 right-4 z-[99999] flex w-[360px] max-w-[calc(100vw-2rem)] flex-col gap-3"
        aria-live="polite"
        aria-label="Notifications"
    >
        <template x-for="toast in $store.toast.items" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-6"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 translate-x-6"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-xl dark:bg-gray-800"
                :class="{
                    'border-success-200 dark:border-success-500/30': toast.type === 'success',
                    'border-error-200 dark:border-error-500/30': toast.type === 'error',
                    'border-warning-200 dark:border-warning-500/30': toast.type === 'warning',
                }"
            >
                <span
                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400': toast.type === 'success',
                        'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400': toast.type === 'error',
                        'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400': toast.type === 'warning',
                    }"
                >
                    <svg x-show="toast.type === 'success'" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="toast.type === 'error'" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <svg x-show="toast.type === 'warning'" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                </span>

                <p class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-200" x-text="toast.message"></p>

                <button
                    type="button"
                    @click="$store.toast.remove(toast.id)"
                    class="shrink-0 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                    aria-label="Dismiss"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    @stack('scripts')
</body>
</html>
