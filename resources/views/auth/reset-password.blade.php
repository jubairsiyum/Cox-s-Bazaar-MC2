@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
<div class="card">
    <div class="card-body" style="padding:28px;">
        <h1 style="font-size:1.1rem;font-weight:700;margin:0 0 20px;">Set New Password</h1>

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="form-group">
                <label for="email" class="form-label">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}"
                       required autocomplete="username"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">New Password <span class="required">*</span></label>
                <input type="password" id="password" name="password"
                       required autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm Password <span class="required">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password"
                       class="form-control @error('password_confirmation') is-invalid @enderror">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
                Reset Password
            </button>
        </form>
    </div>
</div>
@endsection
