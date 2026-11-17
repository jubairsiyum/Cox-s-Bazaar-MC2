@props(['title' => 'Card Title Here', 'description' => 'Start putting content on grids or panels, you can also use different combinations of grids. Please check out the dashboard and other pages'])
<x-ui.card {{ $attributes->merge(['class' => 'flex min-h-screen items-center justify-center rounded-2xl border border-line bg-surface px-5 py-7 shadow-none xl:px-10 xl:py-12  ']) }}>
    <div class="mx-auto w-full max-w-157.5 text-center">
        <h3 class="text-theme-xl mb-4 font-semibold text-ink sm:text-2xl">{{ $title }}</h3>
        <p class="text-sm text-muted sm:text-base">{{ $description }}</p>
    </div>
</x-ui.card>
