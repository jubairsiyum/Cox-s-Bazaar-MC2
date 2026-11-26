<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-canvas">

<head>
    <script>
      (function () {
        var root = document.documentElement, allowed = ['swift', 'ocean', 'aurora', 'ember', 'emerald', 'graphite'], theme = 'swift';
        try {
          var c = localStorage.getItem('color-theme');
          if (c && allowed.indexOf(c) > -1) theme = c;
          var t = localStorage.getItem('theme');
          if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
          if (localStorage.getItem('dir') === 'rtl') root.setAttribute('dir', 'rtl');
          root.setAttribute('data-sidebar', localStorage.getItem('sidebarExpanded') === 'false' ? 'collapsed' : 'expanded');
        } catch (e) {}
        root.setAttribute('data-theme', theme);
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
          root.classList.add('motion');
          if ((navigator.deviceMemory && navigator.deviceMemory <= 2) || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2)) root.classList.add('motion-lite');
          setTimeout(function () { if (!root.classList.contains('motion-ready')) root.classList.add('motion-failsafe'); }, 3500);
        }
      })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title & Meta --}}
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'ERP') }}</title>
    <meta name="description" content="@yield('meta_description', 'Cox\'s Bazaar Mobile Distribution ERP')">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Cloak Style -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Sidebar Store -->
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

@php
    $currentPath = request()->path();
    $isDashboard = request()->routeIs('dashboard') || request()->is('dashboard') || $currentPath === 'dashboard' || $currentPath === '';
@endphp

<body class="min-h-screen bg-canvas font-sans text-ink antialiased p-3 sm:p-4 lg:p-5 selection:bg-brand-500/20 selection:text-brand-600">

    <div class="{{ $isDashboard ? 'enter mv-shell ' : '' }}flex min-h-[calc(100vh-2.5rem)] w-full gap-5 items-start" x-data="{ sidebar: false }">
        <x-dashboard.sidebar />

        <div class="flex-1 min-w-0 flex flex-col gap-5">
            <!-- App topbar -->
            <x-dashboard.topbar />

            <!-- Main Content Area -->
            <main class="flex-1 min-w-0 {{ $isDashboard ? '' : 'enter mv-fade' }}">
                {{ $slot ?? '' }}
                @yield('content')
            </main>
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
                class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-surface p-4 shadow-xl"
                :class="{ 'border-success/30': toast.type === 'success', 'border-danger/30': toast.type === 'error', 'border-warning/30': toast.type === 'warning' }"
            >
                <span
                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full"
                    :class="{ 'bg-success/15 text-success': toast.type === 'success', 'bg-danger/15 text-danger': toast.type === 'error', 'bg-warning/15 text-warning': toast.type === 'warning' }"
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

                <p class="flex-1 text-sm font-medium text-ink" x-text="toast.message"></p>

                <button
                    type="button"
                    @click="$store.toast.remove(toast.id)"
                    class="shrink-0 text-muted transition hover:text-muted"
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
