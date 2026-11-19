{{--
  Brand Sales & Stock Share Chart Component
  Automatically adapts from 0 -> 4 -> 18+ stocked brands:
  - 0 stocked brands: clean empty state with quick actions
  - 1-4 brands: direct series pills + area trend chart
  - 5+ brands: Top 4 + Others (dashed) chart, searchable "+N more" picker popover,
               and 1-click Trend <-> All Brands ranked leaderboard toggle.
--}}
@props([
  'title' => 'Brand Sales Share',
  'subtitle' => null,
  'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  'maxVisible' => 4,
  'totalRegisteredBrands' => null,
  'warehouseName' => 'Central Warehouse',
  'volumeScale' => 'lakh',
  'brands' => [
    [
      'name' => 'Samsung',
      'share' => 38,
      'amount' => '৳ 32.4L',
      'units' => 142,
      'skus' => 18,
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
      'units' => 118,
      'skus' => 15,
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
      'units' => 74,
      'skus' => 10,
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
      'units' => 50,
      'skus' => 8,
      'colorHex' => '#8b5cf6',
      'bgClass' => 'bg-[#8b5cf6]',
      'textClass' => 'text-[#8b5cf6]',
      'shareData' => [18, 17, 16, 17, 17, 14, 15, 13, 15, 15, 14, 15],
      'volumeData' => [15.1, 14.5, 13.7, 14.6, 14.6, 12.0, 12.8, 11.1, 12.8, 12.8, 12.0, 12.8],
    ],
  ],
])

@php
  $brandList = collect($brands)->values();
  $stockedCount = $brandList->count();
  $registeredCount = $totalRegisteredBrands !== null ? max((int) $totalRegisteredBrands, $stockedCount) : $stockedCount;
  $topBrands = $brandList->take($maxVisible)->values();
  $otherBrands = $brandList->slice($maxVisible)->values();
  $hasOthers = $otherBrands->isNotEmpty();
  $othersShare = round((float) $otherBrands->sum('share'), 1);
  $othersUnits = (int) $otherBrands->sum('units');
  $topShareSum = round((float) $topBrands->sum('share'), 1);
  $maxShareVal = max(1.0, (float) ($brandList->max('share') ?? 1));

  $topGridClass = match ($topBrands->count()) {
      1 => 'grid-cols-1',
      2 => 'grid-cols-2',
      3 => 'grid-cols-2 sm:grid-cols-3',
      default => 'grid-cols-2 sm:grid-cols-4',
  };

  $resolvedSubtitle = $subtitle ?? ($stockedCount > 0
      ? ($registeredCount > $stockedCount
          ? "{$stockedCount} stocked of {$registeredCount} brands · Ranked by value"
          : ($hasOthers
              ? "Top {$maxVisible} + {$otherBrands->count()} others · {$stockedCount} active brands"
              : "Distribution volume & share across {$stockedCount} active " . \Illuminate\Support\Str::plural('brand', $stockedCount)))
      : 'Stock products to track live brand distribution share');

  $chartConfig = [
      'categories' => $categories,
      'defaultMode' => 'share',
      'maxVisible' => $maxVisible,
      'volumeScale' => $volumeScale,
      'brands' => $brandList->all(),
  ];

  $topNames = $topBrands->pluck('name')->all();
@endphp

