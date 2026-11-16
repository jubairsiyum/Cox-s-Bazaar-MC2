{{--
  Brand Market Share Chart Component
  Usage: <x-dashboard.brand-share-chart />
--}}
@props([
  'title' => 'Brand Sales Share',
  'brands' => [
    ['name' => 'Samsung', 'share' => 38, 'amount' => '৳ 32.4L', 'color' => 'bg-brand-500', 'textColor' => 'text-brand-500'],
    ['name' => 'Xiaomi / Redmi', 'share' => 29, 'amount' => '৳ 24.8L', 'color' => 'bg-warning', 'textColor' => 'text-warning'],
    ['name' => 'Vivo', 'share' => 18, 'amount' => '৳ 15.3L', 'color' => 'bg-success', 'textColor' => 'text-success'],
    ['name' => 'Realme', 'share' => 15, 'amount' => '৳ 12.8L', 'color' => 'bg-chart-4', 'textColor' => 'text-chart-4'],
  ],
])

<section class="h-full rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between"
  x-data="{
    active: 0,
    brands: @js($brands),
    points: [24, 38, 30, 48, 42, 60, 52, 68],
    w: 300, h: 100, pad: 6,
    x(i) { return this.pad + i * ((this.w - this.pad * 2) / (this.points.length - 1)) },
    y(v) { const m = 80; return this.h - this.pad - (v / m) * (this.h - this.pad * 2) },
    get path() {
      let d = `M ${this.x(0)} ${this.y(this.points[0])}`;
      for (let i = 1; i < this.points.length; i++) {
        const cx = (this.x(i - 1) + this.x(i)) / 2;
        d += ` C ${cx} ${this.y(this.points[i - 1])}, ${cx} ${this.y(this.points[i])}, ${this.x(i)} ${this.y(this.points[i])}`;
      }
      return d;
    },
    get area() { return this.path + ` L ${this.x(this.points.length - 1)} ${this.h} L ${this.x(0)} ${this.h} Z` },
  }">

  <div>
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
        <p class="mt-0.5 text-xs text-muted">Distribution volume by mobile brand</p>
      </div>
      <span class="rounded-full bg-soft px-3 py-1 text-xs font-semibold text-brand-500">
        This Month
      </span>
    </div>

    {{-- Spline curve illustration --}}
    <div class="relative mt-4">
      <svg :viewBox="`0 0 ${w} ${h}`" class="h-24 w-full overflow-visible" preserveAspectRatio="none">
        <defs>
          <linearGradient id="brand-curve-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="var(--color-brand-500)" stop-opacity=".18"/>
            <stop offset="1" stop-color="var(--color-brand-500)" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path :d="area" fill="url(#brand-curve-fill)"/>
        <path :d="path" fill="none" stroke="var(--color-brand-500)" stroke-width="2.5" vector-effect="non-scaling-stroke"/>
      </svg>
    </div>

    {{-- Brand Share List --}}
    <div class="mt-4 space-y-3">
      <template x-for="(b, idx) in brands" :key="b.name">
        <div class="rounded-2xl border border-line bg-surface p-3 transition hover:bg-soft/40 cursor-pointer"
             @mouseenter="active = idx"
             :class="active === idx ? 'border-brand-500/40 bg-soft/30' : ''">
          <div class="flex items-center justify-between text-xs">
            <span class="font-semibold text-ink flex items-center gap-2">
              <span class="size-2.5 rounded-full" :class="b.color"></span>
              <span x-text="b.name"></span>
            </span>
            <div class="flex items-center gap-2">
              <span class="font-medium text-muted" x-text="b.amount"></span>
              <span class="font-bold text-ink" x-text="b.share + '%'"></span>
            </div>
          </div>
          <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-line">
            <div class="h-full rounded-full transition-all duration-300" 
                 :class="b.color" 
                 :style="`width: ${b.share}%`"></div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <div class="mt-4 pt-3 border-t border-line flex items-center justify-between text-xs text-muted">
    <span>Total 4 Major Brands</span>
    <a href="{{ url('/products') }}" class="font-medium text-brand-500 hover:underline">View Catalog &rarr;</a>
  </div>
</section>
