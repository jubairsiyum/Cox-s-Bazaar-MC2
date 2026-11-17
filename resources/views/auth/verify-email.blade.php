@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            Verify your email
        </h1>
        <p class="mt-1.5 text-sm text-muted">
            We sent a verification link to <span class="font-semibold text-ink">{{ auth()->user()->email }}</span>. Click the link in the email to activate your account.
        </p>
    </div>

    @if(session('status') == 'verification-link-sent')
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-success/30 bg-soft p-4 text-sm text-success /20 /10" role="alert">
            <svg class="mt-0.5 size-5 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>A new verification link has been sent to your email address.</div>
        </div>
    @endif

    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button
                type="submit"
                class="w-full inline-flex items-center justify-center rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 px-4 py-3 text-sm font-semibold text-on-brand shadow-card transition-colors focus:outline-none focus:ring-4 focus:ring-brand-500/20"
            >
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="w-full inline-flex items-center justify-center rounded-xl border border-line bg-transparent hover:bg-soft px-4 py-2.5 text-sm font-medium text-ink transition-colors focus:outline-none"
            >
                Log out
            </button>
        </form>
    </div>
@endsection
