@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="auth-top">
        <span>Account <b>recovery</b></span>
        <small>Remembered it?<br><a href="{{ route('login') }}">Back to sign in</a></small>
    </div>

    <h2>Forgot password?</h2>
    <p class="auth-desc">Enter your email address and we'll send you a secure link to reset your password.</p>

    @if(session('status'))
        <div class="auth-alert auth-alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="auth-field">
            <label class="auth-label" for="email">Email address</label>
            <input
                class="auth-input @error('email') is-invalid @enderror"
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
                autofocus
                required
                placeholder="name@company.com"
            >
            @error('email')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="auth-btn">Email me a reset link</button>
    </form>
@endsection
