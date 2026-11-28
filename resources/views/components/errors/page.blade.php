@props(['code' => 500, 'exception' => null, 'detail' => null, 'retryAfter' => null])

{{--
  Full standalone HTML document for every error page.
  IMPORTANT: this view must stay "crash-proof": no auth(), no DB, no session, no route() with
  parameters, no @csrf. It is rendered while the app may be failing (500) or in maintenance (503).
--}}
@php
    $page = \App\Support\ErrorPage::resolve((int) $code, $exception, app()->getLocale(), $detail, $retryAfter !== null ? (int) $retryAfter : null);
    $assetsReady = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $page['code'] }} · {{ $page['title'] }} — {{ config('app.name', 'Swift Track') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    {{-- Anti-flash script: applies active theme and dark mode before first paint --}}
    <script>
        (function () {
            var root = document.documentElement;
            var allowed = ['swift', 'ocean', 'aurora', 'ember', 'emerald', 'graphite'];
            var theme = 'swift';
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
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @if ($assetsReady)
        @vite(['resources/css/app.css', 'resources/css/error-scene.css', 'resources/js/app.js'])
    @else
        {{-- Assets not built (fresh deploy / build failure): still readable, no color literals needed. --}}
        <style>
            :root { color-scheme: light dark; }
            body { font-family: system-ui, sans-serif; max-width: 34rem; margin: 18vh auto; padding: 0 1.25rem; line-height: 1.6; }
            svg { display: none; }
        </style>
    @endif
</head>
<body class="es-body" data-tone="{{ $page['tone'] }}" x-data="errorScene(@js($page))">
    <main class="es-shell">
        <section class="es-copy">
            <a href="{{ url('/') }}" class="es-brand" title="{{ config('app.name', 'Swift Track') }}">
                <img src="{{ asset('cmc2-logo.jpeg') }}" alt="{{ config('app.name', 'Swift Track') }}" class="es-brand-img">
                <div>
                    <span class="es-brand-text">{{ config('app.name', 'Swift Track') }}</span>
                    <span class="es-brand-sub">Distribution ERP</span>
                </div>
            </a>

            <div>
                <span class="es-chip">{{ $page['ui']['error'] }} {{ $page['code'] }}</span>
            </div>

            <h1 class="es-title">{{ $page['title'] }}</h1>
            <p class="es-msg">{{ $page['message'] }}</p>

            @if ($page['detail'])
                <span class="es-detail">{{ $page['detail'] }}</span>
            @endif

            <div class="es-actions">
                <a class="es-btn es-btn-primary" href="{{ url('/') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
                    {{ $page['ui']['home'] }}
                </a>
                <button type="button" class="es-btn" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6l-6 6 6 6"/><path d="M4 12h16"/></svg>
                    {{ $page['ui']['back'] }}
                </button>
                @if ($page['button'])
                    <button type="button" class="es-btn" x-on:click="reload()" onclick="if (!window.Alpine) location.reload()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 10-2.3 5.7"/><path d="M20 4v7h-7"/></svg>
                        {{ $page['ui']['retry'] }}
                    </button>
                @endif
            </div>

            @if ($page['auto'])
                <div class="es-retry" aria-live="polite">
                    <p x-text="text">{{ $page['retryText'] }}</p>
                    <div class="es-bar"><b x-bind:style="'--p:' + progress"></b></div>
                </div>
            @endif

            <p class="es-meta">
                <span>{{ $page['time'] }}</span>
                @if ($page['reference'])
                    <span>{{ $page['ui']['reference'] }}: <code>{{ $page['reference'] }}</code></span>
                @endif
            </p>
        </section>

        <div class="es-stage">
            <div class="es-tilt" x-ref="tilt">
                <x-errors.scene :code="$page['code']" :emblem="$page['emblem']" :label="$page['title']" />
            </div>
        </div>
    </main>
</body>
</html>
