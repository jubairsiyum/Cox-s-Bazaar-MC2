{{--
  Sales vs. Collection Trend Chart Component
  Usage: <x-dashboard.sales-chart />
--}}
@props([
'title' => 'Sales & Collection Trend',
'currency' => '৳',
'periods' => [
'7d' => [
'label' => 'Last 7 Days',
'categories' => ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
'sales' => [125000, 168000, 142000, 195000, 155000, 210000, 184500],
'collections' => [95000, 140000, 110000, 160000, 130000, 175000, 125000],
],
'30d' => [
'label' => 'This Month',
'categories' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
'sales' => [980000, 1240000, 1120000, 1450000],
'collections' => [780000, 1050000, 920000, 1220000],
],
'year' => [
'label' => 'This Year',
'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
'sales' => [3200000, 3600000, 4100000, 3900000, 4400000, 5200000, 4800000, 4600000, 5100000, 5800000, 5400000, 6100000],
'collections' => [2800000, 3100000, 3700000, 3500000, 3900000, 4600000, 4200000, 4100000, 4500000, 5200000, 4900000, 5500000],
],
],
])

<section {{ $attributes->merge(['class' => 'h-full flex flex-col justify-between rounded-3xl bg-surface p-6 shadow-card']) }}
  x-data="{
    period: '7d',
    view: 'both',
    active: null,
    animating: false,
    periods: @js($periods),
    open: false,
    currency: '{{ $currency }}',

    get current() {
      return this.periods[this.period];
    },
    get maxVal() {
      let vals = [];
      if (this.view === 'both') {
        vals = [...this.current.sales, ...this.current.collections];
      } else if (this.view === 'sales') {
        vals = [...this.current.sales];
      } else {
        vals = [...this.current.collections];
      }
      return Math.max(...vals) * 1.15;
    },
    h(val) {
      if (!this.maxVal || this.maxVal <= 0) return 8;
      const pct = (val / this.maxVal) * 100;
      return Math.max(Math.min(Math.round(pct), 100), 8);
    },
    maxHeight(idx) {
      if (idx === null || idx === undefined || !this.current) return 50;
      if (this.view === 'sales') return this.h(this.current.sales[idx]);
      if (this.view === 'collections') return this.h(this.current.collections[idx]);
      return Math.max(this.h(this.current.sales[idx]), this.h(this.current.collections[idx]));
    },
    get tooltipPos() {
      if (this.active === null || !this.current) return 'display: none';
      const total = this.current.categories.length;
      const leftPct = ((this.active + 0.5) / total) * 100;
      const bottomPct = this.maxHeight(this.active);
      return `left: ${leftPct}%; bottom: calc(${bottomPct}% * 0.85 + 28px);`;
    },
    fmt(num) {
      return Number(num || 0).toLocaleString('en-US');
    },
    switchView(v) {
      if (this.view === v) return;
      this.active = null;
      this.animating = true;
      this.view = v;
      setTimeout(() => {
        this.animating = false;
      }, 30);
    },
    switchPeriod(k) {
      if (this.period === k) return;
      this.period = k;
      this.open = false;
      this.active = null;
      this.animating = true;
      setTimeout(() => {
        this.animating = false;
      }, 30);
    }
  }">

  <div>
    {{-- Header with Titles, Interactive Filter & Period Selector --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
        <p class="text-xs text-muted">Revenue invoiced vs. cash & bank collected</p>
      </div>

      <div class="flex items-center gap-3 sm:gap-4">
        {{-- Interactive View Toggles (All / Sales / Collections) --}}
        <div class="flex items-center gap-1 rounded-full border border-line bg-surface p-1 text-xs">
          <button type="button"
            @click="switchView('both')"
            class="rounded-full px-2.5 py-1 font-medium transition"
            :class="view === 'both' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
            Both
          </button>
          <button type="button"
            @click="switchView('sales')"
            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium transition"
            :class="view === 'sales' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
            <span class="size-2 rounded-full bg-brand-500"></span>
            <span>Sales</span>
          </button>
          <button type="button"
            @click="switchView('collections')"
            class="flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium transition"
            :class="view === 'collections' ? 'bg-soft text-success font-bold' : 'text-muted hover:text-ink'">
            <span class="size-2 rounded-full bg-success"></span>
            <span>Collections</span>
          </button>
        </div>

        {{-- Period Dropdown --}}
        <div class="relative" @click.outside="open = false">
          <button @click="open = !open"
            class="flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted transition hover:text-ink">
            <span x-text="current.label"></span>
            <x-icon name="chevron" class="size-3.5" />
          </button>
          <ul x-show="open" x-cloak
            x-transition:enter="transition duration-300 ease-[cubic-bezier(.16,1,.3,1)]"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute right-0 z-40 mt-1 w-32 origin-top-right rounded-2xl border border-line bg-surface p-1 text-xs shadow-card">
            <template x-for="(item, key) in periods" :key="key">
              <li>
                <button @click="switchPeriod(key)"
                  x-text="item.label"
                  class="w-full rounded-xl px-3 py-2 text-left font-medium transition hover:bg-soft"
                  :class="period === key ? 'text-brand-500 font-semibold' : 'text-muted'"></button>
              </li>
            </template>
          </ul>
        </div>
      </div>
    </div>

    {{-- Chart Area --}}
    <div class="mt-6 flex gap-2 sm:gap-4">
      {{-- Y-Axis Grid Reference --}}
      <div class="flex h-56 flex-col justify-between pb-7 text-xs text-muted shrink-0 text-right pr-1 select-none">
        <span x-text="currency + fmt(Math.round(maxVal))"></span>
        <span x-text="currency + fmt(Math.round(maxVal * 0.66))"></span>
        <span x-text="currency + fmt(Math.round(maxVal * 0.33))"></span>
        <span>{{ $currency }} 0</span>
      </div>

      {{-- Interactive Bars Container with single global tooltip --}}
      <div class="relative flex h-56 flex-1 items-end justify-between gap-1 sm:gap-3 border-b border-line pb-2"
        @mouseleave="active = null">

        {{-- Single Floating Tooltip: impossible to get stuck, smoothly glides between bars --}}
        <div x-show="active !== null"
          x-cloak
          x-transition:enter="transition ease-out duration-150"
          x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
          x-transition:enter-end="opacity-100 translate-y-0 scale-100"
          x-transition:leave="transition ease-in duration-200"
          x-transition:leave-start="opacity-100 translate-y-0 scale-100"
          x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
          class="pointer-events-none absolute z-30 -translate-x-1/2 whitespace-nowrap rounded-2xl border border-line bg-surface p-3 text-xs shadow-xl transition-[left,bottom] duration-150 ease-out"
          :style="tooltipPos">
          <template x-if="active !== null && current.categories[active]">
            <div>
              <p class="font-bold text-ink" x-text="current.categories[active]"></p>
              <div x-show="view === 'both' || view === 'sales'" class="mt-1 flex items-center gap-2">
                <span class="size-2 rounded-full bg-brand-500"></span>
                <span class="text-muted">Sales:</span>
                <span class="font-semibold text-ink" x-text="currency + ' ' + fmt(current.sales[active])"></span>
              </div>
              <div x-show="view === 'both' || view === 'collections'" class="mt-0.5 flex items-center gap-2">
                <span class="size-2 rounded-full bg-success"></span>
                <span class="text-muted">Collection:</span>
                <span class="font-semibold text-success" x-text="currency + ' ' + fmt(current.collections[active])"></span>
              </div>
            </div>
          </template>
        </div>

        {{-- Column Bars --}}
        <template x-for="(cat, idx) in current.categories" :key="cat + idx">
          <div class="relative flex h-full flex-1 cursor-pointer flex-col items-center justify-end"
            :style="`--bar-i: ${idx}`"
            @mouseenter="active = idx"
            @focus="active = idx"
            tabindex="0">

            {{-- BARS CONTAINER: Explicit height ensures percentage calculates perfectly in all browsers --}}
            <div class="flex h-[calc(100%-1.75rem)] w-full items-end justify-center gap-1 sm:gap-2 transition-[max-width] duration-300 ease-out"
              :class="view === 'both' ? 'max-w-[46px]' : 'max-w-[38px]'">

              {{-- Sales Bar --}}
              <div x-show="view === 'both' || view === 'sales'"
                class="relative flex h-full w-full flex-col justify-end overflow-hidden rounded-full bg-soft transition-[max-width,background-color] duration-300 ease-out"
                :class="view === 'both' ? 'max-w-[18px]' : 'max-w-[38px]'">
                <div class="bar w-full rounded-full transition-[height,background-color] duration-300 ease-out"
                  :class="active === idx ? 'bg-brand-500 shadow-md ring-2 ring-brand-500/30' : 'bg-brand-500/85'"
                  :style="`height: ${animating ? 0 : h(current.sales[idx])}%`"></div>
              </div>

              {{-- Collections Bar --}}
              <div x-show="view === 'both' || view === 'collections'"
                class="relative flex h-full w-full flex-col justify-end overflow-hidden rounded-full bg-soft transition-[max-width,background-color] duration-300 ease-out"
                :class="view === 'both' ? 'max-w-[18px]' : 'max-w-[38px]'">
                <div class="bar w-full rounded-full transition-[height,background-color] duration-300 ease-out"
                  style="--bar-offset: 40ms;"
                  :class="active === idx ? 'bg-success shadow-md ring-2 ring-success/30' : 'bg-success/85'"
                  :style="`height: ${animating ? 0 : h(current.collections[idx])}%; --bar-offset: 40ms;`"></div>
              </div>
            </div>

            {{-- X-Axis Category Label --}}
            <span class="bar-label mt-2 text-xs truncate transition-colors text-center select-none"
              :class="active === idx ? 'font-bold text-ink' : 'text-muted'"
              x-text="cat"></span>
          </div>
        </template>
      </div>
    </div>
  </div>

  {{-- Subtle Footer note to ensure solid bottom alignment --}}
  <div class="mt-4 pt-3 border-t border-line flex items-center justify-between text-xs text-muted">
    <span>Data refreshed daily at midnight</span>
    <span class="font-medium text-brand-500">Live Forecast Active</span>
  </div>
</section>