<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full bg-canvas">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ config('app.name', "Cox's Bazaar MC2") }}</title>
    <meta name="description" content="Sign in to Cox's Bazaar Mobile Distribution ERP">

    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    {{-- Anti-flash script: apply active theme, dark mode, and text direction before first paint --}}
    <script>
        (function() {
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        .auth-canvas {
            background-color: var(--canvas);
            background-image:
                radial-gradient(ellipse 65% 55% at 15% 15%, color-mix(in srgb, var(--brand-500) 12%, transparent) 0%, transparent 100%),
                radial-gradient(ellipse 55% 45% at 85% 85%, color-mix(in srgb, var(--soft) 50%, transparent) 0%, transparent 100%);
        }

        .auth-orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(65px);
        }

        .auth-orb-1 {
            top: -12%;
            left: -10%;
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, color-mix(in srgb, var(--brand-500) 22%, transparent) 0%, transparent 70%);
            animation: auth-float 10s ease-in-out infinite alternate;
        }

        .auth-orb-2 {
            bottom: -15%;
            right: -10%;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, color-mix(in srgb, var(--brand-400) 18%, transparent) 0%, transparent 70%);
            animation: auth-float 12s ease-in-out infinite alternate-reverse;
        }

        @keyframes auth-float {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(16px, -18px) scale(1.05);
            }
        }
    </style>
</head>