<section {{ $attributes->merge(['class' => 'h-full rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between']) }}
  x-data="{
    view: 'chart',
    mode: 'share',
    selectedBrand: 'all',
    pickerOpen: false,
    pickerQuery: '',
    listQuery: '',
    brands: @js($brandList->all()),
    topNames: @js($topNames),
    hasOthers: @js($hasOthers),
    othersCount: {{ $otherBrands->count() }},
    othersShare: {{ $othersShare }},

    get isAllSelected() {
      return this.selectedBrand === 'all';
    },

    get isExtraBrandSelected() {
      return this.selectedBrand !== 'all'
        && this.selectedBrand !== '__others__'
        && !this.topNames.includes(this.selectedBrand);
    },

    get selectedBrandMeta() {
      if (this.selectedBrand === 'all' || this.selectedBrand === '__others__') return null;
      return this.brands.find(b => b.name === this.selectedBrand) || null;
    },

    isBrandActive(name) {
      return this.selectedBrand === 'all' || this.selectedBrand === name;
    },

    matchesQuery(name, q) {
      const term = (q || '').trim().toLowerCase();
      if (!term) return true;
      return String(name || '').toLowerCase().includes(term);
    },

    filteredCount(q, sliceFrom = 0) {
      const pool = sliceFrom > 0 ? this.brands.slice(sliceFrom) : this.brands;
      const term = (q || '').trim().toLowerCase();
      if (!term) return pool.length;
      return pool.filter(b => String(b.name || '').toLowerCase().includes(term)).length;
    },

    selectBrand(name, switchToChart = false) {
      if (this.selectedBrand === name && !switchToChart) {
        this.selectedBrand = 'all';
      } else {
        this.selectedBrand = name;
      }
      this.pickerOpen = false;
      this.pickerQuery = '';
      if (switchToChart && this.view !== 'chart') {
        this.view = 'chart';
        this.$nextTick(() => this.updateChart());
      } else {
        this.updateChart();
      }
    },

    selectOthers() {
      this.selectedBrand = this.selectedBrand === '__others__' ? 'all' : '__others__';
      this.pickerOpen = false;
      this.updateChart();
    },

    selectAll() {
      this.selectedBrand = 'all';
      this.pickerOpen = false;
      this.updateChart();
    },

    switchView(nextView) {
      this.view = nextView;
      this.pickerOpen = false;
      if (nextView === 'chart') {
        this.$nextTick(() => this.updateChart());
      }
    },

    switchMode(newMode) {
      this.mode = newMode;
      if (this.view === 'chart') {
        this.updateChart();
      }
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
      if (this.selectedBrand === 'all' && this.view === 'chart') {
        this.$refs.chart?.dispatchEvent(new CustomEvent('highlight-brand', {
          detail: { brandName: name }
        }));
      }
    },

    unhoverBrand() {
      if (this.selectedBrand === 'all' && this.view === 'chart') {
        this.$refs.chart?.dispatchEvent(new CustomEvent('unhighlight-brand'));
      }
    }
  }">

  <div class="flex-1 flex flex-col">
    {{-- Card Header: Title + View Switcher (Trend / All N) + Metric Toggle (Share % / Volume ৳) --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <span class="grid size-9 shrink-0 place-items-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
          <x-icon name="trend-up" class="size-4.5" />
        </span>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-base sm:text-lg font-semibold text-ink truncate">{{ $title }}</h3>
            @if ($stockedCount > 0)
              <span class="inline-flex items-center rounded-full bg-soft px-2 py-0.5 text-[10px] font-bold text-brand-500 tabular-nums">
                {{ $stockedCount }} {{ \Illuminate\Support\Str::plural('brand', $stockedCount) }}
              </span>
            @endif
          </div>
          <p class="text-xs text-muted truncate">{{ $resolvedSubtitle }}</p>
        </div>
      </div>

      @if ($stockedCount > 0)
        <div class="flex items-center gap-2 flex-wrap">
          {{-- Trend vs Ranked All-Brands Toggle (especially powerful with 5-18+ brands) --}}
          @if ($stockedCount > 1)
            <div class="flex items-center gap-1 rounded-full border border-line bg-surface p-1 text-xs">
              <button type="button"
                @click="switchView('chart')"
                class="rounded-full px-2.5 py-1 font-medium transition cursor-pointer"
                :class="view === 'chart' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
                Trend
              </button>
              <button type="button"
                @click="switchView('list')"
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium transition cursor-pointer"
                :class="view === 'list' ? 'bg-soft text-brand-500 font-bold' : 'text-muted hover:text-ink'">
                <span>All</span>
                <span class="rounded-full px-1.5 text-[10px] tabular-nums"
                  :class="view === 'list' ? 'bg-brand-500/15 text-brand-500' : 'bg-soft text-muted'">
                  {{ $stockedCount }}
                </span>
              </button>
            </div>
          @endif

          {{-- Share % vs Volume ৳ Toggle --}}
          <div x-show="view === 'chart'" class="flex items-center gap-1 rounded-full border border-line bg-surface p-1 text-xs">
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
      @endif
    </div>

    @if ($stockedCount === 0)
      {{-- Clean Empty State When No Brands Are Stocked Yet --}}
      <div class="my-auto flex flex-col items-center justify-center py-12 text-center">
        <div class="flex size-14 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
          <x-icon name="box" class="size-7" />
        </div>
        <h4 class="text-sm font-bold text-ink">No stocked brands yet</h4>
        <p class="mt-1 max-w-xs text-xs text-muted leading-relaxed">
          @if ($registeredCount > 0)
            You have <strong class="text-ink">{{ $registeredCount }}</strong> registered {{ \Illuminate\Support\Str::plural('brand', $registeredCount) }}, but none have active stock yet. Receive a purchase order or add units to see live brand share.
          @else
            Register brands and add stock to track distribution share across your catalog.
          @endif
        </p>
        <div class="mt-4 flex items-center gap-2.5 flex-wrap justify-center">
          @if (\Illuminate\Support\Facades\Route::has('purchase-orders.create'))
            <a href="{{ route('purchase-orders.create') }}"
               class="btn-scale inline-flex items-center gap-1.5 rounded-full bg-brand-500 px-3.5 py-2 text-xs font-semibold text-on-brand shadow-xs hover:bg-brand-600 transition-colors">
              <x-icon name="plus" class="size-3.5" />
              <span>New Purchase</span>
            </a>
          @endif
          <a href="{{ route('brands.index') }}"
             class="btn-scale inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors">
            <span>Manage Brands</span>
          </a>
        </div>
      </div>
    @else
      {{-- =================================================================
           VIEW 1: TREND CHART (Fixed-Height Top 4 Strip + Searchable Picker)
      ================================================================= --}}
      <div x-show="view === 'chart'" class="flex-1 flex flex-col">
        {{-- Top 4 Brand Pills (Always 1 neat row even with 18+ brands) --}}
        <div class="mt-4 grid {{ $topGridClass }} gap-2.5">
          @foreach ($topBrands as $b)
            <button type="button"
              @click="selectBrand(@js($b['name']))"
              @mouseenter="hoverBrand(@js($b['name']))"
              @mouseleave="unhoverBrand()"
              title="{{ $b['name'] }} — {{ $b['amount'] }} ({{ $b['share'] }}%){{ isset($b['units']) ? ' · ' . number_format((int) $b['units']) . ' units' : '' }}"
              class="flex items-center justify-between rounded-xl border p-2.5 text-left transition cursor-pointer min-w-0"
              :class="isBrandActive(@js($b['name']))
                        ? (selectedBrand === @js($b['name'])
                            ? 'border-brand-500/60 bg-soft/40 shadow-xs ring-1 ring-brand-500/30 opacity-100'
                            : 'border-line bg-surface hover:bg-soft/40 opacity-100 ring-1 ring-line/70')
                        : 'border-line/60 bg-surface/50 opacity-40 hover:opacity-75 grayscale'">
              <div class="flex items-center gap-2 min-w-0">
                <x-brand-logo :name="$b['name']" size="xs" :showContainer="true" containerClass="!size-6 !rounded-md !p-0.5" />
                <div class="min-w-0">
                  <p class="truncate text-xs font-semibold text-ink">{{ $b['name'] }}</p>
                  <p class="truncate text-[11px] text-muted tabular-nums">{{ $b['amount'] }}</p>
                </div>
              </div>
              <span class="ml-1.5 shrink-0 text-xs font-bold tabular-nums" style="color: {{ $b['colorHex'] }}">
                {{ rtrim(rtrim(number_format((float) $b['share'], 1, '.', ''), '0'), '.') }}%
              </span>
            </button>
          @endforeach
        </div>

        {{-- Compact Filter & "+N More Brands" Searchable Picker Strip --}}
        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 flex-wrap">
            {{-- Reset to Top 4 (+ Others) --}}
            <button type="button"
              @click="selectAll()"
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition cursor-pointer"
              :class="isAllSelected
                ? 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20'
                : 'border border-line bg-surface text-muted hover:text-ink hover:bg-soft/60'">
              <span>{{ $hasOthers ? "Top {$maxVisible} + Others" : 'All Brands' }}</span>
            </button>

            {{-- Combined Others Series Quick Chip (when > 4 brands) --}}
            @if ($hasOthers)
              <button type="button"
                @click="selectOthers()"
                @mouseenter="hoverBrand('Others ({{ $otherBrands->count() }})')"
                @mouseleave="unhoverBrand()"
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition cursor-pointer"
                :class="selectedBrand === '__others__'
                  ? 'bg-soft text-ink ring-1 ring-brand-500/40'
                  : 'border border-line bg-surface text-muted hover:text-ink hover:bg-soft/60'">
                <span class="size-2 rounded-full bg-[#94a3b8]"></span>
                <span>Others ({{ $otherBrands->count() }})</span>
                <span class="font-bold text-ink tabular-nums">{{ rtrim(rtrim(number_format($othersShare, 1, '.', ''), '0'), '.') }}%</span>
              </button>
            @endif

            {{-- Active Focused Extra Brand Chip (when a brand #5..#18+ is selected) --}}
            <template x-if="isExtraBrandSelected && selectedBrandMeta">
              <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-2.5 py-1 text-[11px] font-semibold text-brand-500 ring-1 ring-brand-500/25">
                <span class="size-2 rounded-full shrink-0" :style="'background-color:' + selectedBrandMeta.colorHex"></span>
                <span x-text="selectedBrandMeta.name"></span>
                <span class="tabular-nums opacity-80" x-text="selectedBrandMeta.share + '%'"></span>
                <button type="button" @click="selectAll()" class="ml-0.5 rounded-full hover:bg-brand-500/20 p-0.5 cursor-pointer" title="Clear focus">
                  <x-svg.x class="size-2.5" />
                </button>
              </span>
            </template>
          </div>

          {{-- Searchable "+N more" Popover Picker --}}
          @if ($hasOthers)
            <div class="relative" @click.outside="pickerOpen = false" @keydown.escape.window="pickerOpen = false">
              <button type="button"
                @click="pickerOpen = !pickerOpen; if (pickerOpen) { $nextTick(() => $refs.pickerSearch?.focus()); }"
                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1 text-[11px] font-semibold text-ink shadow-2xs hover:border-brand-500/50 hover:text-brand-500 hover:bg-soft/60 transition cursor-pointer">
                <span>+{{ $otherBrands->count() }} more</span>
                <x-icon name="chevron" class="size-3 transition-transform duration-150" ::class="pickerOpen && 'rotate-180 text-brand-500'" />
              </button>

              <div x-show="pickerOpen" x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 z-50 mt-2 w-72 rounded-2xl border border-line bg-surface p-2 shadow-card">
                {{-- Popover Search Input --}}
                <div class="relative mb-2">
                  <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-muted">
                    <x-icon name="search" class="size-3.5" />
                  </span>
                  <input type="text"
                    x-ref="pickerSearch"
                    x-model="pickerQuery"
                    placeholder="Search {{ $otherBrands->count() }} other brands..."
                    class="w-full rounded-xl border border-line bg-canvas/60 pl-8 pr-3 py-1.5 text-xs text-ink placeholder:text-muted focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                </div>

                {{-- Remaining Brands List --}}
                <div class="max-h-56 overflow-y-auto custom-scrollbar space-y-0.5 pr-0.5">
                  @foreach ($otherBrands as $idx => $ob)
                    <button type="button"
                      x-show="matchesQuery(@js($ob['name']), pickerQuery)"
                      @click="selectBrand(@js($ob['name']))"
                      class="w-full flex items-center justify-between gap-2 rounded-xl px-2.5 py-2 text-left transition cursor-pointer"
                      :class="selectedBrand === @js($ob['name']) ? 'bg-soft text-brand-500' : 'hover:bg-soft/60 text-ink'">
                      <div class="flex items-center gap-2 min-w-0">
                        <span class="text-[10px] font-bold text-muted w-5 tabular-nums">#{{ $maxVisible + $idx + 1 }}</span>
                        <x-brand-logo :name="$ob['name']" size="xs" :showContainer="true" containerClass="!size-6 !rounded-md !p-0.5" />
                        <div class="min-w-0">
                          <p class="truncate text-xs font-semibold">{{ $ob['name'] }}</p>
                          <p class="text-[10px] text-muted tabular-nums">
                            {{ $ob['amount'] }}@if(isset($ob['units'])) · {{ number_format((int) $ob['units']) }} pcs @endif
                          </p>
                        </div>
                      </div>
                      <span class="shrink-0 text-xs font-bold tabular-nums" style="color: {{ $ob['colorHex'] }}">
                        {{ rtrim(rtrim(number_format((float) $ob['share'], 1, '.', ''), '0'), '.') }}%
                      </span>
                    </button>
                  @endforeach

                  <div x-show="filteredCount(pickerQuery, {{ $maxVisible }}) === 0" x-cloak class="py-5 text-center text-xs text-muted">
                    No matching brand found.
                  </div>
                </div>

                {{-- Popover Footer: Jump to Full Leaderboard --}}
                <div class="mt-2 border-t border-line pt-1.5 flex items-center justify-between px-1">
                  <span class="text-[10px] text-muted tabular-nums">{{ number_format($othersUnits) }} units in Others</span>
                  <button type="button"
                    @click="switchView('list')"
                    class="text-[11px] font-semibold text-brand-500 hover:underline cursor-pointer">
                    View all {{ $stockedCount }} &rarr;
                  </button>
                </div>
              </div>
            </div>
          @endif
        </div>

        {{-- ApexCharts Area Chart --}}
        <div class="mt-3 custom-scrollbar max-w-full overflow-x-auto flex-1">
          <div class="min-w-[400px] sm:min-w-full">
            <div x-ref="chart" data-dashboard-chart="brand-sales-share" data-config='@json($chartConfig)'></div>
          </div>
        </div>
      </div>

      {{-- =================================================================
           VIEW 2: ALL STOCKED BRANDS LEADERBOARD (Scales effortlessly to 18-50+ brands)
      ================================================================= --}}
      <div x-show="view === 'list'" x-cloak class="mt-4 flex-1 flex flex-col">
        {{-- Compact Search + Top-4 Share Summary --}}
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div class="relative flex-1 min-w-[180px]">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted">
              <x-icon name="search" class="size-3.5" />
            </span>
            <input type="text"
              x-model="listQuery"
              placeholder="Filter {{ $stockedCount }} stocked brands..."
              class="w-full rounded-xl border border-line bg-canvas/50 pl-8 pr-8 py-1.5 text-xs text-ink placeholder:text-muted focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            <button type="button"
              x-show="listQuery.length > 0"
              @click="listQuery = ''"
              class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-muted hover:text-ink cursor-pointer">
              <x-svg.x class="size-3.5" />
            </button>
          </div>

          <span class="rounded-full bg-soft px-2.5 py-1 text-[11px] font-semibold text-muted tabular-nums">
            Top {{ min($maxVisible, $stockedCount) }} hold <strong class="text-ink">{{ rtrim(rtrim(number_format($topShareSum, 1, '.', ''), '0'), '.') }}%</strong>
          </span>
        </div>

        {{-- Scrollable Ranked Brand Breakdown --}}
        <div class="max-h-[300px] overflow-y-auto custom-scrollbar space-y-2 pr-1">
          @foreach ($brandList as $idx => $b)
            @php
              $rank = $idx + 1;
              $barPct = max(4, min(100, (int) round(((float) $b['share'] / $maxShareVal) * 100)));
            @endphp
            <button type="button"
              x-show="matchesQuery(@js($b['name']), listQuery)"
              @click="selectBrand(@js($b['name']), true)"
              class="group w-full rounded-2xl border border-line bg-surface p-3 text-left transition hover:border-brand-500/40 hover:bg-soft/40 cursor-pointer">
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <span class="flex size-6 shrink-0 items-center justify-center rounded-lg text-[11px] font-bold tabular-nums {{ $rank <= 3 ? 'bg-brand-500/10 text-brand-500' : 'bg-soft text-muted' }}">
                    {{ $rank }}
                  </span>
                  <x-brand-logo :name="$b['name']" size="xs" :showContainer="true" containerClass="!size-7 !rounded-lg !p-1" />
                  <div class="min-w-0">
                    <p class="truncate text-xs font-bold text-ink group-hover:text-brand-500 transition-colors">
                      {{ $b['name'] }}
                    </p>
                    <p class="text-[11px] text-muted tabular-nums">
                      @if(isset($b['units']))
                        {{ number_format((int) $b['units']) }} {{ \Illuminate\Support\Str::plural('unit', (int) $b['units']) }}
                      @endif
                      @if(isset($b['skus']))
                        · {{ number_format((int) $b['skus']) }} {{ \Illuminate\Support\Str::plural('SKU', (int) $b['skus']) }}
                      @endif
                    </p>
                  </div>
                </div>

                <div class="text-right shrink-0">
                  <p class="text-xs font-bold text-ink tabular-nums">{{ $b['amount'] }}</p>
                  <p class="text-[11px] font-bold tabular-nums" style="color: {{ $b['colorHex'] }}">
                    {{ rtrim(rtrim(number_format((float) $b['share'], 1, '.', ''), '0'), '.') }}%
                  </p>
                </div>
              </div>

              {{-- Visual Share Progress Bar --}}
              <div class="mt-2.5 flex items-center gap-2">
                <div class="h-1.5 flex-1 rounded-full bg-soft overflow-hidden">
                  <div class="h-full rounded-full transition-all duration-500"
                    style="width: {{ $barPct }}%; background-color: {{ $b['colorHex'] }};"></div>
                </div>
                <span class="text-[10px] font-semibold text-muted opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                  View trend &rarr;
                </span>
              </div>
            </button>
          @endforeach

          <div x-show="filteredCount(listQuery, 0) === 0" x-cloak class="py-8 text-center text-xs text-muted">
            No stocked brands match your search.
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- Card Footer --}}
  <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-4 text-xs">
    <span class="text-muted truncate">
      @if ($stockedCount === 0)
        {{ $registeredCount }} Registered {{ \Illuminate\Support\Str::plural('Brand', $registeredCount) }} &bull; {{ $warehouseName }}
      @elseif ($registeredCount > $stockedCount)
        {{ $stockedCount }} Stocked of {{ $registeredCount }} Brands &bull; {{ $warehouseName }}
      @elseif ($hasOthers)
        Top {{ $maxVisible }} + {{ $otherBrands->count() }} Others ({{ $stockedCount }} Total) &bull; {{ $warehouseName }}
      @else
        {{ $stockedCount }} Active {{ \Illuminate\Support\Str::plural('Brand', $stockedCount) }} &bull; {{ $warehouseName }}
      @endif
    </span>
    <a href="{{ route('brands.index') }}" class="font-semibold text-brand-500 hover:underline shrink-0">
      All Brands &rarr;
    </a>
  </div>
</section>
