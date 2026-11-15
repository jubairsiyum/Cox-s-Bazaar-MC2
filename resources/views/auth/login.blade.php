<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ config('app.name', "Cox's Bazaar ERP") }}</title>
    <meta name="description" content="Cox's Bazaar Mobile Distribution ERP — secure sign in">
    <meta name="theme-color" content="#465fff">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }

        /* Left panel dot-grid background */
        .login-panel-bg {
            background-color: #060d1f;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(70, 95, 255, 0.18) 0%, transparent 50%),
                radial-gradient(circle at 80% 75%, rgba(70, 95, 255, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(99, 120, 255, 0.07) 0%, transparent 70%),
                radial-gradient(circle, rgba(255,255,255,0.055) 1px, transparent 1px);
            background-size: auto, auto, auto, 28px 28px;
        }

        /* Soft glow rings */
        .glow-ring {
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
        }

        /* Animated subtle pulse on the glow */
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50%       { opacity: 0.75; transform: scale(1.06); }
        }
        .glow-animate {
            animation: pulse-glow 6s ease-in-out infinite;
        }
    </style>

    {{-- Anti-flash theme script --}}
    <script>
        (function() {
            const t = localStorage.getItem('theme');
            const html = document.documentElement;
            if (t === 'dark') {
                html.classList.add('dark');
                html.setAttribute('data-color-scheme', 'dark');
            } else {
                html.classList.remove('dark');
                html.setAttribute('data-color-scheme', 'light');
            }
        })();
    </script>

    {{-- Alpine theme store --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const saved = localStorage.getItem('theme');
                    this.theme = saved === 'dark' ? 'dark' : 'light';
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
                    if (isDark) { html.classList.add('dark'); } else { html.classList.remove('dark'); }
                    this.resolvedTheme = isDark ? 'dark' : 'light';
                    html.setAttribute('data-color-scheme', this.resolvedTheme);
                }
            });
        });
    </script>
</head>
<body class="h-full font-outfit antialiased">

