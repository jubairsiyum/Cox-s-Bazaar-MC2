{{--
  Stat Card Component
  Usage: <x-dashboard.stat-card title="Total Expenses" :amount="27200" :change="44" />
  Positive change = brand-500 / ↑, Negative change = danger / ↓
--}}
@props([
  'title',
  'amount' => 0,
  'change' => 0,
  'caption' => 'Bangladeshi Taka',
  'bn' => false,
  'currency' => '৳',
  'icon' => null,
])

@php
  $up  = $change >= 0;
  $pct = min(abs($change), 100);
  $fmt = number_format((float) $amount);
  if ($bn) {
      $fmt = strtr($fmt, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
  }
  $color = $up ? 'text-brand-500' : 'text-danger';
@endphp

<div class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card transition-all hover:shadow-lg h-full flex flex-col justify-between">
  <div class="flex items-center justify-between">
    <h3 class="text-sm sm:text-base font-semibold text-ink/80">{{ $title }}</h3>
    @if ($icon)
      <span class="grid size-8 sm:size-9 place-items-center rounded-2xl bg-soft text-brand-500">
        <x-icon :name="$icon" class="size-4" />
      </span>
    @endif
  </div>

  <div class="mt-4 flex items-end justify-between gap-4">
    <div class="min-w-0">
      <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-ink leading-tight">{{ $currency }} {{ $fmt }}</p>
      @if ($caption)
        <p class="mt-1.5 text-xs font-medium text-muted">{{ $caption }}</p>
      @endif
    </div>

    {{-- Gauge: pathLength=100 allows dasharray to match percentage directly --}}
    <div class="relative h-14 w-24 shrink-0 sm:h-16 sm:w-28">
      <svg viewBox="0 0 120 64" class="h-full w-full">
        <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
              class="text-line" stroke-width="12" stroke-linecap="round"/>
        <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
              class="{{ $color }}" stroke-width="12" stroke-linecap="round"
              stroke-dasharray="{{ $pct }} 100"/>
      </svg>
      <span class="absolute inset-x-0 bottom-0 text-center text-[11px] font-semibold {{ $color }}">
        {{ $change > 0 ? '+' : '' }}{{ $change }}% {{ $up ? '↑' : '↓' }}
      </span>
    </div>
  </div>
</div>
