@use('App\Support\TwMerge')
@props(['size' => 'default'])
@php
    $classes = TwMerge::merge(
        'group/card flex flex-col gap-5 rounded-xl border border-line bg-surface p-5 data-[size=sm]:p-4 sm:p-6  ',
        $attributes->get('class')
    );
@endphp
<div data-slot="card" data-size="{{ $size }}" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</div>
