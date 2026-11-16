@php
    $categories = json_decode('[{"name":"Activity","color":"bg-brand-500"},{"name":"Online Purchases","color":"bg-brand-500"},{"name":"Groceries","color":"bg-brand-500"},{"name":"Digital Goods","color":"bg-brand-500"},{"name":"Stationery","color":"bg-brand-500"},{"name":"Others","color":"bg-brand-500 "}]', true);
@endphp
<x-ui.scroll-area class="max-w-full">
  <div class="flex min-w-105 flex-col gap-5 xl:min-w-full">
    <!-- Progress Bar Row -->
    <div class="flex w-full items-center gap-0.5">
      <div class="h-8 min-w-px flex-1 rounded bg-brand-500"></div>
      <div class="h-8 w-10.5 shrink-0 rounded bg-brand-500"></div>
      <div class="h-8 w-6.5 shrink-0 rounded bg-brand-500"></div>
      <div class="h-8 w-10 shrink-0 rounded bg-brand-500"></div>
      <div class="h-8 w-3.25 shrink-0 rounded bg-brand-500"></div>
      <div class="h-8 w-13 shrink-0 rounded bg-brand-500"></div>
    </div>

    <!-- Spending Categories Grid -->
    <div class="grid grid-cols-2 gap-x-3 gap-y-3">
      @foreach ($categories as $category)
<div class="flex items-center gap-2">
        <span class="size-2.5 shrink-0 rounded-full {{ $category['color'] }}"></span>
        <span class="text-sm font-normal text-ink">
          {{ $category['name'] }}
        </span>
      </div>
@endforeach
    </div>
  </div>
</x-ui.scroll-area>
