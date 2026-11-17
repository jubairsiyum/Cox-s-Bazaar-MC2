@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 sm:gap-2']) }}>
    <p class="text-xs font-normal text-muted sm:text-sm">
        {{ $label }}
    </p>
    <p class="text-sm font-medium text-ink sm:text-base" x-text="{{ $value }} || '—'"></p>
</div>
