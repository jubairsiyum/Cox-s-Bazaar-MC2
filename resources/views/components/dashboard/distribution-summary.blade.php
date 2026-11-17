{{--
  SR & Field Distribution Summary Component
  Usage: <x-dashboard.distribution-summary />
--}}
@props([
  'title' => 'Field Distribution & SR',
  'activeRoutes' => 8,
  'dispatchedUnits' => 185,
  'placedUnits' => 142,
  'returnsCount' => 43,
])

@php
  $pct = min(max(round(($placedUnits / max($dispatchedUnits, 1)) * 100), 0), 100);
@endphp

<section class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between h-full">
  <div>
    {{-- Header --}}
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
        <p class="mt-0.5 text-xs text-muted">Daily SR field placement & routing</p>
      </div>
      <div class="grid size-8 place-items-center rounded-2xl bg-soft text-brand-500">
        <x-icon name="truck" class="size-4" />
      </div>
    </div>

    {{-- Main Metric with Semi-circle Gauge matching Top Stat Cards --}}
    <div class="mt-6 flex items-end justify-between gap-4">
      <div>
        <p class="text-3xl sm:text-4xl font-extrabold tracking-tight text-ink">{{ $dispatchedUnits }} <span class="text-base sm:text-lg font-medium text-muted">Pcs</span></p>
        <p class="mt-1 text-xs text-muted">Total Handsets Dispatched Today</p>
      </div>

      {{-- Gauge: pathLength=100 allows dasharray to match percentage directly --}}
      <div class="relative h-14 w-24 shrink-0 sm:h-16 sm:w-28">
        <svg viewBox="0 0 120 64" class="h-full w-full">
          <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
                class="text-line" stroke-width="12" stroke-linecap="round"/>
          <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
                class="text-brand-500" stroke-width="12" stroke-linecap="round"
                stroke-dasharray="{{ $pct }} 100"/>
        </svg>
        <span class="absolute inset-x-0 bottom-0 text-center text-[11px] font-semibold text-brand-500">
          {{ $pct }}% Placed
        </span>
      </div>
    </div>

    {{-- Progress bar breakdown --}}
    <div class="mt-6 space-y-2.5">
      <div class="h-3 w-full overflow-hidden rounded-full bg-line flex">
        <div class="h-full bg-brand-500 transition-all duration-500" style="width: {{ ($placedUnits / max($dispatchedUnits, 1)) * 100 }}%"></div>
        <div class="h-full bg-warning transition-all duration-500" style="width: {{ ($returnsCount / max($dispatchedUnits, 1)) * 100 }}%"></div>
      </div>
      <div class="flex items-center justify-between text-xs text-muted pt-1">
        <span class="flex items-center gap-1.5 font-medium text-ink">
          <span class="size-2 rounded-full bg-brand-500"></span> Placed: <strong class="text-ink">{{ $placedUnits }} pcs</strong>
        </span>
        <span class="flex items-center gap-1.5 font-medium text-ink">
          <span class="size-2 rounded-full bg-warning"></span> In-Transit: <strong class="text-ink">{{ $returnsCount }} pcs</strong>
        </span>
      </div>
    </div>
  </div>

  {{-- Status Footer --}}
  <div class="mt-6 flex items-center justify-between border-t border-line pt-4">
    <div class="flex items-center gap-2 text-xs font-semibold text-brand-500">
      <span class="relative grid size-3 place-items-center">
        <span class="absolute size-3 animate-ping rounded-full bg-brand-500/40"></span>
        <span class="size-2 rounded-full bg-brand-500"></span>
      </span>
      <span>{{ $activeRoutes }} Active Field SRs</span>
    </div>
    <a href="{{ url('/distribution') }}" class="text-xs font-medium text-muted hover:text-brand-500 transition-colors">
      View Routes &rarr;
    </a>
  </div>
</section>
