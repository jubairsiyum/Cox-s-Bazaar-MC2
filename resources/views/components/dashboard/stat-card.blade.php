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
'progress' => false,
'gaugeLabel' => null,
'progressColor' => null,
'href' => null,
])

@php
$changeNum = (float) $change;
$isNegative = $changeNum < 0;
$isZero = $changeNum == 0.0;
$up = $changeNum > 0;
$pct = min(max(abs($changeNum), 0), 100);

$fmt = number_format((float) $amount);
if ($bn) {
$fmt = strtr($fmt, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}

if ($progress) {
    if ($progressColor) {
        $color = $progressColor;
    } elseif ($pct >= 100) {
        $color = 'text-emerald-500';
    } elseif ($pct > 0) {
        $color = 'text-brand-500';
    } else {
        $color = 'text-muted';
    }
} else {
    // When % is minus value: vibrant red bar & label (text-danger / text-red-500)
    // When % is positive: brand color (text-brand-500)
    // When % is zero: neutral muted color (text-muted)
    if ($isNegative) {
        $color = 'text-danger text-red-500';
    } elseif ($up) {
        $color = 'text-brand-500';
    } else {
        $color = 'text-muted';
    }
}

$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card h-full flex flex-col justify-between' . ($href ? ' transition-all hover:ring-2 hover:ring-brand-500/20 cursor-pointer' : '')]) }} x-data>
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
      <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-ink leading-tight flex items-center gap-1 sm:gap-1.5">
        @if ($currency === '৳')
          <x-taka class="size-6 sm:size-7 text-ink shrink-0 -mt-0.5" strokeWidth="2.2" />
        @elseif ($currency)
          <span>{{ $currency }}</span>
        @endif
        <span class="tabular-nums" x-count="{{ $amount }}" @if($bn) data-locale="bn-BD" @endif>{{ $fmt }}</span>
      </p>
      @if ($caption)
      <p class="mt-1.5 text-xs font-medium text-muted opacity-70">{{ $caption }}</p>
      @endif
    </div>

    {{-- Gauge: pathLength=100 allows dasharray to match percentage directly --}}
    <div class="relative h-14 w-24 shrink-0 sm:h-16 sm:w-28">
      <svg viewBox="0 0 120 64" class="h-full w-full">
        <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
          class="text-line" stroke-width="12" stroke-linecap="round" />
        <path d="M10 60 A50 50 0 0 1 110 60" pathLength="100" fill="none" stroke="currentColor"
          class="gauge {{ $color }}" stroke-width="12" stroke-linecap="round"
          style="--target-pct: {{ $pct }}; stroke-dasharray: {{ $pct }} 100;"
          stroke-dasharray="{{ $pct }} 100" />
      </svg>
      <span class="gauge-label absolute inset-x-0 bottom-0 text-center text-[11px] font-semibold {{ $color }}">
        @if ($gaugeLabel !== null)
          {{ $gaugeLabel }}
        @elseif ($progress)
          {{ (int) round($pct) }}%
        @elseif ($isNegative)
          {{ $change }}% ↓
        @elseif ($up)
          +{{ $change }}% ↑
        @else
          0%
        @endif
      </span>
    </div>
  </div>
</{{ $tag }}>