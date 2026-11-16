{{--
  Low Stock & Critical Reorder Alerts Component
  Usage: <x-dashboard.stock-alerts />
--}}
@props([
  'title' => 'Low Stock & Reorder Alerts',
  'items' => [
    [
      'sku' => 'PRD-0042',
      'brand' => 'Samsung',
      'model' => 'Galaxy A15 (6GB/128GB)',
      'color' => 'Light Blue',
      'stock' => 3,
      'min' => 10,
      'status' => 'critical',
    ],
    [
      'sku' => 'PRD-0089',
      'brand' => 'Xiaomi',
      'model' => 'Redmi Note 13 (8GB/256GB)',
      'color' => 'Midnight Black',
      'stock' => 2,
      'min' => 8,
      'status' => 'critical',
    ],
    [
      'sku' => 'PRD-0114',
      'brand' => 'Vivo',
      'model' => 'Y28 4G (4GB/128GB)',
      'color' => 'Gleaming Orange',
      'stock' => 4,
      'min' => 12,
      'status' => 'low',
    ],
    [
      'sku' => 'PRD-0156',
      'brand' => 'Realme',
      'model' => 'C65 (6GB/128GB)',
      'color' => 'Starlight Purple',
      'stock' => 5,
      'min' => 15,
      'status' => 'low',
    ],
  ],
])

<section class="h-full rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between">
  <div>
    {{-- Header --}}
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="grid size-9 place-items-center rounded-2xl bg-danger/10 text-danger">
          <x-icon name="alert-triangle" class="size-4.5" />
        </span>
        <div>
          <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
          <p class="text-xs text-muted">Handsets below safe reorder thresholds</p>
        </div>
      </div>
      <span class="rounded-full bg-danger/10 px-3 py-1 text-xs font-bold text-danger">
        {{ count($items) }} Items Alert
      </span>
    </div>

    {{-- Alert List --}}
    <div class="mt-5 space-y-3">
      @foreach ($items as $item)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface p-3.5 transition hover:border-line/80 hover:bg-soft/20">
          <div class="flex items-center gap-3 min-w-0">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-soft text-brand-500 font-bold text-xs">
              {{ substr($item['brand'], 0, 2) }}
            </span>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <p class="truncate text-sm font-bold text-ink">{{ $item['brand'] }} {{ $item['model'] }}</p>
                <span class="text-[10px] text-muted">{{ $item['sku'] }}</span>
              </div>
              <p class="text-xs text-muted">
                Color: {{ $item['color'] }} &bull; Min required: <strong class="text-ink">{{ $item['min'] }} pcs</strong>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-4 shrink-0">
            <div class="text-right">
              <p class="text-sm font-extrabold {{ $item['status'] === 'critical' ? 'text-danger' : 'text-warning' }}">
                {{ $item['stock'] }} left
              </p>
              <span class="inline-block rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $item['status'] === 'critical' ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning' }}">
                {{ $item['status'] === 'critical' ? 'Critical' : 'Low' }}
              </span>
            </div>

            <a href="{{ url('/products') }}" 
               class="rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink transition hover:bg-soft hover:text-brand-500 shadow-2xs">
              Reorder
            </a>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Card Footer --}}
  <div class="mt-5 flex items-center justify-between border-t border-line pt-4 text-xs">
    <span class="text-muted">Automatic supplier notification enabled</span>
    <a href="{{ url('/products') }}" class="font-semibold text-brand-500 hover:underline">
      View All Inventory &rarr;
    </a>
  </div>
</section>
