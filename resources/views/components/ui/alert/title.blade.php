@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        '[&_a]:hover:text-foreground text-base font-medium text-ink group-has-[>svg]/alert:col-start-2  [&_a]:underline [&_a]:underline-offset-3',
        $attributes->get('class')
    );
@endphp
<div data-slot="alert-title" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</div>
