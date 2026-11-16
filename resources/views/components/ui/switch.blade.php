@use('App\Support\TwMerge')
@props(['size' => 'default', 'checked' => false, 'model' => null, 'disabled' => false])
@php
    $variants = [
        'default' => [
            'root' => 'h-6 w-13 p-0.5',
            'thumb' => 'h-5 w-[30px] data-checked:translate-x-[18px] rtl:data-checked:-translate-x-[18px]',
        ],
        'sm' => [
            'root' => 'h-5 w-11 p-[1.5px]',
            'thumb' => 'h-[17px] w-[26px] data-checked:translate-x-[15px] rtl:data-checked:-translate-x-[15px]',
        ],
    ];
    $variant = $variants[$size] ?? $variants['default'];
    $state = $model ?? 'on';
    $rootClasses = TwMerge::merge(
        'peer relative inline-flex shrink-0 cursor-pointer items-center rounded-full transition-colors outline-hidden select-none focus-visible:ring-2 focus-visible:ring-line focus-visible:outline-hidden  bg-line hover:bg-soft   data-checked:bg-soft data-checked:hover:bg-soft   data-disabled:cursor-not-allowed data-disabled:bg-soft data-disabled:hover:bg-soft   data-disabled:data-checked:bg-soft data-disabled:data-checked:hover:bg-soft   group-has-focus-visible/field-label:ring-0 after:absolute after:-inset-x-3 after:-inset-y-2 aria-invalid:ring-3 aria-invalid:ring-line ',
        $variant['root'],
        $attributes->get('class')
    );
    $thumbClasses = TwMerge::merge(
        'pointer-events-none block shrink-0 rounded-full bg-surface shadow-card ring-0 transition-transform duration-150 ease-linear data-unchecked:translate-x-0 rtl:data-unchecked:translate-x-0',
        $variant['thumb']
    );
@endphp
<button
    type="button"
    role="switch"
    data-slot="switch"
    data-size="{{ $size }}"
    @unless ($model) x-data="{ on: {{ $checked ? 'true' : 'false' }} }" @endunless
    @if ($disabled) disabled data-disabled="" @endif
    x-bind:aria-checked="{{ $state }} ? 'true' : 'false'"
    x-bind:data-state="{{ $state }} ? 'checked' : 'unchecked'"
    x-bind:data-checked="{{ $state }} ? '' : null"
    x-bind:data-unchecked="{{ $state }} ? null : ''"
    @unless ($disabled) x-on:click="{{ $state }} = !{{ $state }}" @endunless
    {{ $attributes->except(['class', 'model']) }}
    class="{{ $rootClasses }}"
>
    <span
        data-slot="switch-thumb"
        x-bind:data-checked="{{ $state }} ? '' : null"
        x-bind:data-unchecked="{{ $state }} ? null : ''"
        class="{{ $thumbClasses }}"
    ></span>
</button>
