@props(['tabs'])

<div class="mb-6 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-lg font-semibold text-ink">
            {{ __('ecommerce.statistics.title') }}
        </h3>
        <p class="mt-1 text-sm text-muted">
            {{ __('ecommerce.statistics.subtitle') }}
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3 sm:justify-end">
        <x-ui.button-group class="gap-0.5 rounded-lg bg-soft p-0.5 *:data-slot:rounded-md!">
            @foreach ($tabs as $tab)
                <x-ui.button variant="none" size="sm" class="h-8 px-3 text-sm font-medium transition-colors"
                    x-bind:class="tab === '{{ $tab }}' ? 'bg-surface text-ink shadow-card hover:bg-surface hover:text-ink ' : 'text-muted hover:bg-transparent hover:text-ink '"
                    x-on:click="tab = '{{ $tab }}'">
                    {{ __('ecommerce.statistics.' . $tab) }}
                </x-ui.button>
            @endforeach
        </x-ui.button-group>

        <div class="dashboard-datepicker relative inline-flex items-center">
            <x-svg.calendar class="pointer-events-none absolute start-3 top-1/2 z-10 -translate-y-1/2 text-muted" />
            <input x-ref="picker" placeholder="{{ __('ecommerce.statistics.selectDateRange') }}"
                class="h-9 w-40 cursor-pointer rounded-lg border border-line bg-surface ps-9 pe-3 text-sm font-medium text-ink shadow-card outline-none hover:bg-canvas focus:border-brand-300" />
        </div>
    </div>
</div>
