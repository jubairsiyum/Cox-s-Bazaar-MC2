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

<section class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between h-full">
  <div>
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
        <p class="mt-0.5 text-xs text-muted">Daily SR field placement & routing</p>
      </div>
      <div class="grid size-8 place-items-center rounded-2xl bg-soft text-brand-500">
        <x-icon name="truck" class="size-4" />
      </div>
    </div>

    {{-- Main Metric --}}
    <div class="mt-6 flex items-baseline justify-between">
      <div>
        <p class="text-4xl font-extrabold tracking-tight text-ink">{{ $dispatchedUnits }} <span class="text-lg font-medium text-muted">Pcs</span></p>
        <p class="mt-1 text-xs text-muted">Total Handsets Dispatched Today</p>
      </div>
      <div class="text-right">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-brand-500">
          <span class="size-2 rounded-full bg-brand-500"></span>
          {{ round(($placedUnits / max($dispatchedUnits, 1)) * 100) }}% Placed
        </span>
      </div>
    </div>

    {{-- Progress bar breakdown --}}
    <div class="mt-5 space-y-2">
      <div class="h-3 w-full overflow-hidden rounded-full bg-line flex">
        <div class="h-full bg-brand-500 transition-all duration-500" style="width: {{ ($placedUnits / max($dispatchedUnits, 1)) * 100 }}%"></div>
        <div class="h-full bg-warning transition-all duration-500" style="width: {{ ($returnsCount / max($dispatchedUnits, 1)) * 100 }}%"></div>
      </div>
      <div class="flex items-center justify-between text-xs text-muted pt-1">
        <span class="flex items-center gap-1.5 font-medium text-ink">
          <span class="size-2 rounded-full bg-brand-500"></span> Placed: <strong class="text-ink">{{ $placedUnits }} pcs</strong>
        </span>
        <span class="flex items-center gap-1.5 font-medium text-ink">
          <span class="size-2 rounded-full bg-warning"></span> In-Transit / Pending: <strong class="text-ink">{{ $returnsCount }} pcs</strong>
        </span>
      </div>
    </div>

    {{-- Stylized Delivery Route SVG background element --}}
    <div class="mt-6 rounded-2xl border border-line bg-soft/30 p-3.5">
      <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-2">
          <span class="grid size-7 place-items-center rounded-xl bg-surface text-brand-500 shadow-xs">
            <x-icon name="smartphone" class="size-3.5" />
          </span>
          <div>
            <p class="font-semibold text-ink">Chakaria & Teknaf Routes</p>
            <p class="text-[11px] text-muted">High volume deliveries in progress</p>
          </div>
        </div>
        <span class="font-bold text-brand-500">32 Retailers</span>
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
