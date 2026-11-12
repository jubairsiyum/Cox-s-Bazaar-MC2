{{-- Initials avatar used throughout the admin module --}}
@props(['name' => '', 'size' => 'md'])

@php
    $initials = \Illuminate\Support\Str::of($name)
        ->upper()
        ->trim()
        ->explode(' ')
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<span class="avatar avatar-{{ $size }}" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</span>
