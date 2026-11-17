@props([
    'title',
    'desc' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-surface  /[0.03]']) }}>
    <!-- Card Header -->
    <div class="px-6 py-5">
        <h3 class="text-base font-medium text-ink">
            {{ $title }}
        </h3>
        @if($desc)
            <p class="mt-1 text-sm text-muted">
                {{ $desc }}
            </p>
        @endif
    </div>

    <!-- Card Body -->
    <div class="p-4 border-t border-line sm:p-6">
        <div class="space-y-6">
            {{ $slot }}
        </div>
    </div>
</div>