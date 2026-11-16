@use('App\Support\TwMerge')
@props(['placeholder' => ''])
<span data-slot="select-value" {{ $attributes->except('class') }}
    class="{{ TwMerge::merge('flex flex-1 items-center gap-2 truncate text-start text-ink group-data-placeholder/select-trigger:text-muted dark:group-data-placeholder/select-trigger:text-muted', $attributes->get('class')) }}"
    x-text="label ?? @js($placeholder)">{{ $placeholder }}</span>
