<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ config('app.name', "Cox's Bazaar MC2") }}</title>
    <meta name="description" content="Sign in to Cox's Bazaar Mobile Distribution ERP">
    <meta name="theme-color" content="#465fff">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        /* ─── Smooth scroll & box-sizing baseline ─── */
        *, *::before, *::after { box-sizing: border-box; }

        /* ─── Left panel: deep-navy with layered gradients + dot-grid ─── */
        .auth-panel {
            background-color: #05091a;
            background-image:
                radial-gradient(ellipse 70% 60% at 15% 10%,  rgba(70,95,255,0.22) 0%, transparent 100%),
                radial-gradient(ellipse 55% 50% at 85% 85%,  rgba(60,90,255,0.15) 0%, transparent 100%),
                radial-gradient(ellipse 40% 40% at 55% 50%,  rgba(90,110,255,0.06) 0%, transparent 100%),
                radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: auto, auto, auto, 30px 30px;
        }

        /* ─── Glow orbs ─── */
        .orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .orb-1 {
            top: -80px; left: -80px;
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(70,95,255,0.3) 0%, transparent 70%);
            filter: blur(55px);
            animation: orb-float 8s ease-in-out infinite;
        }
        .orb-2 {
            bottom: -40px; right: -60px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(60,100,255,0.2) 0%, transparent 70%);
            filter: blur(50px);
            animation: orb-float 10s ease-in-out infinite reverse;
            animation-delay: -4s;
        }
        .orb-3 {
            top: 45%; left: 38%;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(100,120,255,0.1) 0%, transparent 70%);
            filter: blur(70px);
        }
        @keyframes orb-float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(12px, -18px) scale(1.04); }
            66%       { transform: translate(-8px, 10px) scale(0.97); }
        }

        /* ─── Animated dash on SVG lines ─── */
        @keyframes dash-flow {
            to { stroke-dashoffset: -40; }
        }
        .flow-line {
            animation: dash-flow 3s linear infinite;
        }
        .flow-line-slow {
            animation: dash-flow 5s linear infinite;
        }

        /* ─── Ping dot ─── */
        @keyframes ping-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(1.5); }
        }
        .ping { animation: ping-dot 2s ease-in-out infinite; }
        .ping-delay { animation: ping-dot 2s ease-in-out infinite; animation-delay: 1s; }

        /* ─── Form input focus glow ─── */
        .auth-input {
            display: block;
            width: 100%;
            border-radius: 0.75rem;
            border: 1.5px solid;
            background: transparent;
            padding: 0.65rem 1rem 0.65rem 2.75rem;
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #111827;
            transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
        }
        .auth-input::placeholder { color: #9ca3af; }
        .auth-input:focus { outline: none; }

        .dark .auth-input { color: #f9fafb; }
        .dark .auth-input::placeholder { color: #6b7280; }

        /* ─── Mobile brand accent bar ─── */
        .brand-bar {
            height: 3px;
            background: linear-gradient(90deg, #465fff 0%, #6478ff 50%, #7c8fff 100%);
        }

        /* ─── Stat cards on left panel ─── */
        .stat-card {
            background: rgba(255,255,255,0.045);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            padding: 1rem;
            transition: background 0.2s;
        }
    </style>

    {{-- Anti-flash: apply saved theme before paint --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            var html = document.documentElement;
            if (t === 'dark') {
                html.classList.add('dark');
                html.setAttribute('data-color-scheme', 'dark');
            } else {
                html.classList.remove('dark');
                html.setAttribute('data-color-scheme', 'light');
            }
        }());
    </script>

    {{-- Alpine theme store --}}
    <script>
        document.addEventListener('alpine:init', function () {
            Alpine.store('theme', {
                init: function () {
                    var saved = localStorage.getItem('theme');
                    this.theme = (saved === 'dark') ? 'dark' : 'light';
                    this.updateTheme();
                },
                theme: 'light',
                resolvedTheme: 'light',
                set: function (value) {
                    value = (value === 'dark') ? 'dark' : 'light';
                    this.theme = value;
                    localStorage.setItem('theme', value);
                    this.updateTheme();
                    window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }));
                },
                toggle: function () {
                    this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
                },
                updateTheme: function () {
                    var html = document.documentElement;
                    var isDark = this.theme === 'dark';
                    if (isDark) { html.classList.add('dark'); } else { html.classList.remove('dark'); }
                    this.resolvedTheme = isDark ? 'dark' : 'light';
                    html.setAttribute('data-color-scheme', this.resolvedTheme);
                }
            });
        });
    </script>
</head>

{{--
    Body strategy:
    - Mobile:  single scrollable column, no overflow clip
    - Desktop: flex row, full viewport height, no scroll on the row itself
               right panel scrolls independently with overflow-y-auto
--}}
<body class="font-outfit antialiased bg-white dark:bg-gray-950 h-full">

<div class="flex min-h-screen flex-col lg:flex-row">

    {{-- ════════════════════════════════════════════════════════════
         MOBILE HEADER — visible only on small screens
    ════════════════════════════════════════════════════════════ --}}
    <div class="brand-bar lg:hidden"></div>

    <header class="flex items-center justify-between px-5 py-4 lg:hidden">
        <a href="/" class="flex items-center gap-2.5" aria-label="Go to homepage">
            <div class="relative">
                <img
                    src="{{ asset('cmc2-logo.jpeg') }}"
                    alt="CMC2 logo"
                    class="h-9 w-9 rounded-xl object-contain border border-gray-200 dark:border-gray-800 shadow-sm"
                >
            </div>
            <div>
                <span class="block text-[13px] font-bold text-gray-900 dark:text-white leading-none">
                    {{ config('app.name', "Cox's Bazaar MC2") }}
                </span>
                <span class="block text-[10px] text-gray-400 font-medium mt-0.5 tracking-wide">
                    Mobile Distribution ERP
                </span>
            </div>
        </a>

        {{-- Mobile theme toggle --}}
        <button
            type="button"
            @click="$store.theme.toggle()"
            aria-label="Toggle theme"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white/80 text-gray-500 backdrop-blur transition hover:bg-gray-100 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" class="size-[15px]" />
            <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" class="size-[15px]" />
        </button>
    </header>


    {{-- ════════════════════════════════════════════════════════════
         LEFT PANEL — Visual experience, desktop only
    ════════════════════════════════════════════════════════════ --}}
    <aside class="auth-panel relative hidden select-none overflow-hidden lg:flex lg:w-[52%] xl:w-[55%] flex-col">

        {{-- Glow orbs --}}
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>

        {{-- Logo bar --}}
        <div class="relative z-10 flex items-center gap-3 px-10 pt-10 pb-0">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/[0.08] ring-1 ring-white/15 backdrop-blur-sm shadow-inner">
                <img src="{{ asset('cmc2-logo.jpeg') }}" alt="CMC2 logo" class="h-7 w-7 rounded-lg object-contain">
            </div>
            <div>
                <span class="block text-[13px] font-bold text-white leading-none">
                    {{ config('app.name', "Cox's Bazaar MC2") }}
                </span>
                <span class="block text-[11px] text-white/40 font-medium mt-0.5 tracking-wide">
                    Mobile Distribution ERP
                </span>
            </div>
        </div>

        {{-- Main content area --}}
        <div class="relative z-10 flex flex-1 flex-col justify-center px-10 xl:px-14">

            {{-- SVG illustration: abstract secure network --}}
            <div class="mb-8 w-full max-w-sm mx-auto">
                <svg viewBox="0 0 360 260" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full" aria-hidden="true">

                    {{-- Background subtle hex grid --}}
                    <defs>
                        <pattern id="hex-grid" x="0" y="0" width="40" height="34.64" patternUnits="userSpaceOnUse">
                            <path d="M20 0 L40 10 L40 24.64 L20 34.64 L0 24.64 L0 10 Z" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="0.8"/>
                        </pattern>
                        <radialGradient id="center-glow" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stop-color="#465fff" stop-opacity="1"/>
                            <stop offset="100%" stop-color="#465fff" stop-opacity="0.3"/>
                        </radialGradient>
                        <radialGradient id="node-glow" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stop-color="#6478ff" stop-opacity="0.9"/>
                            <stop offset="100%" stop-color="#465fff" stop-opacity="0.5"/>
                        </radialGradient>
                        <filter id="blur-sm">
                            <feGaussianBlur stdDeviation="2"/>
                        </filter>
                    </defs>

                    <rect width="360" height="260" fill="url(#hex-grid)"/>

                    {{-- Glow halo behind center --}}
                    <circle cx="180" cy="130" r="55" fill="rgba(70,95,255,0.1)" filter="url(#blur-sm)"/>

                    {{-- Animated data-flow lines --}}
                    <line x1="180" y1="130" x2="60"  y2="55"  stroke="rgba(70,95,255,0.5)"  stroke-width="1.5" stroke-dasharray="6 5" class="flow-line"/>
                    <line x1="180" y1="130" x2="300" y2="55"  stroke="rgba(70,95,255,0.5)"  stroke-width="1.5" stroke-dasharray="6 5" class="flow-line"/>
                    <line x1="180" y1="130" x2="60"  y2="210" stroke="rgba(70,95,255,0.4)"  stroke-width="1.5" stroke-dasharray="6 5" class="flow-line-slow"/>
                    <line x1="180" y1="130" x2="300" y2="210" stroke="rgba(70,95,255,0.4)"  stroke-width="1.5" stroke-dasharray="6 5" class="flow-line-slow"/>
                    <line x1="180" y1="130" x2="180" y2="22"  stroke="rgba(100,120,255,0.45)" stroke-width="1.5" stroke-dasharray="6 5" class="flow-line"/>
                    <line x1="60"  y1="55"  x2="300" y2="55"  stroke="rgba(70,95,255,0.12)" stroke-width="1"/>
                    <line x1="60"  y1="210" x2="300" y2="210" stroke="rgba(70,95,255,0.12)" stroke-width="1"/>

                    {{-- Secondary connecting lines (subtler) --}}
                    <line x1="60"  y1="55"  x2="60"  y2="210" stroke="rgba(70,95,255,0.1)" stroke-width="1" stroke-dasharray="3 6"/>
                    <line x1="300" y1="55"  x2="300" y2="210" stroke="rgba(70,95,255,0.1)" stroke-width="1" stroke-dasharray="3 6"/>

                    {{-- Data packets (small dots on the lines) --}}
                    <circle cx="120" cy="92"  r="2.5" fill="rgba(255,255,255,0.6)"/>
                    <circle cx="240" cy="92"  r="2.5" fill="rgba(255,255,255,0.6)"/>
                    <circle cx="120" cy="171" r="2.5" fill="rgba(255,255,255,0.5)"/>
                    <circle cx="240" cy="171" r="2.5" fill="rgba(255,255,255,0.5)"/>
                    <circle cx="180" cy="76"  r="2"   fill="rgba(255,255,255,0.55)"/>

                    {{-- Outer satellite nodes --}}
                    <circle cx="60"  cy="55"  r="10" fill="rgba(70,95,255,0.55)" stroke="rgba(100,130,255,0.9)" stroke-width="1.5"/>
                    <circle cx="60"  cy="55"  r="17" fill="none" stroke="rgba(70,95,255,0.2)" stroke-width="1"/>

                    <circle cx="300" cy="55"  r="10" fill="rgba(70,95,255,0.55)" stroke="rgba(100,130,255,0.9)" stroke-width="1.5"/>
                    <circle cx="300" cy="55"  r="17" fill="none" stroke="rgba(70,95,255,0.2)" stroke-width="1"/>

                    <circle cx="60"  cy="210" r="10" fill="rgba(70,95,255,0.45)" stroke="rgba(100,130,255,0.8)" stroke-width="1.5"/>
                    <circle cx="60"  cy="210" r="17" fill="none" stroke="rgba(70,95,255,0.15)" stroke-width="1"/>

                    <circle cx="300" cy="210" r="10" fill="rgba(70,95,255,0.45)" stroke="rgba(100,130,255,0.8)" stroke-width="1.5"/>
                    <circle cx="300" cy="210" r="17" fill="none" stroke="rgba(70,95,255,0.15)" stroke-width="1"/>

                    {{-- Top center small node --}}
                    <circle cx="180" cy="22" r="6" fill="rgba(100,120,255,0.65)" stroke="rgba(140,160,255,0.9)" stroke-width="1.5"/>
                    <circle cx="180" cy="22" r="11" fill="none" stroke="rgba(100,120,255,0.2)" stroke-width="1"/>

                    {{-- Center node: triple rings --}}
                    <circle cx="180" cy="130" r="38" fill="rgba(70,95,255,0.08)" stroke="rgba(70,95,255,0.3)" stroke-width="1"/>
                    <circle cx="180" cy="130" r="27" fill="rgba(70,95,255,0.18)" stroke="rgba(70,95,255,0.5)" stroke-width="1.5"/>
                    <circle cx="180" cy="130" r="17" fill="url(#center-glow)" stroke="rgba(255,255,255,0.3)" stroke-width="1.5"/>

                    {{-- Shield icon in center --}}
                    <path
                        d="M180 121 L173 124.5 L173 131.5 C173 135.5 176 138.5 180 140 C184 138.5 187 135.5 187 131.5 L187 124.5 Z"
                        fill="white"
                        opacity="0.95"
                    />
                    <path d="M177 131 L179 133 L183 128" stroke="rgba(70,95,255,0.9)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

                    {{-- Ping rings on center (animated via CSS above) --}}
                    <circle cx="180" cy="130" r="45" fill="none" stroke="rgba(70,95,255,0.15)" stroke-width="1" class="ping"/>
                    <circle cx="60"  cy="55"  r="22" fill="none" stroke="rgba(70,95,255,0.1)"  stroke-width="1" class="ping-delay"/>
                </svg>
            </div>

            {{-- Headline --}}
            <div class="text-center mb-8">
                <h2 class="text-[1.6rem] xl:text-[1.85rem] font-bold text-white leading-[1.25] tracking-tight">
                    Your business,<br>always in control.
                </h2>
                <p class="mt-3 text-[0.9rem] text-white/50 leading-relaxed max-w-[22rem] mx-auto">
                    Real-time inventory, billing & distribution intelligence for Cox's Bazaar mobile commerce.
                </p>
            </div>

            {{-- Three stat / feature tiles --}}
            <div class="grid grid-cols-3 gap-3 max-w-sm mx-auto w-full">
                @foreach([
                    [
                        'icon'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'value' => 'Live',
                        'label' => 'Inventory',
                    ],
                    [
                        'icon'  => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M5 20h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z',
                        'value' => 'POS',
                        'label' => 'Billing',
                    ],
                    [
                        'icon'  => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                        'value' => 'Smart',
                        'label' => 'Analytics',
                    ],
                ] as $stat)
                <div class="stat-card flex flex-col items-center text-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/20">
                        <svg class="h-4 w-4 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $stat['icon'] }}"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white">{{ $stat['value'] }}</div>
                        <div class="text-[10px] text-white/40 tracking-wide font-medium">{{ $stat['label'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Footer --}}
        <div class="relative z-10 flex items-center justify-between px-10 py-7 text-[11px] text-white/25">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}.</span>
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400/70"></span>
                All systems operational
            </span>
        </div>
    </aside>


    {{-- ════════════════════════════════════════════════════════════
         RIGHT PANEL — Authentication form
    ════════════════════════════════════════════════════════════ --}}
    <main class="relative flex flex-1 flex-col bg-white dark:bg-gray-950 lg:overflow-y-auto">

        {{-- Desktop-only: dark mode toggle + corner decoration --}}
        <div class="absolute top-5 right-5 z-20 hidden lg:block">
            <button
                type="button"
                @click="$store.theme.toggle()"
                aria-label="Toggle dark/light theme"
                class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-xs transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" class="size-[15px]" />
                <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" class="size-[15px]" />
            </button>
        </div>

        {{-- Vertical divider line on desktop (decorative) --}}
        <div class="absolute inset-y-0 left-0 hidden w-px lg:block bg-gradient-to-b from-transparent via-gray-200/60 to-transparent dark:via-gray-800/60"></div>

        {{-- Form container: centered, constrained width --}}
        <div class="flex flex-1 flex-col justify-center px-6 py-10 sm:px-10 lg:px-12 xl:px-20">
            <div class="mx-auto w-full max-w-[22rem]">

                {{-- Desktop brand header (hidden on mobile, mobile header above handles it) --}}
                <div class="mb-8 hidden lg:block">
                    <a href="/" class="inline-flex items-center gap-2.5 mb-7 group" aria-label="Homepage">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs overflow-hidden transition-transform group-hover:scale-105">
                            <img src="{{ asset('cmc2-logo.jpeg') }}" alt="CMC2 logo" class="h-10 w-10 object-contain">
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                {{ config('app.name', "Cox's Bazaar MC2") }}
                            </span>
                            <span class="block text-[11px] text-gray-400 font-medium mt-0.5">
                                Mobile Distribution ERP
                            </span>
                        </div>
                    </a>
                </div>

                {{-- Page heading --}}
                <div class="mb-7">
                    <h1 class="text-[1.6rem] sm:text-[1.75rem] font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                        Welcome back
                    </h1>
                    <p class="mt-1.5 text-[0.9rem] text-gray-500 dark:text-gray-400">
                        Sign in to access your dashboard.
                    </p>
                </div>

                {{-- Status / success alert --}}
                @if(session('status'))
                    <div
                        class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                        role="alert"
                        aria-live="polite"
                    >
                        <svg class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                {{-- Error alert --}}
                @if($errors->any())
                    <div
                        class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300"
                        role="alert"
                        aria-live="assertive"
                    >
                        <svg class="mt-0.5 size-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                {{-- ─── Login form ─── --}}
                <form
                    method="POST"
                    action="{{ route('login') }}"
                    x-data="{ loading: false, showPwd: false }"
                    @submit="loading = true"
                    novalidate
                >
                    @csrf

                    <div class="space-y-4">

                        {{-- Email field --}}
                        <div>
                            <label for="email" class="block text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                Email address
                            </label>
                            <div class="relative">
                                {{-- Leading icon --}}
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5" aria-hidden="true">
                                    <svg class="size-[15px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    autocomplete="email"
                                    autofocus
                                    required
                                    spellcheck="false"
                                    autocapitalize="off"
                                    placeholder="name@company.com"
                                    class="auth-input pl-10 pr-4
                                        {{ $errors->has('email')
                                            ? 'border-red-400 bg-red-50/40 focus:border-red-500 focus:shadow-[0_0_0_3px_rgba(239,68,68,0.12)] dark:border-red-500/50 dark:bg-red-500/5'
                                            : 'border-gray-300 bg-white hover:border-gray-400 focus:border-brand-500 focus:shadow-[0_0_0_3px_rgba(70,95,255,0.12)] dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600 dark:focus:border-brand-500' }}"
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                    @if($errors->has('email')) aria-describedby="email-error" @endif
                                >
                            </div>
                            @error('email')
                                <p id="email-error" class="mt-1.5 flex items-center gap-1.5 text-[12px] text-red-600 dark:text-red-400" role="alert">
                                    <svg class="size-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Password field --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="password" class="block text-[13px] font-semibold text-gray-700 dark:text-gray-300">
                                    Password
                                </label>
                                @if(Route::has('password.request'))
                                    <a
                                        href="{{ route('password.request') }}"
                                        class="text-[12px] font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition-colors underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded"
                                    >
                                        Forgot?
                                    </a>
                                @endif
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5" aria-hidden="true">
                                    <svg class="size-[15px] text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </span>
                                <input
                                    :type="showPwd ? 'text' : 'password'"
                                    id="password"
                                    name="password"
                                    autocomplete="current-password"
                                    required
                                    placeholder="••••••••"
                                    class="auth-input pl-10 pr-11
                                        {{ $errors->has('password')
                                            ? 'border-red-400 bg-red-50/40 focus:border-red-500 focus:shadow-[0_0_0_3px_rgba(239,68,68,0.12)] dark:border-red-500/50 dark:bg-red-500/5'
                                            : 'border-gray-300 bg-white hover:border-gray-400 focus:border-brand-500 focus:shadow-[0_0_0_3px_rgba(70,95,255,0.12)] dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600 dark:focus:border-brand-500' }}"
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    @if($errors->has('password')) aria-describedby="password-error" @endif
                                >
                                <button
                                    type="button"
                                    @click="showPwd = !showPwd"
                                    :aria-label="showPwd ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPwd.toString()"
                                    class="absolute inset-y-0 right-0 flex items-center px-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 rounded-r-xl"
                                >
                                    <svg x-show="!showPwd" class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showPwd" x-cloak class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p id="password-error" class="mt-1.5 flex items-center gap-1.5 text-[12px] text-red-600 dark:text-red-400" role="alert">
                                    <svg class="size-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Remember me --}}
                        <div class="flex items-center pt-0.5">
                            <label for="remember" class="inline-flex items-center gap-2.5 cursor-pointer select-none group">
                                <div class="relative flex items-center">
                                    <input
                                        type="checkbox"
                                        id="remember"
                                        name="remember"
                                        {{ old('remember') ? 'checked' : '' }}
                                        class="peer h-4 w-4 cursor-pointer appearance-none rounded border border-gray-300 bg-white transition checked:border-brand-500 checked:bg-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1 dark:border-gray-700 dark:bg-gray-900 dark:checked:border-brand-500 dark:checked:bg-brand-500"
                                    >
                                    {{-- Custom checkmark --}}
                                    <svg
                                        class="pointer-events-none absolute left-0.5 top-0.5 size-3 text-white opacity-0 transition peer-checked:opacity-100"
                                        viewBox="0 0 12 12"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <span class="text-[13px] text-gray-600 dark:text-gray-400 transition-colors group-hover:text-gray-900 dark:group-hover:text-gray-200">
                                    Keep me signed in
                                </span>
                            </label>
                        </div>

                        {{-- Submit button --}}
                        <div class="pt-1">
                            <button
                                type="submit"
                                :disabled="loading"
                                class="relative w-full inline-flex items-center justify-center gap-2.5 overflow-hidden rounded-xl px-4 py-3 text-[14px] font-semibold text-white shadow-md transition-all duration-200 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30"
                                :class="loading
                                    ? 'bg-brand-500 opacity-70 cursor-not-allowed'
                                    : 'bg-brand-500 hover:bg-brand-600 active:scale-[0.98] active:bg-brand-700'"
                                style="box-shadow: 0 1px 2px rgba(0,0,0,0.08), 0 4px 16px rgba(70,95,255,0.28);"
                            >
                                {{-- Shimmer sweep on idle --}}
                                <span
                                    x-show="!loading"
                                    class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent transition-transform duration-700"
                                    x-ref="shimmer"
                                    aria-hidden="true"
                                ></span>

                                {{-- Spinner --}}
                                <svg
                                    x-show="loading"
                                    x-cloak
                                    class="size-4 animate-spin"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>

                                {{-- Arrow icon on idle --}}
                                <svg x-show="!loading" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>

                                <span x-text="loading ? 'Signing in…' : 'Sign in'">Sign in</span>
                            </button>
                        </div>
                    </div>
                </form>

                {{-- ─── Footer ─── --}}
                <div class="mt-8 space-y-3.5 border-t border-gray-100 dark:border-gray-800/80 pt-5">
                    {{-- Security badge --}}
                    <div class="flex items-center justify-center gap-2 text-[11px] text-gray-400 dark:text-gray-600">
                        <svg class="size-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Secured session &mdash; credentials never stored locally</span>
                    </div>

                    {{-- Admin contact --}}
                    <p class="text-center text-[11px] text-gray-400 dark:text-gray-600">
                        Need access? &thinsp;
                        <span class="font-medium text-gray-500 dark:text-gray-500">Contact your administrator.</span>
                    </p>
                </div>

            </div>
        </div>
    </main>

</div>
</body>
</html>
