@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
<div class="card">
    <div class="card-body" style="padding:28px;">
        <h1 style="font-size:1.1rem;font-weight:700;margin:0 0 4px;">Welcome back</h1>
        <p style="font-size:.8rem;color:var(--color-text-muted);margin:0 0 24px;">Sign in to your ERP account</p>

        {{-- Session status --}}
        @if(session('status'))
            <div class="alert alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="login-form">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="you@example.com"
                >
                @error('email')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span class="required">*</span></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="••••••••"
                >
                @error('password')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:7px;font-size:.82rem;cursor:pointer;">
                    <input type="checkbox" name="remember" id="remember" class="form-checkbox">
                    Remember me
                </label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       style="font-size:.8rem;color:var(--color-primary);text-decoration:none;">
                        Forgot password?
                    </a>
                @endif
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
                Sign In
            </button>
        </form>
    </div>
</div>
@endsection
