@use('App\Support\TwMerge')
<div role="separator" aria-orientation="horizontal" data-slot="dropdown-menu-separator" {{ $attributes->except('class') }} class="{{ TwMerge::merge('-mx-3 my-1.5 h-px bg-soft ', $attributes->get('class')) }}"></div>
