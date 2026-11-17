@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            Forgot password?
        </h1>
        <p class="mt-1.5 text-sm text-muted">
            Enter your email and we'll send you a password reset link.
        </p>
    </div>

    @if(session('status'))
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-success/30 bg-soft p-4 text-sm text-success /20 /10" role="alert">
            <svg class="mt-0.5 size-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-ink">
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
                    class="block w-full rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink placeholder-muted transition focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:placeholder-muted @error('email') border-danger focus:border-danger focus:ring-danger/10 @enderror"
                >
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 px-4 py-3 text-sm font-semibold text-on-brand shadow-card transition-colors focus:outline-none focus:ring-4 focus:ring-brand-500/20"
        >
            Email password reset link
        </button>
    </form>

    <div class="mt-6 border-t border-line pt-5 text-center text-xs text-muted">
        Remember your password?
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">
            Back to sign in
        </a>
    </div>
@endsection
