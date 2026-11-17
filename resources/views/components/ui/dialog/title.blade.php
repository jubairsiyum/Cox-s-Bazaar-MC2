@use('App\Support\TwMerge')
<h2 data-slot="dialog-title" x-bind:id="titleId" {{ $attributes->except('class') }} class="{{ TwMerge::merge('text-lg font-semibold text-ink', $attributes->get('class')) }}">{{ $slot }}</h2>
