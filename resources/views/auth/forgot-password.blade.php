@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
<div class="card">
    <div class="card-body" style="padding:28px;">
        <h1 style="font-size:1.1rem;font-weight:700;margin:0 0 4px;">Forgot Password</h1>
        <p style="font-size:.8rem;color:var(--color-text-muted);margin:0 0 20px;">
            Enter your email and we'll send a reset link.
        </p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       required autofocus
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="you@example.com">
                @error('email')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
                Send Reset Link
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;">
            <a href="{{ route('login') }}" style="font-size:.8rem;color:var(--color-text-muted);text-decoration:none;">
                ← Back to Sign In
            </a>
        </div>
    </div>
</div>
@endsection
