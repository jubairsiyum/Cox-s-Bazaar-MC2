@extends('layouts.app')

@section('title', 'Distribution Dashboard — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Distribution Dashboard')

@section('content')
  <div class="space-y-6">

    {{-- 1. Top 4 Metric KPI Stat Cards --}}
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Today's Sales" 
          :amount="184500" 
          :change="18" 
          caption="32 Handsets Dispatched" 
          icon="cart"
        />
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Today's Collection" 
          :amount="125000" 
          :change="24" 
          caption="Cash ৳75k / Bank ৳50k" 
          icon="wallet"
        />
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Retailer Dues (Receivable)" 
          :amount="1420000" 
          :change="-8" 
          caption="Overdue ৳1,45,000" 
          icon="store"
        />
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Total Stock Value" 
          :amount="8650000" 
          :change="12" 
          caption="384 Units in Central Warehouse" 
          icon="box"
        />
      </div>
    </div>

    {{-- 2. Middle Row: Sales vs. Collection Trend (2 Cols) + SR Field Distribution (1 Col) --}}
    <div class="grid gap-5 xl:grid-cols-3 items-stretch">
      <div class="xl:col-span-2 flex flex-col">
        <x-dashboard.sales-chart />
      </div>
      <div class="xl:col-span-1 flex flex-col">
        <x-dashboard.distribution-summary />
      </div>
    </div>

    {{-- 3. Third Row: Low Stock & Reorder Alerts (50%) + Brand Sales Share (50%) --}}
    <div class="grid gap-5 lg:grid-cols-2 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.stock-alerts />
      </div>
      <div class="flex flex-col">
        <x-dashboard.brand-share-chart />
      </div>
    </div>

    {{-- 4. Bottom Row: Recent Retailer Invoices & Orders --}}
    <div>
      <x-dashboard.recent-invoices />
    </div>

  </div>
@endsection
