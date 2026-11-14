@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
    <div class="auth-top">
        <span>Account <b>recovery</b></span>
        <small>Remembered it?<br><a href="{{ route('login') }}">Back to sign in</a></small>
    </div>

    <h2>Set new password</h2>
    <p class="auth-desc">Choose a strong password for your account.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label class="auth-label" for="email">Email address</label>
            <input
                class="auth-input @error('email') is-invalid @enderror"
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $request->email) }}"
                autocomplete="username"
                required
                placeholder="name@company.com"
            >
            @error('email')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="password">New password</label>
            <input
                class="auth-input @error('password') is-invalid @enderror"
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
                placeholder="••••••••"
            >
            <button class="auth-eye" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
            @error('password')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="password_confirmation">Confirm password</label>
            <input
                class="auth-input @error('password_confirmation') is-invalid @enderror"
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                required
                placeholder="••••••••"
            >
            <button class="auth-eye" type="button" data-password-toggle="password_confirmation" aria-label="Show password">Show</button>
            @error('password_confirmation')
                <div class="auth-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="auth-btn">Reset password</button>
    </form>
@endsection
