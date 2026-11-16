@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge(
        'flex items-center gap-2 text-sm leading-none font-medium text-ink select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 ',
        $attributes->get('class')
    );
@endphp
<label data-slot="label" {{ $attributes->except('class') }} class="{{ $classes }}">{{ $slot }}</label>
