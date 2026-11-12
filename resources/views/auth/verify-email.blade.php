@extends('layouts.guest')

@section('title', 'Verify Email')

@section('content')
<div class="card">
    <div class="card-body" style="padding:28px;">
        <h1 style="font-size:1.1rem;font-weight:700;margin:0 0 4px;">Verify Your Email</h1>
        <p style="font-size:.8rem;color:var(--color-text-muted);margin:0 0 20px;">
            Thanks for signing up! Before getting started, please verify your email address by clicking the link we just emailed to you.
        </p>

        @if(session('status'))
            <div class="alert alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
        @endif

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Resend Verification Email</button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Log Out</button>
            </form>
        </div>
    </div>
</div>
@endsection
