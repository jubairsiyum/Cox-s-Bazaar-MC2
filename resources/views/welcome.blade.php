@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
<div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
    <h1 class="text-3xl font-extrabold text-ink sm:text-4xl">{{ config('app.name') }}</h1>
    <p class="mt-3 max-w-md text-sm text-muted">Cox's Bazaar Mobile Distribution ERP System</p>
    <div class="mt-6 flex gap-3">
        <a href="{{ route('login') }}" class="btn btn-primary">Sign In</a>
    </div>
</div>
@endsection
