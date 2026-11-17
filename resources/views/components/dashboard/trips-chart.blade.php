{{--
  Trips by Year Chart Component
  Usage: <x-dashboard.trips-chart :points="[8, 14, 52, 28, 12, 18, 44, 92]" />
--}}
@props([
  'title' => 'Trips by Year',
  'points' => [8, 14, 52, 28, 12, 18, 44, 92],
  'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
  'initial' => 3,
])

<section class="rounded-3xl bg-surface p-6 shadow-card"
  x-data="{
    pts: @js($points), labels: @js($labels), active: {{ $initial }},
    w: 300, h: 110, pad: 6, open: false, range: 'Last Year',
    x(i) { return this.pad + i * ((this.w - this.pad * 2) / (this.pts.length - 1)) },
    y(v) { const m = Math.max(...this.pts); return this.h - this.pad - (v / m) * (this.h - this.pad * 2) },
    get path() {
      // Smooth cubic bezier spline
      let d = `M ${this.x(0)} ${this.y(this.pts[0])}`;
      for (let i = 1; i < this.pts.length; i++) {
        const cx = (this.x(i - 1) + this.x(i)) / 2;
        d += ` C ${cx} ${this.y(this.pts[i - 1])}, ${cx} ${this.y(this.pts[i])}, ${this.x(i)} ${this.y(this.pts[i])}`;
      }
      return d;
    },
    get area() { return this.path + ` L ${this.x(this.pts.length - 1)} ${this.h} L ${this.x(0)} ${this.h} Z` },
  }">

  <div class="flex items-center justify-between">
    <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>
    <div class="relative" @click.outside="open = false">
      <button @click="open = !open" class="flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:text-ink">
        <span x-text="range"></span><x-icon name="chevron" class="size-3.5" />
      </button>
      <ul x-show="open" x-cloak x-transition class="absolute right-0 z-10 mt-1 w-28 rounded-xl border border-line bg-surface p-1 text-xs shadow-card">
        @foreach (['Last Year', 'This Year'] as $r)
          <li>
            <button @click="range = '{{ $r }}'; open = false"
                    class="w-full rounded-lg px-3 py-2 text-left font-medium transition hover:bg-soft"
                    :class="range === '{{ $r }}' ? 'text-brand-500 font-semibold' : 'text-muted'">{{ $r }}</button>
          </li>
        @endforeach
      </ul>
    </div>
  </div>

  <div class="relative mt-6">
    <svg :viewBox="`0 0 ${w} ${h}`" class="wipe h-32 w-full overflow-visible" preserveAspectRatio="none">
      <defs>
        <linearGradient id="trips-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="var(--color-brand-500)" stop-opacity=".18"/>
          <stop offset="1" stop-color="var(--color-brand-500)" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <path :d="area" fill="url(#trips-fill)"/>
      <path :d="path" fill="none" stroke="var(--color-brand-500)" stroke-width="2" vector-effect="non-scaling-stroke"/>
      <line :x1="x(active)" :x2="x(active)" :y1="y(pts[active])" :y2="h" stroke="var(--color-brand-500)" stroke-opacity=".3" stroke-dasharray="3 3"/>
    </svg>

    {{-- Interactive marker dot + pill badge --}}
    <div class="pointer-events-none absolute inset-0">
      <div class="dot absolute -translate-x-1/2 -translate-y-1/2 transition-[left,top] duration-200" :style="`left:${x(active)/w*100}%; top:${y(pts[active])/h*100}%`">
        <span class="block size-2.5 rounded-full bg-brand-500 ring-4 ring-surface shadow-sm"></span>
        <span class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-soft px-2 py-0.5 text-[10px] font-semibold text-brand-500 shadow-xs" x-text="pts[active] + '%'"></span>
      </div>
    </div>
  </div>

  <div class="mt-2 flex justify-between">
    <template x-for="(l, i) in labels" :key="i">
      <button @click="active = i" @mouseenter="active = i" class="text-[10px] transition"
              :class="active === i ? 'font-bold text-ink' : 'text-muted'" x-text="l"></button>
    </template>
  </div>
</section>
