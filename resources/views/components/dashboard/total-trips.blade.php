@props([
  'count' => 24,
  'hired' => 5,
  'image' => asset('images/truck.png'),
])

<section class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card">
  <div class="flex items-start justify-between">
    <div>
      <h3 class="text-lg font-semibold text-ink">Total Trips</h3>
      <p class="mt-1 text-xs text-muted">Active on-road vehicles</p>
    </div>
    <button class="grid size-7 place-items-center rounded-full border border-line text-muted transition hover:bg-soft hover:text-ink" aria-label="Options">
      <x-icon name="dots" class="size-4" />
    </button>
  </div>

  <p class="mt-6 text-5xl font-bold tracking-tight text-ink">{{ $count }}</p>
  <p class="mt-1 text-xs text-muted">Hired transport <span class="text-base font-bold text-danger">{{ $hired }} Trips</span></p>

  {{-- Truck Illustration / Image --}}
  <div class="pointer-events-none absolute -right-2 bottom-4 select-none">
    @if (file_exists(public_path('images/truck.png')))
      <img src="{{ $image }}" alt="Truck" class="w-44 select-none sm:w-52">
    @else
      <div class="relative w-44 sm:w-52 opacity-90 dark:opacity-75">
        <svg viewBox="0 0 200 120" class="w-full h-auto text-brand-500" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 30C20 24.4772 24.4772 20 30 20H120C125.523 20 130 24.4772 130 30V80H20V30Z" fill="currentColor" fill-opacity="0.12"/>
          <path d="M130 45H160C163.5 45 166.8 46.8 168.6 49.8L180 68V80H130V45Z" fill="currentColor" fill-opacity="0.22"/>
          <rect x="138" y="52" width="28" height="18" rx="3" fill="var(--surface)"/>
          <circle cx="55" cy="85" r="14" fill="var(--ink)"/>
          <circle cx="55" cy="85" r="6" fill="var(--surface)"/>
          <circle cx="150" cy="85" r="14" fill="var(--ink)"/>
          <circle cx="150" cy="85" r="6" fill="var(--surface)"/>
          <line x1="10" y1="85" x2="35" y2="85" stroke="var(--line)" stroke-width="4" stroke-linecap="round"/>
          <line x1="75" y1="85" x2="130" y2="85" stroke="var(--line)" stroke-width="4" stroke-linecap="round"/>
        </svg>
      </div>
    @endif
  </div>

  <div class="mt-8 flex items-center gap-2 text-sm font-semibold text-brand-500">
    <span class="relative grid size-4 place-items-center">
      <span class="absolute size-4 animate-ping rounded-full bg-brand-500/30"></span>
      <span class="size-3 rounded-full bg-brand-500"></span>
    </span>
    On-Route
  </div>
</section>
