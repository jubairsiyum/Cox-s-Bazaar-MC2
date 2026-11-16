@use('App\Support\TwMerge')
@props(['size' => 'default'])
@php
    $classes = TwMerge::merge('group/select-trigger group/select-trigger flex w-full min-w-0 items-center justify-between gap-2 rounded-[10px] border border-line bg-surface py-2.5 ps-4 pe-3.5 text-sm font-normal text-ink shadow-card outline-hidden transition-all duration-200 select-none hover:border-line hover:bg-canvas/50 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus-visible:border-brand-300 focus-visible:ring-3 focus-visible:ring-brand-500/20 disabled:cursor-not-allowed disabled:border-line disabled:bg-soft/50 disabled:text-muted disabled:shadow-none aria-invalid:border-danger aria-invalid:ring-3 aria-invalid:ring-danger/20 data-invalid:border-danger data-invalid:ring-3 data-invalid:ring-danger/20 data-popup-open:border-brand-300 data-popup-open:ring-3 data-popup-open:ring-brand-500/20 data-[size=default]:h-11 data-[size=default]:rounded-[10px] data-[size=lg]:h-12 data-[size=lg]:rounded-[10px] data-[size=lg]:py-3 data-[size=lg]:ps-4.5 data-[size=lg]:pe-3.5 data-[size=sm]:h-9 data-[size=sm]:rounded-lg data-[size=sm]:py-2 data-[size=sm]:ps-3.5 data-[size=sm]:pe-2.5      dark:focus:border-brand-500/40 dark:focus-visible:border-brand-500/40 dark:disabled:border-line dark:disabled:bg-surface/50 dark:disabled:text-muted dark:data-popup-open:border-brand-500/40 [&_svg]:pointer-events-none [&_svg]:shrink-0', $attributes->get('class'));
@endphp
<button type="button" role="combobox" aria-haspopup="listbox" aria-expanded="false" data-slot="select-trigger" data-size="{{ $size }}" data-state="closed"
    x-ref="trigger" x-bind:id="id + '-trigger'" x-bind:aria-expanded="open ? 'true' : 'false'" x-bind:data-state="state" x-bind:data-popup-open="open ? '' : null"
    x-bind:data-placeholder="label == null ? '' : null" x-on:click="toggle()" x-on:keydown="onTriggerKeydown($event)"
    {{ $attributes->except('class') }} class="{{ $classes }}">
    {{ $slot }}
    <x-svg.chevron-down class="size-4 text-muted transition-transform duration-200 group-hover/select-trigger:text-ink group-data-popup-open/select-trigger:rotate-180 dark:group-hover/select-trigger:text-muted" />
</button>