<body class="font-sans antialiased bg-canvas text-ink min-h-screen flex flex-col justify-center p-4 sm:p-6 lg:p-8 relative selection:bg-brand-500 selection:text-on-brand auth-canvas">

    {{-- ════════════════════════════════════════════════════════════
         TOP CONTROLS: Live Theme Switcher & Dark Mode Toggle
    ════════════════════════════════════════════════════════════ --}}
    <aside aria-label="Theme settings" class="fixed top-4 right-4 sm:top-6 sm:right-6 z-40 flex items-center gap-2">
        {{-- Palette Selector --}}
        <div x-data="{ open: false }" class="relative">
            <button
                type="button"
                @click="open = !open"
                @click.outside="open = false"
                class="flex items-center gap-2 h-9 px-3 rounded-xl border border-line bg-surface/90 backdrop-blur-md text-ink text-xs font-semibold shadow-xs transition hover:bg-soft hover:border-line focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                aria-label="Select theme palette">
                <span class="size-2.5 rounded-full bg-brand-500 ring-2 ring-brand-500/20"></span>
                <span class="capitalize" x-text="$store.theme.name">Swift</span>
                <x-icon name="chevron" class="size-3 text-muted transition-transform" ::class="open && 'rotate-180'" />
            </button>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 mt-1.5 w-40 rounded-xl border border-line bg-surface p-1.5 shadow-card z-50">
                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-muted">Theme Palette</div>
                <template x-for="t in [
                    { id: 'swift', name: 'Swift Blue', color: '#465fff' },
                    { id: 'ocean', name: 'Ocean Cyan', color: '#0284c7' },
                    { id: 'aurora', name: 'Aurora Indigo', color: '#6366f1' },
                    { id: 'ember', name: 'Ember Orange', color: '#c2410c' },
                    { id: 'emerald', name: 'Emerald Green', color: '#059669' },
                    { id: 'graphite', name: 'Graphite Slate', color: '#475569' }
                ]" :key="t.id">
                    <button
                        type="button"
                        @click="$store.theme.set(t.id); open = false"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink transition hover:bg-soft"
                        :class="$store.theme.name === t.id && 'bg-soft font-semibold text-brand-600 dark:text-brand-400'">
                        <span class="size-2.5 rounded-full shrink-0" :style="'background-color:' + t.color"></span>
                        <span x-text="t.name"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Dark/Light Toggle --}}
        <button
            type="button"
            @click="$store.theme.toggle()"
            class="flex size-9 items-center justify-center rounded-xl border border-line bg-surface/90 backdrop-blur-md text-muted shadow-xs transition hover:bg-soft hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
            aria-label="Toggle dark/light mode">
            <span x-show="!$store.theme.dark"><x-icon name="moon" class="size-4" /></span>
            <span x-show="$store.theme.dark" x-cloak><x-icon name="sun" class="size-4 text-warning" /></span>
        </button>
    </aside>

    {{-- ════════════════════════════════════════════════════════════
         MAIN LOGIN CARD SHELL (Two-column on desktop)
    ════════════════════════════════════════════════════════════ --}}
    <main class="w-full max-w-5xl xl:max-w-6xl mx-auto rounded-3xl lg:rounded-[36px] border border-line bg-shell shadow-card overflow-hidden grid lg:grid-cols-12 relative z-10 transition-colors">

        {{-- ────────────────────────────────────────────────────────
             LEFT COLUMN: Visual Branding & Live ERP Intelligence
        ──────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-6 xl:col-span-7 relative p-8 sm:p-10 xl:p-14 flex flex-col justify-between overflow-hidden border-b lg:border-b-0 lg:border-r border-line/70 bg-gradient-to-br from-soft/60 via-soft/20 to-transparent">
            {{-- Glowing ambient orbs --}}
            <div class="auth-orb auth-orb-1"></div>
            <div class="auth-orb auth-orb-2"></div>

            {{-- Top Branding --}}
            <div class="relative z-10">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-3.5 group text-decoration-none">
                    <img
                        src="{{ asset('cmc2-logo.jpeg') }}"
                        alt="{{ config('app.name', 'Cox\'s Bazaar MC2') }} logo"
                        class="h-11 w-11 rounded-2xl object-contain bg-surface p-1 border border-line shadow-xs transition-transform duration-200 group-hover:scale-105">
                    <div>
                        <span class="block text-base font-extrabold tracking-tight text-ink leading-tight">
                            {{ config('app.name', "Cox's Bazaar MC2") }}
                        </span>
                        <span class="block text-xs font-semibold text-muted mt-0.5">
                            Mobile Distribution ERP
                        </span>
                    </div>
                </a>
            </div>

            {{-- Middle Visual Presentation & KPI Showcase --}}
            <div class="relative z-10 my-8 lg:my-10">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-soft text-brand-600 dark:text-brand-400 text-xs font-bold tracking-wide mb-4 border border-line">
                    <span class="size-1.5 rounded-full bg-brand-500 animate-pulse"></span>
                    Distribution Command Center
                </span>

                <h1 class="text-2xl sm:text-3xl xl:text-4xl font-extrabold tracking-tight text-ink leading-[1.2]">
                    Intelligent commerce, <br class="hidden sm:inline">
                    <span class="text-brand-500">built for high-volume scale.</span>
                </h1>

                <p class="mt-3.5 text-sm sm:text-base text-muted leading-relaxed max-w-lg">
                    Real-time mobile stock synchronization, automated distributor invoicing, and smart multi-brand market share analytics.
                </p>

                {{-- Live Mockup Widget Preview --}}
                <div class="mt-6 rounded-2xl border border-line bg-surface/85 backdrop-blur-md p-4 sm:p-5 shadow-xs max-w-md">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-line/60">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-success"></span>
                            <span class="text-xs font-bold text-ink">Central Terminal Status</span>
                        </div>
                        <span class="text-[11px] font-semibold text-muted tabular-nums">Live Feed</span>
                    </div>

                    {{-- Mini Brand Leaderboard Bars --}}
                    <div class="space-y-2.5">
                        <div>
                            <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                <span class="text-ink">Samsung Electronics</span>
                                <span class="tabular-nums text-brand-500 font-bold">38% share</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                <div class="h-full rounded-full bg-brand-500" style="width: 38%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                <span class="text-ink">Xiaomi &amp; Redmi</span>
                                <span class="tabular-nums text-warning font-bold">29% share</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                <div class="h-full rounded-full bg-warning" style="width: 29%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                <span class="text-ink">Vivo Telecom</span>
                                <span class="tabular-nums text-success font-bold">18% share</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                <div class="h-full rounded-full bg-success" style="width: 18%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3 Feature Badges --}}
                <div class="mt-6 grid grid-cols-3 gap-2.5 max-w-md">
                    <div class="rounded-xl border border-line bg-surface/60 p-2.5 text-center">
                        <x-icon name="box" class="size-4 mx-auto text-brand-500 mb-1" />
                        <div class="text-xs font-bold text-ink">Live</div>
                        <div class="text-[10px] text-muted">Stock Sync</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface/60 p-2.5 text-center">
                        <x-icon name="cart" class="size-4 mx-auto text-warning mb-1" />
                        <div class="text-xs font-bold text-ink">Fast POS</div>
                        <div class="text-[10px] text-muted">Billing Terminal</div>
                    </div>
                    <div class="rounded-xl border border-line bg-surface/60 p-2.5 text-center">
                        <x-icon name="trend-up" class="size-4 mx-auto text-success mb-1" />
                        <div class="text-xs font-bold text-ink">Ranked</div>
                        <div class="text-[10px] text-muted">Analytics</div>
                    </div>
                </div>
            </div>

            {{-- Bottom Status & Heartbeat --}}
            <div class="relative z-10 flex items-center justify-between text-xs text-muted pt-4 border-t border-line/60">
                <span class="flex items-center gap-2">
                    <span class="relative flex size-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                        <span class="relative inline-flex rounded-full size-2 bg-success"></span>
                    </span>
                    <span class="font-medium text-ink">All terminals operational</span>
                </span>
                <span>&copy; {{ date('Y') }} {{ config('app.name', "Cox's Bazaar MC2") }}</span>
            </div>
        </div>

        {{-- ────────────────────────────────────────────────────────
             RIGHT COLUMN: Clean, High-Contrast Authentication Form
        ──────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-6 xl:col-span-5 p-8 sm:p-10 xl:p-12 flex flex-col justify-center bg-surface relative">
            <div class="w-full max-w-sm mx-auto">

                {{-- Header Title --}}
                <div class="mb-7">
                    <h2 class="text-2xl font-extrabold tracking-tight text-ink">
                        Sign in to account
                    </h2>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted">
                        Enter your credentials to access your ERP dashboard.
                    </p>
                </div>

                {{-- Status Alert (e.g. password reset confirmation) --}}
                @if(session('status'))
                <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-success/30 bg-success/10 p-3.5 text-xs font-medium text-success" role="alert">
                    <x-icon name="check" class="size-4 shrink-0 mt-0.5" />
                    <span>{{ session('status') }}</span>
                </div>
                @endif

                {{-- General Validation Alert --}}
                @if($errors->any())
                <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-danger/30 bg-danger/10 p-3.5 text-xs font-medium text-danger" role="alert">
                    <x-icon name="alert-triangle" class="size-4 shrink-0 mt-0.5" />
                    <span>{{ $errors->first() }}</span>
                </div>
                @endif

                {{-- Login Form --}}
                <form
                    method="POST"
                    action="{{ route('login') }}"
                    x-data="{ loading: false, showPwd: false }"
                    @submit="loading = true"
                    class="space-y-4"
                    novalidate>
                    @csrf

                    {{-- Email Field --}}
                    <div>
                        <label for="email" class="block text-xs font-bold text-ink mb-1.5">
                            Email address
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
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
                                placeholder="operator@company.com"
                                class="h-11 w-full rounded-xl border bg-surface pl-10 pr-4 text-sm text-ink placeholder:text-muted transition focus:outline-none focus:ring-2 focus:ring-brand-500/25 {{ $errors->has('email') ? 'border-danger focus:border-danger' : 'border-line hover:border-line/80 focus:border-brand-500' }}"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                        </div>
                        @error('email')
                        <p class="mt-1.5 text-xs text-danger font-medium flex items-center gap-1.5">
                            <x-icon name="alert-triangle" class="size-3.5 shrink-0" />
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    {{-- Password Field --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold text-ink">
                                Password
                            </label>
                            @if(Route::has('password.request'))
                            <a
                                href="{{ route('password.request') }}"
                                class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded">
                                Forgot password?
                            </a>
                            @endif
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input
                                :type="showPwd ? 'text' : 'password'"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                                placeholder="••••••••"
                                class="h-11 w-full rounded-xl border bg-surface pl-10 pr-11 text-sm text-ink placeholder:text-muted transition focus:outline-none focus:ring-2 focus:ring-brand-500/25 {{ $errors->has('password') ? 'border-danger focus:border-danger' : 'border-line hover:border-line/80 focus:border-brand-500' }}"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            <button
                                type="button"
                                @click="showPwd = !showPwd"
                                :aria-label="showPwd ? 'Hide password' : 'Show password'"
                                class="absolute inset-y-0 right-0 flex items-center px-3.5 text-muted hover:text-ink transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-r-xl">
                                <svg x-show="!showPwd" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPwd" x-cloak class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                        <p class="mt-1.5 text-xs text-danger font-medium flex items-center gap-1.5">
                            <x-icon name="alert-triangle" class="size-3.5 shrink-0" />
                            {{ $message }}
                        </p>
                        @enderror
                    </div>

                    {{-- Remember Me & Security --}}
                    <div class="flex items-center justify-between pt-1">
                        <label for="remember" class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                {{ old('remember') ? 'checked' : '' }}
                                class="size-4 rounded border-line text-brand-500 focus:ring-brand-500/30 bg-surface cursor-pointer">
                            <span class="text-xs text-muted font-medium hover:text-ink transition">Keep me signed in</span>
                        </label>
                    </div>

                    {{-- Submit Button --}}
                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="loading"
                            class="h-11 w-full rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand font-semibold text-sm shadow-card active:scale-[0.98] transition-all flex items-center justify-center gap-2 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 disabled:opacity-75 disabled:cursor-not-allowed">
                            {{-- Loading Spinner --}}
                            <svg x-show="loading" x-cloak class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>

                            <span x-text="loading ? 'Authenticating…' : 'Sign in to Terminal'">Sign in to Terminal</span>
                            <svg x-show="!loading" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </form>

                {{-- ────────────────────────────────────────────────────
                     Local Environment Quick Demo Logins (1-Click Fill)
                ──────────────────────────────────────────────────── --}}
                @if(app()->environment('local'))
                <div class="mt-6 pt-5 border-t border-line/70">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Demo Credentials</span>
                        <span class="text-[10px] font-medium text-brand-600 dark:text-brand-400">1-Click Fill</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            type="button"
                            onclick="document.getElementById('email').value='admin@cberp.local'; document.getElementById('password').value='Admin@1234';"
                            class="col-span-2 flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-brand-500/30 bg-brand-500/10 hover:bg-brand-500/15 text-left text-xs font-bold text-brand-600 dark:text-brand-400 transition group">
                            <span>👑 Super Admin (Full Network Access)</span>
                            <span class="text-[10px] text-brand-500 font-mono">Fill &rarr;</span>
                        </button>
                        <button
                            type="button"
                            onclick="document.getElementById('email').value='manager@cberp.local'; document.getElementById('password').value='Password@123';"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-line bg-surface hover:bg-soft text-left text-xs font-semibold text-ink transition group">
                            <span>Manager (Outlet)</span>
                            <span class="text-[10px] text-muted group-hover:text-brand-500 font-mono">Fill &rarr;</span>
                        </button>
                        <button
                            type="button"
                            onclick="document.getElementById('email').value='sales@cberp.local'; document.getElementById('password').value='Password@123';"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-line bg-surface hover:bg-soft text-left text-xs font-semibold text-ink transition group">
                            <span>Sales Staff (Outlet)</span>
                            <span class="text-[10px] text-muted group-hover:text-brand-500 font-mono">Fill &rarr;</span>
                        </button>
                        <button
                            type="button"
                            onclick="document.getElementById('email').value='accountant@cberp.local'; document.getElementById('password').value='Password@123';"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-line bg-surface hover:bg-soft text-left text-xs font-semibold text-ink transition group">
                            <span>Accountant</span>
                            <span class="text-[10px] text-muted group-hover:text-brand-500 font-mono">Fill &rarr;</span>
                        </button>
                        <button
                            type="button"
                            onclick="document.getElementById('email').value='storekeeper@cberp.local'; document.getElementById('password').value='Password@123';"
                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-line bg-surface hover:bg-soft text-left text-xs font-semibold text-ink transition group">
                            <span>Storekeeper</span>
                            <span class="text-[10px] text-muted group-hover:text-brand-500 font-mono">Fill &rarr;</span>
                        </button>
                    </div>
                </div>
                @endif

                {{-- Footnote & Security Notice --}}
                <div class="mt-8 pt-5 border-t border-line/60 flex items-center justify-center gap-1.5 text-xs text-muted text-center">
                    <svg class="size-3.5 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>Secured terminal &bull; 256-bit encrypted session</span>
                </div>

            </div>
        </div>
    </main>

</body>

</html>