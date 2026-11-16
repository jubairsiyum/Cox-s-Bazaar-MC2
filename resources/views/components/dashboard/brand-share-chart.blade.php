{{--
  Brand Market Share Chart Component (TailAdmin Line Chart 1 Style)
  Usage: <x-dashboard.brand-share-chart />
--}}
@props([
'title' => 'Brand Sales Share',
'subtitle' => 'Distribution volume & market share by mobile brand',
'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
'brands' => [
[
'name' => 'Samsung',
'share' => 38,
'amount' => '৳ 32.4L',
'colorHex' => '#465fff',
'bgClass' => 'bg-brand-500',
'textClass' => 'text-brand-500',
'shareData' => [34, 35, 36, 35, 37, 39, 38, 41, 39, 38, 40, 38],
'volumeData' => [28.5, 29.8, 31.0, 30.2, 31.8, 33.5, 32.4, 35.0, 33.2, 32.8, 34.1, 32.4],
],
[
'name' => 'Xiaomi / Redmi',
'share' => 29,
'amount' => '৳ 24.8L',
'colorHex' => '#f59e0b',
'bgClass' => 'bg-warning',
'textClass' => 'text-warning',
'shareData' => [25, 27, 26, 28, 27, 29, 30, 28, 29, 31, 28, 29],
'volumeData' => [21.0, 23.0, 22.4, 24.1, 23.2, 24.9, 25.6, 23.9, 24.8, 26.5, 23.9, 24.8],
],
[
'name' => 'Vivo',
'share' => 18,
'amount' => '৳ 15.3L',
'colorHex' => '#10b981',
'bgClass' => 'bg-success',
'textClass' => 'text-success',
'shareData' => [23, 21, 22, 20, 19, 18, 17, 18, 17, 16, 18, 18],
'volumeData' => [19.3, 17.9, 18.9, 17.2, 16.3, 15.5, 14.5, 15.4, 14.5, 13.7, 15.4, 15.3],
],
[
'name' => 'Realme',
'share' => 15,
'amount' => '৳ 12.8L',
'colorHex' => '#8b5cf6',
'bgClass' => 'bg-[#8b5cf6]',
'textClass' => 'text-[#8b5cf6]',
'shareData' => [18, 17, 16, 17, 17, 14, 15, 13, 15, 15, 14, 15],
'volumeData' => [15.1, 14.5, 13.7, 14.6, 14.6, 12.0, 12.8, 11.1, 12.8, 12.8, 12.0, 12.8],
],
],
])

@php
$chartConfig = [
'categories' => $categories,
'defaultMode' => 'share',
'brands' => $brands,
];
@endphp

<section class="h-full rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between"
  x-data="{
    mode: 'share',
    selectedBrand: 'all',
    brands: @js($brands),

    get isAllSelected() {
      return this.selectedBrand === 'all';
    },

    isBrandActive(name) {
      return this.selectedBrand === 'all' || this.selectedBrand === name;
    },

    selectBrand(name) {
      if (this.selectedBrand === name) {
        this.selectedBrand = 'all';
      } else {
        this.selectedBrand = name;
      }
      this.updateChart();
    },

    selectAll() {
      this.selectedBrand = 'all';
      this.updateChart();
    },

    switchMode(newMode) {
      this.mode = newMode;
      this.updateChart();
    },

    updateChart() {
      this.$refs.chart?.dispatchEvent(new CustomEvent('chart-update', {
        detail: {
          mode: this.mode,
          selectedBrand: this.selectedBrand,
        }
      }));
    },

    hoverBrand(name) {
      if (this.selectedBrand === 'all') {
        this.$refs.chart?.dispatchEvent(new CustomEvent('highlight-brand', {
          detail: { brandName: name }
        }));
      }
    },

    unhoverBrand() {
      if (this.selectedBrand === 'all') {
        this.$refs.chart?.dispatchEvent(new CustomEvent('unhighlight-brand'));
      }
    }
  }">

  <div>
    {{-- Card Header: Title + All Brands + Mode Toggle (Share % vs Volume ৳) --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <span class="grid size-9 place-items-center rounded-2xl bg-brand-500/10 text-brand-500">
          <x-icon name="trend-up" class="size-4.5" />
        </span>
        <div>
          <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
          <p class="text-xs text-muted">{{ $subtitle }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button type="button"
          @click="selectAll()"
          class="rounded-full px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
          :class="isAllSelected ? 'bg-soft text-brand-500' : 'text-muted hover:text-ink hover:bg-soft/50 border border-line'">
          All Brands
        </button>

        <div class="flex items-center gap-1 rounded-full border border-line bg-surface p-1 text-xs">
          <button type="button"
            @click="switchMode('share')"
            class="rounded-full px-2.5 py-1 font-medium transition cursor-pointer"
            :class="mode === 'share' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
            Share %
          </button>
          <button type="button"
            @click="switchMode('volume')"
            class="rounded-full px-2.5 py-1 font-medium transition cursor-pointer"
            :class="mode === 'volume' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
            Volume (৳)
          </button>
        </div>
      </div>
    </div>

    {{-- TailAdmin Line Chart 1 Style Legend Pills / Tabs --}}
    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2.5">
      @foreach ($brands as $b)
      <button type="button"
        @click="selectBrand('{{ $b['name'] }}')"
        @mouseenter="hoverBrand('{{ $b['name'] }}')"
        @mouseleave="unhoverBrand()"
        class="flex items-center justify-between rounded-xl border p-2.5 text-left transition cursor-pointer"
        :class="isBrandActive('{{ $b['name'] }}')
                  ? (selectedBrand === '{{ $b['name'] }}'
                      ? 'border-brand-500/60 bg-soft/40 shadow-xs ring-1 ring-brand-500/30 opacity-100'
                      : 'border-line bg-surface hover:bg-soft/40 opacity-100 ring-1 ring-line/70')
                  : 'border-line/60 bg-surface/50 opacity-40 hover:opacity-75 grayscale'">
        <div class="flex items-center gap-2 min-w-0">
          <span class="size-2.5 shrink-0 rounded-full {{ $b['bgClass'] }}" style="background-color: {{ $b['colorHex'] }}"></span>
          <div class="truncate">
            <p class="truncate text-xs font-semibold text-ink">{{ $b['name'] }}</p>
            <p class="text-[11px] text-muted">{{ $b['amount'] }}</p>
          </div>
        </div>
        <span class="ml-1 text-xs font-bold {{ $b['textClass'] }}" style="color: {{ $b['colorHex'] }}">
          {{ $b['share'] }}%
        </span>
      </button>
      @endforeach
    </div>

    {{-- Line Chart Area --}}
    <div class="mt-4 custom-scrollbar max-w-full overflow-x-auto">
      <div class="min-w-[420px] sm:min-w-full">
        <div x-ref="chart" data-dashboard-chart="brand-sales-share" data-config='@json($chartConfig)'></div>
      </div>
    </div>
  </div>

  {{-- Card Footer --}}
  <div class="mt-4 flex items-center justify-between border-t border-line pt-4 text-xs">
    <span class="text-muted">Total 4 Major Brands &bull; Central Warehouse Dispatch</span>
    <a href="{{ url('/products') }}" class="font-semibold text-brand-500 hover:underline">
      View Catalog &rarr;
    </a>
  </div>
</section>