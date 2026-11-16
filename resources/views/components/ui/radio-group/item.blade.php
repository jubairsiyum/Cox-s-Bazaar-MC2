@aware(['defaultValue' => null])
@use('App\Support\TwMerge')
@props(['value', 'id' => null, 'disabled' => false])
@php
    $checked = $defaultValue === $value;
    $classes = TwMerge::merge('group/radio-group-item peer relative flex size-4 shrink-0 cursor-pointer items-center justify-center rounded-full border border-line bg-surface shadow-card outline-hidden transition-colors group-has-focus-visible/field-label:ring-0 group-has-disabled/field:opacity-50 after:absolute after:-inset-x-3 after:-inset-y-2 hover:border-brand-500 focus-visible:border-brand-500 focus-visible:ring-3 focus-visible:ring-brand-500/20 disabled:cursor-not-allowed disabled:border-line disabled:bg-soft/50 disabled:shadow-none aria-invalid:border-danger aria-invalid:ring-3 aria-invalid:ring-danger/20 data-checked:border-brand-500 data-checked:bg-brand-500 data-checked:hover:border-brand-600 data-checked:hover:bg-brand-600 aria-invalid:data-checked:border-danger aria-invalid:data-checked:bg-danger data-disabled:data-checked:border-line data-disabled:data-checked:bg-transparent    dark:focus-visible:border-brand-400 dark:focus-visible:ring-brand-500/30 dark:disabled:border-line dark:disabled:bg-surface/50 dark:aria-invalid:border-danger/50 dark:aria-invalid:ring-danger/40 dark:data-checked:border-brand-500 dark:data-checked:bg-brand-500 dark:data-checked:hover:border-brand-600 dark:data-checked:hover:bg-brand-600 dark:data-disabled:data-checked:border-line', $attributes->get('class'));
@endphp
<button type="button" role="radio" data-slot="radio-group-item" value="{{ $value }}" @if ($id) id="{{ $id }}" @endif @disabled($disabled)
    aria-checked="{{ $checked ? 'true' : 'false' }}" data-state="{{ $checked ? 'checked' : 'unchecked' }}" @if ($checked) data-checked="" @endif tabindex="{{ $checked ? 0 : -1 }}"
    x-bind:aria-checked="value === @js($value) ? 'true' : 'false'" x-bind:data-state="value === @js($value) ? 'checked' : 'unchecked'" x-bind:data-checked="value === @js($value) ? '' : null"
    x-bind:tabindex="value === @js($value) ? 0 : -1" x-on:click="value = @js($value)"
    {{ $attributes->except('class') }} class="{{ $classes }}">
    <span data-state="checked" data-slot="radio-group-indicator" x-show="value === @js($value)" @unless ($checked) style="display: none;" @endunless class="flex size-4 items-center justify-center"><span class="size-2 rounded-full bg-surface group-data-disabled/radio-group-item:bg-line dark:group-data-disabled/radio-group-item:bg-surface"></span></span>
</button>
