@use('App\Support\TwMerge')
<div role="alert" data-slot="field-error" {{ $attributes->except('class') }} class="{{ TwMerge::merge('text-sm font-normal text-danger ', $attributes->get('class')) }}">{{ $slot }}</div>