<div class="flex h-full min-h-screen">

    {{-- ═══════════════════════════════════════════════════════════
         LEFT PANEL — Visual Experience (desktop only)
    ═══════════════════════════════════════════════════════════ --}}
    <div class="login-panel-bg relative hidden lg:flex lg:w-[55%] xl:w-1/2 flex-col overflow-hidden select-none">

        {{-- Glow orbs --}}
        <div class="glow-ring glow-animate absolute -top-24 -left-24 h-96 w-96 bg-brand-500/25"></div>
        <div class="glow-ring glow-animate absolute bottom-10 right-0 h-72 w-72 bg-brand-400/15" style="animation-delay:3s"></div>
        <div class="glow-ring absolute top-1/2 left-1/3 h-64 w-64 bg-indigo-500/10" style="filter:blur(80px)"></div>

        {{-- Top bar with logo --}}
        <div class="relative z-10 flex items-center gap-3 p-10">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
                <img src="{{ asset('cmc2-logo.jpeg') }}" alt="CMC2 Logo" class="h-8 w-8 rounded-lg object-contain">
            </div>
            <div>
                <span class="block text-sm font-bold text-white leading-tight">{{ config('app.name', "Cox's Bazaar MC2") }}</span>
                <span class="block text-xs text-white/50 font-medium tracking-wide">Mobile Distribution ERP</span>
            </div>
        </div>

        {{-- Abstract SVG Illustration --}}
        <div class="relative z-10 flex flex-1 items-center justify-center px-10">
            <div class="w-full max-w-md">

                {{-- Central network/node illustration --}}
                <svg viewBox="0 0 400 320" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full opacity-80" aria-hidden="true">
                    <!-- Grid lines -->
                    <line x1="0" y1="80" x2="400" y2="80" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="0" y1="160" x2="400" y2="160" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="0" y1="240" x2="400" y2="240" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="80" y1="0" x2="80" y2="320" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="160" y1="0" x2="160" y2="320" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="240" y1="0" x2="240" y2="320" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>
                    <line x1="320" y1="0" x2="320" y2="320" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>

                    <!-- Connection lines -->
                    <line x1="200" y1="160" x2="80" y2="80" stroke="rgba(70,95,255,0.4)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line x1="200" y1="160" x2="320" y2="80" stroke="rgba(70,95,255,0.4)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line x1="200" y1="160" x2="80" y2="250" stroke="rgba(70,95,255,0.3)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line x1="200" y1="160" x2="320" y2="250" stroke="rgba(70,95,255,0.3)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line x1="200" y1="160" x2="200" y2="40" stroke="rgba(99,120,255,0.35)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line x1="80" y1="80" x2="320" y2="80" stroke="rgba(70,95,255,0.15)" stroke-width="1"/>
                    <line x1="80" y1="250" x2="320" y2="250" stroke="rgba(70,95,255,0.15)" stroke-width="1"/>

                    <!-- Outer nodes -->
                    <circle cx="80" cy="80" r="8" fill="rgba(70,95,255,0.6)" stroke="rgba(70,95,255,0.9)" stroke-width="1.5"/>
                    <circle cx="80" cy="80" r="14" fill="none" stroke="rgba(70,95,255,0.25)" stroke-width="1"/>

                    <circle cx="320" cy="80" r="8" fill="rgba(70,95,255,0.6)" stroke="rgba(70,95,255,0.9)" stroke-width="1.5"/>
                    <circle cx="320" cy="80" r="14" fill="none" stroke="rgba(70,95,255,0.25)" stroke-width="1"/>

                    <circle cx="200" cy="40" r="6" fill="rgba(99,120,255,0.5)" stroke="rgba(99,120,255,0.8)" stroke-width="1.5"/>

                    <circle cx="80" cy="250" r="8" fill="rgba(70,95,255,0.5)" stroke="rgba(70,95,255,0.8)" stroke-width="1.5"/>
                    <circle cx="80" cy="250" r="14" fill="none" stroke="rgba(70,95,255,0.2)" stroke-width="1"/>

                    <circle cx="320" cy="250" r="8" fill="rgba(70,95,255,0.5)" stroke="rgba(70,95,255,0.8)" stroke-width="1.5"/>
                    <circle cx="320" cy="250" r="14" fill="none" stroke="rgba(70,95,255,0.2)" stroke-width="1"/>

                    <!-- Center node -->
                    <circle cx="200" cy="160" r="28" fill="rgba(70,95,255,0.15)" stroke="rgba(70,95,255,0.5)" stroke-width="1.5"/>
                    <circle cx="200" cy="160" r="20" fill="rgba(70,95,255,0.25)" stroke="rgba(70,95,255,0.7)" stroke-width="1.5"/>
                    <circle cx="200" cy="160" r="10" fill="rgba(70,95,255,0.9)" stroke="rgba(255,255,255,0.4)" stroke-width="1.5"/>

                    <!-- Shield icon in center node -->
                    <path d="M200 153 L195 156 L195 162 C195 164.5 197 166.5 200 168 C203 166.5 205 164.5 205 162 L205 156 Z" fill="white" opacity="0.9"/>

                    <!-- Small data packets on lines (decorative) -->
                    <circle cx="140" cy="120" r="3" fill="rgba(255,255,255,0.5)"/>
                    <circle cx="260" cy="120" r="3" fill="rgba(255,255,255,0.5)"/>
                    <circle cx="140" cy="205" r="3" fill="rgba(255,255,255,0.4)"/>
                    <circle cx="260" cy="205" r="3" fill="rgba(255,255,255,0.4)"/>
                </svg>

                {{-- Headline --}}
                <div class="mt-8 text-center">
                    <h2 class="text-2xl xl:text-3xl font-bold text-white leading-snug tracking-tight">
                        Your business,<br>always in control.
                    </h2>
                    <p class="mt-3 text-sm xl:text-base text-white/55 leading-relaxed max-w-sm mx-auto">
                        Real-time inventory, billing, and distribution intelligence for Cox's Bazaar mobile commerce.
                    </p>
                </div>

                {{-- Feature highlights --}}
                <div class="mt-8 flex flex-col gap-3">
                    @foreach([
                        ['icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Real-time inventory tracking'],
                        ['icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M5 20h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z', 'label' => 'Point-of-sale & billing'],
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Business analytics & reports'],
                    ] as $feature)
                    <div class="flex items-center gap-3 rounded-xl bg-white/5 px-4 py-3 ring-1 ring-white/8 backdrop-blur-sm">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500/25">
                            <svg class="h-4 w-4 text-brand-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $feature['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-sm text-white/70 font-medium">{{ $feature['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Bottom copyright --}}
        <div class="relative z-10 p-10 text-xs text-white/30">
            &copy; {{ date('Y') }} {{ config('app.name', "Cox's Bazaar MC2") }}. All rights reserved.
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         RIGHT PANEL — Authentication Form
    ═══════════════════════════════════════════════════════════ --}}
    <div class="relative flex w-full lg:w-[45%] xl:w-1/2 flex-col justify-center overflow-y-auto bg-white px-6 py-12 dark:bg-gray-950 sm:px-10 lg:px-12 xl:px-16">

        {{-- Dark mode toggle --}}
        <div class="absolute top-5 right-5 z-20">
            <button
                type="button"
                @click="$store.theme.toggle()"
                aria-label="Toggle dark/light theme"
                class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-xs transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" class="size-4" />
                <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" class="size-4" />
            </button>
        </div>

        <div class="mx-auto w-full max-w-sm">

            {{-- Logo (mobile) + Brand --}}
            <div class="mb-9">
                <a href="/" class="inline-flex items-center gap-3 mb-7 transition-opacity hover:opacity-80 lg:hidden">
                    <img src="{{ asset('cmc2-logo.jpeg') }}" alt="Logo" class="h-10 w-10 rounded-xl object-contain border border-gray-200 dark:border-gray-800 shadow-xs">
                    <div>
                        <span class="block text-base font-bold text-gray-900 dark:text-white leading-tight">{{ config('app.name', "Cox's Bazaar MC2") }}</span>
                        <span class="block text-xs text-gray-400 font-medium">Mobile Distribution ERP</span>
                    </div>
                </a>

                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Welcome back
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    Sign in to your account to continue.
                </p>
            </div>

            {{-- Session status (e.g. password reset link sent) --}}
            @if(session('status'))
                <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300" role="alert" aria-live="polite">
                    <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            {{-- Authentication error (generic, no user enumeration) --}}
            @if($errors->any())
                <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300" role="alert" aria-live="assertive">
                    <svg class="mt-0.5 size-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            {{-- Login Form --}}
            <form
                method="POST"
                action="{{ route('login') }}"
                class="space-y-5"
                x-data="{ loading: false, showPassword: false }"
                @submit="loading = true"
                novalidate
            >
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email address
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5" aria-hidden="true">
                            <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            autofocus
                            required
                            placeholder="name@company.com"
                            class="block w-full rounded-xl border bg-white pl-10 pr-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition-all duration-150 focus:outline-none focus:ring-4 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500
                                   {{ $errors->has('email') ? 'border-red-400 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-500' }}"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                        >
                    </div>
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        @if(Route::has('password.request'))
                            <a
                                href="{{ route('password.request') }}"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded"
                            >
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5" aria-hidden="true">
                            <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="block w-full rounded-xl border bg-white pl-10 pr-11 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition-all duration-150 focus:outline-none focus:ring-4 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500
                                   {{ $errors->has('password') ? 'border-red-400 focus:border-red-500 focus:ring-red-500/10 dark:border-red-500/60' : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-500' }}"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            :aria-pressed="showPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-r-xl"
                        >
                            {{-- Eye open --}}
                            <svg x-show="!showPassword" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            {{-- Eye closed --}}
                            <svg x-show="showPassword" x-cloak class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="mt-1.5 text-xs text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center">
                    <label for="remember" class="inline-flex items-center gap-2.5 cursor-pointer select-none group">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            {{ old('remember') ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/30 focus:ring-2 dark:border-gray-700 dark:bg-gray-800 dark:checked:bg-brand-600 transition cursor-pointer"
                        >
                        <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-200 transition-colors">
                            Keep me signed in
                        </span>
                    </label>
                </div>

                {{-- Submit button with loading state --}}
                <button
                    type="submit"
                    :disabled="loading"
                    :class="loading ? 'opacity-70 cursor-not-allowed' : 'hover:bg-brand-600 active:bg-brand-700'"
                    class="relative mt-1 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-150 focus:outline-none focus:ring-4 focus:ring-brand-500/20"
                >
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
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="loading ? 'Signing in…' : 'Sign in'">Sign in</span>
                </button>
            </form>

            {{-- Security + contact footer --}}
            <div class="mt-8 space-y-4">
                {{-- Security trust badge --}}
                <div class="flex items-center justify-center gap-2 text-xs text-gray-400 dark:text-gray-600">
                    <svg class="size-3.5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span>Secured session &mdash; credentials are never stored locally</span>
                </div>

                {{-- Admin contact --}}
                <p class="text-center text-xs text-gray-400 dark:text-gray-600">
                    Need access?
                    <span class="font-medium text-gray-500 dark:text-gray-500">Contact your system administrator.</span>
                </p>
            </div>

        </div>
    </div>
</div>

</body>
</html>
