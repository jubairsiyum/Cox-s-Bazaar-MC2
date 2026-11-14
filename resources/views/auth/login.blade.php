@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <div class="auth-top">
        <span>Welcome to <b>{{ config('app.name') }}</b></span>
        <small>Need access?<br>Contact your administrator</small>
    </div>

    <h2>Sign in</h2>
    <p class="auth-desc">Enter your credentials to access your dashboard.</p>

    @if(session('status'))
        <div class="auth-alert auth-alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
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

        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input
                class="auth-input @error('password') is-invalid @enderror"
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
                placeholder="••••••••"
            >
            <button class="auth-eye" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
            @error('password')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-row">
            <label for="remember">
                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                Keep me signed in
            </label>
            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="auth-btn">Sign in</button>
    </form>
@endsection
