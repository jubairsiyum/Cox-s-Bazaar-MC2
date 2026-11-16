@use('App\Support\TwMerge')
<p data-slot="dialog-description" x-bind:id="descriptionId" {{ $attributes->except('class') }} class="{{ TwMerge::merge('text-sm text-muted', $attributes->get('class')) }}">{{ $slot }}</p>
