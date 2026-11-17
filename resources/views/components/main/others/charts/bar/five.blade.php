@php
    $timeLabels = json_decode('["01:00 PM","12:00 PM","11:00 AM","10:00 AM","09:00 AM","08:00 AM"]', true);
    $gridSlots = json_decode('["bg-soft","bg-canvas/60","bg-canvas/60","bg-soft","bg-canvas/60","bg-soft","bg-soft","bg-brand-300 /50","bg-brand-300 /50","bg-brand-300 /50","bg-brand-300 /50","bg-brand-200 /35","bg-soft","bg-brand-200 /35","bg-soft","bg-brand-400 /70","bg-soft","bg-brand-200 /35","bg-brand-200 /35","bg-brand-400 /70","bg-brand-300 /50","bg-brand-300 /50","bg-brand-200 /35","bg-canvas/60","bg-brand-400 /70","bg-brand-200 /35","bg-brand-400 /70","bg-brand-500 ","bg-brand-500 ","bg-brand-500 ","bg-brand-500 ","bg-brand-300 /50","bg-brand-300 /50","bg-brand-500 ","bg-brand-500 ","bg-brand-300 /50","bg-brand-200 /35","bg-canvas/60","bg-brand-400 /70","bg-brand-200 /35","bg-brand-400 /70","bg-brand-500 "]', true);
    $dayLabels = json_decode('["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]', true);
@endphp
<x-ui.scroll-area class="max-w-full">
  <div class="min-w-140 xl:min-w-full">
    <div class="flex items-center gap-4">
      <!-- Time axis column -->
      <div class="flex h-65.25 shrink-0 flex-col items-start pb-7.5">
        @foreach ($timeLabels as $time)
<div class="flex min-h-px w-13.75 flex-1 items-center justify-start text-start">
          <span class="font-outfit text-xs font-normal text-ink">
            {{ $time }}
          </span>
        </div>
@endforeach
      </div>

      <!-- Tracker grid & Day labels column -->
      <div class="flex flex-1 flex-col gap-7">
        <!-- Heatmap / tracker grid -->
        <div class="grid h-[231.2px] w-full grid-cols-7 grid-rows-6 gap-x-2 gap-y-1">
          @foreach ($gridSlots as $bgClass)
<div class="rounded-lg {{ $bgClass }}"></div>
@endforeach
        </div>

        <!-- Day labels row -->
        <div
          class="flex items-center justify-between text-center font-outfit text-xs font-normal text-ink"
        >
          @foreach ($dayLabels as $day)
<span class="flex-1">
            {{ $day }}
          </span>
@endforeach
        </div>
      </div>
    </div>
  </div>
</x-ui.scroll-area>
