@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        'group/avatar-group flex -space-x-2 *:data-[slot=avatar]:ring-2 *:data-[slot=avatar]:ring-surface ',
        $attributes->get('class')
    );
@endphp
<div data-slot="avatar-group" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</div>
