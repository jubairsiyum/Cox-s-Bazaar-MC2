@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            Set new password
        </h1>
        <p class="mt-1.5 text-sm text-muted">
            Choose a strong password for your account.
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-ink">
                Email address
            </label>
            <div class="mt-1.5">
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    autocomplete="username"
                    required
                    placeholder="name@company.com"
                    class="block w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink placeholder-muted transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:placeholder-muted @error('email') border-danger focus:border-danger focus:ring-danger/10 @enderror"
                >
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="block text-sm font-medium text-ink">
                New password
            </label>
            <div class="relative mt-1.5">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required
                    placeholder="••••••••"
                    class="block w-full rounded-xl border border-line bg-surface px-4 py-2.5 pr-11 text-sm text-ink placeholder-muted transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:placeholder-muted @error('password') border-danger focus:border-danger focus:ring-danger/10 @enderror"
                >
                <button
                    type="button"
                    @click="show = !show"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted hover:text-muted transition-colors focus:outline-none"
                    :aria-label="show ? 'Hide password' : 'Show password'"
                >
                    <svg x-show="!show" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="show" x-cloak class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink">
                Confirm new password
            </label>
            <div class="mt-1.5">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                    placeholder="••••••••"
                    class="block w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink placeholder-muted transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:placeholder-muted"
                >
            </div>
        </div>

        <button
            type="submit"
            class="w-full inline-flex items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 px-4 py-3 text-sm font-semibold text-on-brand shadow-card transition-colors focus:outline-none focus:ring-4 focus:ring-brand-500/20"
        >
            Reset password
        </button>
    </form>

    <div class="mt-6 border-t border-line pt-5 text-center text-xs text-muted">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">
            Back to sign in
        </a>
    </div>
@endsection
