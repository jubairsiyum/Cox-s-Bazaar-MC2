@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        'flex size-full items-center justify-center rounded-full bg-brand-500 text-sm text-ink group-data-[size=sm]/avatar:text-xs group-data-[size=xs]/avatar:text-xs  ',
        $attributes->get('class')
    );
@endphp
<template x-if="!loaded">
<span data-slot="avatar-fallback" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</span>
</template>
