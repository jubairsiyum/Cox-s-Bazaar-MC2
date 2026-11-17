{{--
  Recent Invoices & Retail Distribution Orders
  Usage: <x-dashboard.recent-invoices />
--}}
@props([
  'title' => 'Recent Distribution Invoices',
  'invoices' => [
    [
      'invoice_no' => 'INV-2026-0428',
      'retailer' => 'Bismillah Telecom',
      'location' => 'Chakaria Bazar',
      'items' => 'Samsung Galaxy A15 (10x), Vivo Y28 (5x)',
      'sr' => 'Rafiqul Islam',
      'amount' => 195000,
      'status' => 'paid',
      'date' => 'Today, 10:45 AM',
    ],
    [
      'invoice_no' => 'INV-2026-0427',
      'retailer' => 'Al-Madina Mobile House',
      'location' => 'Teknaf Road, Ukhia',
      'items' => 'Redmi Note 13 (8x), Realme C65 (6x)',
      'sr' => 'Kamal Hossain',
      'amount' => 162000,
      'status' => 'partial',
      'date' => 'Today, 09:20 AM',
    ],
    [
      'invoice_no' => 'INV-2026-0426',
      'retailer' => 'Cox\'s Gadget Corner',
      'location' => 'Sadar Market, Main Road',
      'items' => 'Samsung A05s (12x), Earbuds (20x)',
      'sr' => 'Shahidul Alam',
      'amount' => 148500,
      'status' => 'due',
      'date' => 'Yesterday',
    ],
    [
      'invoice_no' => 'INV-2026-0425',
      'retailer' => 'Moon Telecom & Accessories',
      'location' => 'Eidgaon Station',
      'items' => 'Vivo Y17s (6x), Redmi 13C (10x)',
      'sr' => 'Rafiqul Islam',
      'amount' => 134000,
      'status' => 'paid',
      'date' => 'Yesterday',
    ],
    [
      'invoice_no' => 'INV-2026-0424',
      'retailer' => 'Ramu Digital Center',
      'location' => 'Ramu Bypass',
      'items' => 'Realme C53 (8x), Fast Chargers (30x)',
      'sr' => 'Kamal Hossain',
      'amount' => 98000,
      'status' => 'paid',
      'date' => '14 Nov 2026',
    ],
  ],
])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-3xl bg-surface p-6 shadow-card']) }}>
  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3 pb-5 border-b border-line">
    <div>
      <h3 class="text-base sm:text-lg font-semibold text-ink">{{ $title }}</h3>
      <p class="text-xs text-muted">Latest wholesale orders dispatched to retailers</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="{{ url('/sales') }}" 
         class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-1.5 text-xs font-semibold text-ink transition-[transform,background-color] duration-200 hover:bg-soft hover:text-brand-500 active:scale-[0.97] shadow-2xs">
        <span>All Invoices</span>
        <x-icon name="arrow-up-right" class="size-3.5" />
      </a>
    </div>
  </div>

  {{-- Responsive Table --}}
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead>
        <tr class="border-b border-line text-xs font-semibold uppercase tracking-wider text-muted">
          <th class="py-3.5 pr-4">Invoice #</th>
          <th class="py-3.5 px-4">Retailer / Route</th>
          <th class="py-3.5 px-4 hidden md:table-cell">Items Handsets</th>
          <th class="py-3.5 px-4 hidden sm:table-cell">SR Assigned</th>
          <th class="py-3.5 px-4 text-right">Amount (৳)</th>
          <th class="py-3.5 px-4 text-center">Payment</th>
          <th class="py-3.5 pl-4 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line">
        @foreach ($invoices as $inv)
          <tr style="--row-i: {{ $loop->index }}" class="transition-colors hover:bg-soft/25">
            {{-- Invoice No & Date --}}
            <td class="py-3.5 pr-4 whitespace-nowrap">
              <span class="font-bold text-ink">{{ $inv['invoice_no'] }}</span>
              <span class="block text-[11px] text-muted">{{ $inv['date'] }}</span>
            </td>

            {{-- Retailer & Location --}}
            <td class="py-3.5 px-4">
              <div class="font-semibold text-ink">{{ $inv['retailer'] }}</div>
              <div class="text-xs text-muted">{{ $inv['location'] }}</div>
            </td>

            {{-- Items --}}
            <td class="py-3.5 px-4 hidden md:table-cell max-w-xs truncate text-xs text-ink/80">
              {{ $inv['items'] }}
            </td>

            {{-- SR --}}
            <td class="py-3.5 px-4 hidden sm:table-cell whitespace-nowrap text-xs text-muted">
              {{ $inv['sr'] }}
            </td>

            {{-- Amount --}}
            <td class="py-3.5 px-4 text-right whitespace-nowrap">
              <span class="font-bold text-ink">৳ {{ number_format($inv['amount']) }}</span>
            </td>

            {{-- Status Badge --}}
            <td class="py-3.5 px-4 text-center whitespace-nowrap">
              @if ($inv['status'] === 'paid')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/15 px-2.5 py-0.5 text-xs font-semibold text-success">
                  <span class="size-1.5 rounded-full bg-success"></span> Paid
                </span>
              @elseif ($inv['status'] === 'partial')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-semibold text-warning">
                  <span class="size-1.5 rounded-full bg-warning"></span> Partial
                </span>
              @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/15 px-2.5 py-0.5 text-xs font-semibold text-danger">
                  <span class="size-1.5 rounded-full bg-danger"></span> Due
                </span>
              @endif
            </td>

            {{-- Action --}}
            <td class="py-3.5 pl-4 text-right whitespace-nowrap">
              <button type="button" 
                      class="grid size-8 place-items-center rounded-xl border border-line bg-surface text-muted transition hover:bg-soft hover:text-brand-500 shadow-2xs ml-auto"
                      title="View Details">
                <x-icon name="arrow-up-right" class="size-3.5" />
              </button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
