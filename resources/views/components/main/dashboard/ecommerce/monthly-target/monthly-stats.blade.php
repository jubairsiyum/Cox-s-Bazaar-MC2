@php
    $stats = [
        ['key' => 'target', 'value' => '$20K', 'trend' => 'down'],
        ['key' => 'revenue', 'value' => '$70K', 'trend' => 'up'],
        ['key' => 'today', 'value' => '$30K', 'trend' => 'up'],
    ];
@endphp
<div class="flex items-center justify-center gap-5 px-6 py-3 sm:gap-8 sm:py-5">
    @foreach ($stats as $index => $stat)
        @if ($index > 0)
            <div class="h-7 w-px bg-line"></div>
        @endif
        <div>
            <p class="mb-1 text-center text-xs text-muted sm:text-sm">
                {{ __('ecommerce.monthlyTarget.' . $stat['key']) }}
            </p>
            <p class="flex items-center justify-center gap-1 text-base font-semibold text-ink sm:text-lg">
                {{ $stat['value'] }}
                @if ($stat['trend'] === 'up')
                    <x-svg.arrow-up class="text-success" />
                @else
                    <x-svg.arrow-down class="text-danger" />
                @endif
            </p>
        </div>
    @endforeach
</div>
