<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'Swift Track ERP' }}</title>

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
      if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.classList.add('motion');
        if ((navigator.deviceMemory && navigator.deviceMemory <= 2) || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2)) root.classList.add('motion-lite');
        setTimeout(function () { if (!root.classList.contains('motion-ready')) root.classList.add('motion-failsafe'); }, 3500);
      }
    })();
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  

  @stack('head')
</head>
@php
    $currentPath = request()->path();
    $isDashboard = request()->routeIs('dashboard') || request()->is('dashboard') || $currentPath === 'dashboard' || $currentPath === '';
@endphp

<body class="min-h-screen bg-canvas font-sans text-ink antialiased p-3 sm:p-4 lg:p-5 selection:bg-brand-500/20 selection:text-brand-600">
  <div class="{{ $isDashboard ? 'enter mv-shell ' : '' }}flex min-h-[calc(100vh-2.5rem)] w-full gap-5"
       x-data="{ sidebar: false }">

    <x-dashboard.sidebar />

    <div class="flex-1 min-w-0 flex flex-col gap-5">
      <x-dashboard.topbar />
      <main class="flex-1 min-w-0 {{ $isDashboard ? '' : 'enter mv-fade' }}">
        {{ $slot }}
      </main>
    </div>
  </div>

  @stack('scripts')
</body>
</html>
