@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')
    <div class="auth-top">
        <span>Account <b>verification</b></span>
        <small>Signed in as<br><b>{{ auth()->user()->email }}</b></small>
    </div>

    <h2>Verify your email</h2>
    <p class="auth-desc">
        We sent a verification link to <b>{{ auth()->user()->email }}</b>. Click the link in the email to activate your account, or request a new one below.
    </p>

    @if(session('status'))
        <div class="auth-alert auth-alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="auth-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="auth-btn">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="auth-btn-secondary">Log out</button>
        </form>
    </div>
@endsection
