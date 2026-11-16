@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        'mb-2 text-theme-xl font-medium text-ink group-data-[size=sm]/card:mb-1  [div[data-slot=card-header]_&]:mb-0',
        $attributes->get('class')
    );
@endphp
<div data-slot="card-title" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</div>
