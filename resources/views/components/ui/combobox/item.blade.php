@use('App\Support\TwMerge')
@props(['value', 'disabled' => false])
@php
    $classes = TwMerge::merge("group/combobox-item relative flex w-full cursor-pointer items-center gap-3 rounded-lg py-2.5 ps-3 pe-8 text-sm font-medium text-ink outline-hidden transition-colors duration-200 select-none hover:bg-soft hover:text-ink focus:bg-soft focus:text-ink data-disabled:pointer-events-none data-disabled:opacity-50 data-highlighted:bg-soft data-highlighted:text-ink data-inset:ps-8    dark:focus:bg-soft dark:focus:text-muted dark:data-highlighted:bg-soft dark:data-highlighted:text-muted [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg]:text-muted group-hover/combobox-item:[&_svg]:text-ink data-highlighted:[&_svg]:text-ink dark:[&_svg]:text-muted dark:group-hover/combobox-item:[&_svg]:text-muted dark:data-highlighted:[&_svg]:text-muted [&_svg:not([class*=size-])]:size-5", $attributes->get('class'));
@endphp
<div role="option" tabindex="-1" data-slot="combobox-item" data-value="{{ $value }}" @if ($disabled) data-disabled="" @endif
    x-data="{ label: '' }" x-init="label = $el.querySelector('[data-slot=combobox-item-text]').textContent.trim(); register(@js($value), label)"
    x-show="matches(label)"
    x-bind:aria-selected="value === @js($value) ? 'true' : 'false'" x-bind:data-state="value === @js($value) ? 'checked' : 'unchecked'"
    x-on:pointermove="highlight($el)" x-on:click="pick(@js($value))"
    {{ $attributes->except('class') }} class="{{ $classes }}">
    <span data-slot="combobox-item-text" class="flex flex-1 shrink-0 items-center gap-2 whitespace-nowrap">{{ $slot }}</span>
    <span class="pointer-events-none absolute end-3 flex items-center justify-center text-brand-500" data-slot="combobox-item-indicator">
        <span x-show="value === @js($value)" style="display: none;"><x-svg.check class="size-4" /></span>
    </span>
</div>
