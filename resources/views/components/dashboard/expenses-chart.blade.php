{{--
  Expenses Chart Component
  Usage: <x-dashboard.expenses-chart :series="['this' => [...12], 'last' => [...12]]" />
--}}
@props([
  'title' => 'Annual Expenses',
  'currency' => '৳',
  'series' => [
    'last' => [1300, 1500, 1800, 2400, 1700, 4000, 2200, 1900, 1600, 2600, 1400, 2300],
    'this' => [1500, 1700, 2100, 2000, 1900, 3200, 2600, 2100, 1800, 2400, 1700, 2700],
  ],
])

<section class="rounded-3xl bg-surface p-6 shadow-card"
  x-data="{
    range: 'last',
    active: 5,
    series: @js($series),
    months: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    labels: { last: 'Last Year', this: 'This Year' },
    open: false,
    min: 1000, max: 5000,
    get data() { return this.series[this.range] },
    h(v) { return Math.max(((v - this.min) / (this.max - this.min)) * 100, 10) },
    fmt(n) { return Number(n).toLocaleString('en-US') },
  }">

  <div class="flex items-center justify-between">
    <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>

    <div class="relative" @click.outside="open = false">
      <button @click="open = !open" class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:text-ink">
        <span x-text="labels[range]"></span><x-icon name="chevron" class="size-3.5" />
      </button>
      <ul x-show="open" x-cloak x-transition class="absolute right-0 z-10 mt-1 w-28 rounded-xl border border-line bg-surface p-1 text-xs shadow-card">
        <template x-for="(l, k) in labels" :key="k">
          <li>
            <button @click="range = k; open = false" x-text="l"
                    class="w-full rounded-lg px-3 py-2 text-left font-medium transition hover:bg-soft"
                    :class="range === k ? 'text-brand-500 font-semibold' : 'text-muted'"></button>
          </li>
        </template>
      </ul>
    </div>
  </div>

  <div class="mt-4 flex gap-4">
    {{-- Y Axis --}}
    <div class="flex h-56 flex-col justify-between pb-6 text-xs text-muted">
      <template x-for="t in [5000, 3000, 2000, 1000]" :key="t">
        <span x-text="'{{ $currency }}' + fmt(t)"></span>
      </template>
    </div>

    {{-- Interactive Bars --}}
    <div class="relative flex h-56 flex-1 items-end justify-between gap-1.5 sm:gap-3">
      <template x-for="(v, i) in data" :key="i">
        <div class="relative flex h-full flex-1 cursor-pointer flex-col items-center justify-end gap-2"
             @mouseenter="active = i" @focus="active = i" tabindex="0">

          {{-- Tooltip --}}
          <div x-show="active === i" x-transition.opacity
               class="absolute z-10 -translate-y-1 whitespace-nowrap rounded-lg bg-ink px-3 py-1.5 text-xs font-semibold text-surface shadow-md"
               :style="`bottom: calc(${h(v)}% * 0.88 + 36px)`">
            {{ $currency }}<span x-text="fmt(v)"></span>
          </div>

          <div class="w-full max-w-[38px] rounded-full transition-[height,background-color] duration-300"
               :class="active === i ? 'bg-brand-500' : 'bg-soft'"
               :style="`height: calc(${h(v)}% * 0.88)`"></div>
          <span class="text-xs transition" :class="active === i ? 'font-bold text-ink' : 'text-muted'" x-text="months[i]"></span>
        </div>
      </template>
    </div>
  </div>
</section>
