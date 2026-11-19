{{-- Image thumbnail with an initials fallback behind it; a broken/missing image is removed so the initials show instead of a broken-image icon --}}
@props(['src' => null, 'alt' => '', 'label' => '', 'size' => 'size-9'])

@php
    $initials = \Illuminate\Support\Str::of($label)
        ->upper()
        ->trim()
        ->explode(' ')
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<span {{ $attributes->merge(['class' => "relative inline-flex {$size} shrink-0 items-center justify-center overflow-hidden rounded border border-line bg-soft"]) }}>
    <span class="text-xs font-bold text-muted">{{ $initials !== '' ? $initials : '?' }}</span>
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt }}"
             class="absolute inset-0 size-full bg-surface object-contain p-0.5"
             onerror="this.remove()">
    @endif
</span>
