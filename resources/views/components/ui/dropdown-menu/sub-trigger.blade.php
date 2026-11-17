@use('App\Support\TwMerge')
@props(['inset' => false, 'showIcon' => false])
@php
    $classes = TwMerge::merge('group/dropdown-menu-sub-trigger relative flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink outline-hidden transition-colors duration-200 select-none hover:bg-soft hover:text-ink focus:bg-soft focus:text-ink data-highlighted:bg-soft data-highlighted:text-ink data-inset:ps-8 data-open:bg-soft data-open:text-ink data-popup-open:bg-soft data-popup-open:text-ink   dark:focus:bg-soft dark:focus:text-muted dark:data-highlighted:bg-soft dark:data-highlighted:text-muted dark:data-open:bg-soft dark:data-open:text-muted dark:data-popup-open:bg-soft dark:data-popup-open:text-muted [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg]:text-muted group-hover/dropdown-menu-sub-trigger:[&_svg]:text-ink data-highlighted:[&_svg]:text-ink dark:[&_svg]:text-muted dark:group-hover/dropdown-menu-sub-trigger:[&_svg]:text-muted dark:data-highlighted:[&_svg]:text-muted [&_svg:not([class*=size-])]:size-5', $attributes->get('class'));
@endphp
<div role="menuitem" tabindex="-1" aria-haspopup="menu" aria-expanded="false" data-state="closed" data-orientation="vertical" data-slot="dropdown-menu-sub-trigger" @if ($inset) data-inset="" @endif
    x-ref="trigger" x-bind:aria-expanded="open ? 'true' : 'false'" x-bind:data-state="state" x-bind:data-popup-open="open ? '' : null"
    x-on:pointermove="highlight($el); show()" x-on:click="show(true)" x-on:keydown="onSubTriggerKeydown($event)"
    {{ $attributes->except('class') }} class="{{ $classes }}">
    {{ $slot }}
    @if ($showIcon)<x-svg.chevron-right class="ms-auto size-4 text-muted rtl:rotate-180" />@endif
</div>
