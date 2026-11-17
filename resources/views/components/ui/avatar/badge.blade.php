@use('App\Support\TwMerge')
@props(['variant' => 'online'])
@php
    $classes = TwMerge::merge(
        'text-primary-foreground absolute top-0 end-0 z-10 inline-flex items-center justify-center rounded-full bg-blend-color ring-2 ring-surface select-none ',
        'data-[variant=busy]:bg-warning data-[variant=offline]:bg-danger data-[variant=online]:bg-success',
        'group-data-[size=xs]/avatar:size-1.5 group-data-[size=xs]/avatar:[&>svg]:hidden',
        'group-data-[size=sm]/avatar:size-2 group-data-[size=sm]/avatar:[&>svg]:hidden',
        'group-data-[size=default]/avatar:size-2.5 group-data-[size=default]/avatar:[&>svg]:size-2',
        'group-data-[size=lg]/avatar:size-3 group-data-[size=lg]/avatar:[&>svg]:size-2',
        'group-data-[size=xl]/avatar:size-3.5 group-data-[size=xl]/avatar:[&>svg]:size-2.5',
        $attributes->get('class')
    );
@endphp
<span data-slot="avatar-badge" data-variant="{{ $variant }}" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</span>
