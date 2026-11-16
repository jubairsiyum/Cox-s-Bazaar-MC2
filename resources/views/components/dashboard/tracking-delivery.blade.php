{{--
  Tracking Delivery Component
  Usage: <x-dashboard.tracking-delivery orderId="FR156KL89K" status="Checking" :timeline="[...]" />
--}}
@props([
  'title' => 'Tracking Delivery',
  'orderId' => 'FR156KL89K',
  'status' => 'Checking',
  'timeline' => [
    ['label' => 'Total Distance', 'value' => '868', 'unit' => 'km',    'time' => '11:23 AM', 'done' => true],
    ['label' => 'Return',         'value' => '8',   'unit' => 'Trips', 'time' => '10:23 AM', 'done' => true],
    ['label' => 'One-Way',        'value' => '16',  'unit' => 'Trips', 'time' => '9:28 AM',  'done' => false],
  ],
  'driver' => ['name' => 'Antoni Jaison', 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80'],
  'mapImage' => null,
])

<section class="rounded-3xl bg-surface p-6 shadow-card">
  <div class="flex items-start justify-between">
    <div>
      <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>
      <p class="mt-1 text-xs text-muted">Recent delivery history</p>
    </div>
    <button class="grid size-7 place-items-center rounded-full border border-line text-muted transition hover:bg-soft hover:text-ink" aria-label="Options">
      <x-icon name="dots" class="size-4" />
    </button>
  </div>

  {{-- Map preview or fallback stylized SVG map --}}
  <div id="delivery-map" class="relative mt-4 h-40 overflow-hidden rounded-2xl bg-line">
    @if ($mapImage)
      <img src="{{ $mapImage }}" alt="Map" class="size-full object-cover">
    @else
      <svg viewBox="0 0 300 160" class="size-full" preserveAspectRatio="xMidYMid slice">
        <rect width="300" height="160" class="fill-soft"/>
        <path d="M0 40 L300 90 M60 0 L120 160 M180 0 L220 160 M0 120 L300 60" class="stroke-surface" stroke-width="6" fill="none" opacity=".7"/>
        <path d="M40 30 L40 55 Q80 110 150 100 L190 90" fill="none" stroke="var(--brand-500)" stroke-width="3"/>
        <circle cx="40" cy="55" r="4.5" fill="var(--brand-500)"/>
        <circle cx="190" cy="90" r="4.5" fill="var(--danger)"/>
      </svg>
    @endif
    <div class="absolute bottom-3 right-3 grid size-8 place-items-center rounded-full bg-brand-500 text-on-brand shadow-md">
      <span class="size-2 rounded-full bg-surface"></span>
    </div>
  </div>

  <div class="mt-5 flex items-center justify-between">
    <div>
      <p class="text-xs text-muted">Order ID:</p>
      <p class="mt-0.5 text-base font-bold text-ink">#{{ $orderId }}</p>
    </div>
    <span class="rounded-full bg-soft px-4 py-1.5 text-xs font-semibold text-brand-500">{{ $status }}</span>
  </div>

  {{-- Timeline List --}}
  <ol class="mt-5 space-y-4">
    @foreach ($timeline as $step)
      <li class="relative flex items-start justify-between pl-6">
        <span class="absolute left-0 top-1.5 size-2.5 rounded-full {{ $step['done'] ? 'bg-brand-500' : 'bg-line ring-2 ring-surface' }}"></span>
        @unless ($loop->last)
          <span class="absolute left-[4px] top-4 h-[calc(100%+0.5rem)] border-l border-dashed border-brand-500/40"></span>
        @endunless
        <div>
          <p class="text-xs text-muted">{{ $step['label'] }}</p>
          <p class="text-sm font-bold text-ink">{{ $step['value'] }} <span class="text-xs font-normal text-muted">{{ $step['unit'] }}</span></p>
        </div>
        <span class="text-[11px] font-medium text-muted">{{ $step['time'] }}</span>
      </li>
    @endforeach
  </ol>

  {{-- Driver Card --}}
  <div class="mt-5 flex items-center gap-3 rounded-2xl bg-soft/60 p-2.5">
    <img src="{{ $driver['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80' }}"
         alt="{{ $driver['name'] }}"
         class="size-10 rounded-full object-cover ring-2 ring-surface">
    <div class="min-w-0 flex-1">
      <p class="text-[11px] text-muted font-medium">Driver</p>
      <p class="truncate text-sm font-bold text-ink">{{ $driver['name'] }}</p>
    </div>
    <button class="grid size-9 place-items-center rounded-full bg-surface text-muted transition hover:bg-soft hover:text-brand-500" aria-label="Message">
      <x-icon name="chat" class="size-4" />
    </button>
    <button class="grid size-9 place-items-center rounded-full bg-surface text-muted transition hover:bg-soft hover:text-brand-500" aria-label="Call">
      <x-icon name="phone" class="size-4" />
    </button>
  </div>
</section>
