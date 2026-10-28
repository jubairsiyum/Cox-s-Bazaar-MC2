<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign In') — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#c0392b">
    <link rel="manifest" href="/manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--color-background);">

    <div style="width:100%;max-width:400px;padding:16px;">
        {{-- Logo --}}
        <div style="text-align:center;margin-bottom:28px;">
            <div style="font-size:1.4rem;font-weight:800;color:var(--color-primary);">📱 {{ config('app.name') }}</div>
            <div style="font-size:.8rem;color:var(--color-text-muted);margin-top:4px;">Distribution ERP · Cox's Bazaar</div>
        </div>

        @yield('content')
    </div>

    <div style="margin-top:24px;font-size:.72rem;color:var(--color-text-muted);">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </div>

    @stack('scripts')
</body>
</html>
