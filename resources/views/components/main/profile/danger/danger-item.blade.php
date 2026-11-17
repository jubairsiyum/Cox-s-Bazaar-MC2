@props(['title', 'description', 'buttonText', 'icon' => null, 'iconClass' => '', 'variant' => 'outline', 'hasDivider' => false])

<div {{ $attributes->merge(['class' => 'flex flex-col justify-between gap-4 py-4 first:pt-0 sm:flex-row sm:items-center ' . ($hasDivider ? 'border-b border-line pb-4 ' : '')]) }}>
    <div>
        <h4 class="mb-1 text-base font-medium text-ink">
            {{ $title }}
        </h4>
        <p class="text-sm text-muted">
            {{ $description }}
        </p>
    </div>
    <div class="flex shrink-0 items-center">
        <x-ui.button
            type="button"
            variant="outline"
            class="{{ $variant === 'danger-outline' ? 'border-line text-danger shadow-card hover:bg-soft hover:text-danger /40 ' : '' }}"
        >
            @if ($icon)
                <x-dynamic-component :component="'svg.' . $icon" :class="$iconClass" />
            @endif
            <span>{{ $buttonText }}</span>
        </x-ui.button>
    </div>
</div>
