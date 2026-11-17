{{-- `value` is an Alpine expression resolved in the address scope. --}}
@props(['label', 'value'])

<div {{ $attributes }}>
    <p class="mb-2 text-xs leading-normal text-muted">
        {{ $label }}
    </p>
    <p class="text-sm font-medium text-ink" x-text="{{ $value }}"></p>
</div>
