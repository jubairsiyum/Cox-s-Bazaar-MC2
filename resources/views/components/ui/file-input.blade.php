@use('App\Support\TwMerge')
@php
    $classes = TwMerge::merge('h-11 w-full min-w-0 cursor-pointer overflow-hidden rounded-[10px] border border-line bg-transparent text-sm text-muted shadow-card transition-colors file:me-4 file:h-full file:cursor-pointer file:border-0 file:border-e file:border-solid file:border-line file:bg-canvas file:px-3.5 file:py-2.5 file:text-sm file:font-medium file:text-ink hover:file:bg-soft focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:border-line disabled:bg-soft/50 disabled:text-muted disabled:shadow-none    dark:file:border-line dark:file:bg-soft dark:file:text-muted dark:hover:file:bg-soft dark:focus:border-brand-500/40 dark:disabled:border-line dark:disabled:bg-surface/50', $attributes->get('class'));
@endphp
<input type="file" data-slot="file-input" {{ $attributes->except('class') }} class="{{ $classes }}" />
