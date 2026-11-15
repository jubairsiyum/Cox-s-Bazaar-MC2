@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            Forgot password?
        </h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
            Enter your email and we'll send you a password reset link.
        </p>
    </div>

    @if(session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300" role="alert">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Email address
            </label>
            <div class="mt-1.5">
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required
                    placeholder="name@company.com"
                    class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500/10 @enderror"
                >
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 px-4 py-3 text-sm font-semibold text-white shadow-theme-xs transition-colors focus:outline-none focus:ring-4 focus:ring-brand-500/20"
        >
            Email password reset link
        </button>
    </form>

    <div class="mt-6 border-t border-gray-100 pt-5 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
        Remember your password?
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
            Back to sign in
        </a>
    </div>
@endsection
