@extends('layouts.app')

@section('title', 'Distribution Dashboard — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Distribution Dashboard')

@section('content')
  <div class="space-y-6">

    {{-- ── Header: Greeting, Role & Outlet Scope Switcher ── --}}
    <div class="enter mv-down flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-1" style="--i:0">
      <div>
        <div class="flex items-center gap-2.5 flex-wrap">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            {{ $greeting }}, {{ $userName }}!
          </h1>
          <span class="text-xl sm:text-2xl select-none">👋</span>

          {{-- Role Badge --}}
          <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
            <span class="size-1.5 rounded-full bg-brand-500"></span>
            {{ $primaryRole }}
          </span>

          {{-- Outlet Scope Indicator / Switcher --}}
          @if ($isSuperAdmin && $allLocations->isNotEmpty())
            {{-- ONLY Super Admin gets the interactive switcher dropdown --}}
            <div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
              <button type="button"
                      @click="open = !open"
                      class="btn-scale inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-1 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                <x-icon name="store" class="size-3.5 text-brand-500" />
                <span class="max-w-[180px] sm:max-w-xs truncate">{{ $activeScopeLocation ? $activeScopeLocation->name : 'All Outlets (Network)' }}</span>
                <x-icon name="chevron" class="size-3 text-muted transition-transform duration-200" ::class="open && 'rotate-180'" />
              </button>

              <div x-show="open" x-cloak
                   x-transition:enter="transition duration-200 ease-out"
                   x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                   x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                   x-transition:leave="transition duration-150 ease-in"
                   x-transition:leave-start="opacity-100 scale-100"
                   x-transition:leave-end="opacity-0 scale-95"
                   class="absolute left-0 z-50 mt-2 w-64 origin-top-left rounded-2xl border border-line bg-surface p-1.5 shadow-card text-xs">
                <div class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted border-b border-line mb-1">
                  Select Scope View
                </div>

                {{-- All Outlets --}}
                <a href="{{ route('dashboard') }}"
                   class="flex items-center justify-between rounded-xl px-3 py-2 font-medium transition-colors {{ !$scopedLocationId ? 'bg-soft text-brand-500 font-bold' : 'text-ink hover:bg-soft' }}">
                  <span class="flex items-center gap-2">
                    <x-icon name="dashboard" class="size-3.5 text-muted" />
                    <span>All Outlets & Warehouses</span>
                  </span>
                  @if(!$scopedLocationId)
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                  @endif
                </a>

                <div class="my-1 border-t border-line"></div>

                {{-- Individual Outlets --}}
                @foreach($allLocations as $loc)
                  <a href="{{ route('dashboard', ['location_id' => $loc->id]) }}"
                     class="flex items-center justify-between rounded-xl px-3 py-2 font-medium transition-colors {{ $scopedLocationId === $loc->id ? 'bg-soft text-brand-500 font-bold' : 'text-ink hover:bg-soft' }}">
                    <span class="flex items-center gap-2 truncate">
                      <x-icon name="store" class="size-3.5 text-muted" />
                      <span class="truncate">{{ $loc->name }}</span>
                    </span>
                    @if($scopedLocationId === $loc->id)
                      <span class="size-1.5 rounded-full bg-brand-500"></span>
                    @endif
                  </a>
                @endforeach
              </div>
            </div>
          @elseif ($activeScopeLocation)
            {{-- All non-Super Admin roles (Outlet Manager, Manager, Sales Staff, etc.): strictly locked to their outlet --}}
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-500/25 bg-surface px-3 py-1 text-xs font-semibold text-brand-600 shadow-2xs">
              <x-icon name="store" class="size-3.5 text-brand-500" />
              <span>Outlet Scope: <strong>{{ $activeScopeLocation->name }}</strong></span>
            </span>
          @endif
        </div>

        <p class="mt-1 text-xs sm:text-sm text-muted">
          @if ($activeScopeLocation)
            Showing live metrics for <strong class="text-ink">{{ $activeScopeLocation->name }}</strong> on {{ now()->format('l, d M Y') }}
          @else
            Consolidated network overview across all distribution outlets and warehouses on {{ now()->format('l, d M Y') }}
          @endif
          <span class="text-muted/80"> · As of {{ $asOfFormatted ?? now()->format('d M, h:i A') }}</span>
        </p>
      </div>

      {{-- ── Quick Add Shortcuts (Gated by Permissions) ── --}}
      <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
        {{-- POS Quick Terminal Shortcut (For Outlet Managers & Sales Staff) --}}
        @can('pos.access')
          <a href="{{ route('pos.index') }}"
             class="btn-scale inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
            <x-icon name="cart" class="size-3.5 text-brand-500" />
            <span>POS Terminal</span>
          </a>
        @endcan

        {{-- Add Product Shortcut (Only if permitted) --}}
        @can('products.manage')
          <a href="{{ route('products.create') }}"
             class="btn-scale inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
            <x-icon name="plus" class="size-3.5 text-muted" />
            <span>Add Product</span>
          </a>
        @endcan

        {{-- Primary Action: Transfer or Sales Order --}}
        @can('stock-transfers.manage')
          <a href="{{ route('stock-transfers.create') }}"
             class="btn-scale inline-flex items-center gap-2 rounded-full bg-brand-500 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
            <x-icon name="plus" class="size-3.5" />
            <span>New Transfer</span>
          </a>
        @elsecan('sales-orders.manage')
          <a href="{{ route('sales-orders.create') }}"
             class="btn-scale inline-flex items-center gap-2 rounded-full bg-brand-500 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
            <x-icon name="plus" class="size-3.5" />
            <span>New Order</span>
          </a>
        @endcan

        {{-- More Quick Actions Dropdown (Only shows options user can execute) --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
          <button type="button"
                  @click="open = !open"
                  class="btn-scale grid size-9 place-items-center rounded-full border border-line bg-surface text-muted shadow-2xs hover:bg-soft hover:text-ink transition-colors cursor-pointer"
                  title="More actions">
            <x-icon name="dots" class="size-4" />
          </button>

          <div x-show="open" x-cloak
               x-transition:enter="transition duration-300 ease-[cubic-bezier(.16,1,.3,1)]"
               x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               x-transition:leave="transition duration-150 ease-in"
               x-transition:leave-start="opacity-100 scale-100"
               x-transition:leave-end="opacity-0 scale-95"
               class="absolute right-0 z-50 mt-2 w-48 origin-top-right rounded-2xl border border-line bg-surface p-1.5 shadow-card text-xs">
            @can('sales-orders.manage')
              <a href="{{ route('sales-orders.create') }}"
                 class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
                <x-icon name="cart" class="size-4 text-muted" />
                <span>New Sales Order</span>
              </a>
            @endcan

            @can('customers.manage')
              <a href="{{ route('customers.create') }}"
                 class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
                <x-icon name="store" class="size-4 text-muted" />
                <span>Add Retailer</span>
              </a>
            @endcan

            @can('stock-adjustments.manage')
              <a href="{{ route('stock-adjustments.create') }}"
                 class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
                <x-icon name="box" class="size-4 text-muted" />
                <span>Stock Adjustment</span>
              </a>
            @endcan

            @can('suppliers.manage')
              <a href="{{ route('suppliers.create') }}"
                 class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
                <x-icon name="truck" class="size-4 text-muted" />
                <span>Add Supplier</span>
              </a>
            @endcan

            @can('sales-returns.manage')
              <a href="{{ route('sales-returns.create') }}"
                 class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
                <x-icon name="arrow-up-right" class="size-4 text-muted" />
                <span>Sales Return</span>
              </a>
            @endcan
          </div>
        </div>
      </div>
    </div>

    @if ($isRestricted && !$assignedLocation)
      <div class="rounded-2xl border border-warning/30 bg-warning/10 p-4 text-xs sm:text-sm text-warning flex items-start gap-3">
        <x-icon name="warning" class="size-5 shrink-0 mt-0.5 text-warning" />
        <div>
          <h4 class="font-bold text-ink">No Outlet Assigned to Your Account</h4>
          <p class="mt-0.5 text-muted">You are logged in with role <strong>{{ $primaryRole }}</strong>, but no specific outlet/branch is currently assigned to your account. For security, cross-outlet metrics are withheld. An administrator can assign your outlet under User Management.</p>
        </div>
      </div>
    @endif

    @if ($isSuperAdmin && !$targetLocationId && ($networkPayable['unclosed_branches_count'] ?? 0) > 0)
      <div 
        x-data="{ dismissed: false }"
        x-show="!dismissed"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="rounded-2xl border border-warning/30 bg-warning/10 p-3.5 sm:p-4 text-xs sm:text-sm text-ink flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-2.5">
          <x-icon name="alert-triangle" class="size-4 sm:size-5 shrink-0 text-warning" />
          <span><strong>Daily Closing Notice:</strong> {{ $networkPayable['unclosed_branches_count'] }} of {{ $networkPayable['branches_count'] }} {{ \Illuminate\Support\Str::plural('branch', $networkPayable['branches_count']) }} {{ $networkPayable['unclosed_branches_count'] === 1 ? 'has' : 'have' }} not completed daily cash close today.</span>
        </div>
        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
          <a href="{{ route('day-closings.index') }}" class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-warning/20 border border-warning/40 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-warning/30 transition-colors">
            <span>View Branch Status</span>
            <x-svg.arrow-right class="size-3" />
          </a>
          <button 
            type="button" 
            @click="dismissed = true" 
            class="p-1 rounded-xl text-ink/60 hover:text-ink hover:bg-warning/25 transition-colors cursor-pointer"
            title="Dismiss notice"
            aria-label="Dismiss notice">
            <x-svg.x class="size-4" />
          </button>
        </div>
      </div>
    @endif

    {{-- ── 1. Top 4 Metric KPI Stat Cards (Scoped) ── --}}
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Today's Sales" 
          :amount="$todaySales" 
          :change="$salesChangePct" 
          :caption="$todaySalesCaption" 
          icon="cart"
          class="rv mv-blur lift"
          style="--i:1; --stagger:160ms; --d:80ms"
        />
      </div>
      <div class="flex flex-col">
        @if ($isOutletScope)
          <x-dashboard.stat-card 
            title="Today's Collection" 
            :amount="$todayCollection" 
            :change="$collectionChangePct" 
            :caption="$todayCollectionCaption" 
            :href="route('collections.index')"
            icon="wallet"
            class="rv mv-blur lift"
            style="--i:2; --stagger:160ms; --d:80ms"
          />
        @else
          <x-dashboard.stat-card 
            title="Collection from Branches" 
            :amount="(float) ($networkPayable['today_sent_to_hq'] ?? 0)" 
            :change="(int) ($networkPayable['progress_pct'] ?? 0)" 
            :progress="true"
            :caption="$hqCollectionCaption" 
            :href="route('account-transfers.index')"
            icon="wallet"
            class="rv mv-blur lift"
            style="--i:2; --stagger:160ms; --d:80ms"
          />
        @endif
      </div>
      <div class="flex flex-col">
        @if ($isOutletScope)
          <x-dashboard.stat-card 
            title="Payable to HQ" 
            :amount="$payableSummary['left_amount']" 
            :change="$payableSummary['progress_pct']" 
            :progress="true"
            :caption="$clearDayCaption" 
            :href="$clearDayUrl"
            icon="warehouse"
            class="rv mv-blur lift"
            style="--i:3; --stagger:160ms; --d:80ms"
          />
        @else
          <x-dashboard.stat-card 
            title="Branch Closings (Receivable)" 
            :amount="(float) ($networkPayable['total_receivable'] ?? 0)" 
            :change="(int) ($networkPayable['progress_pct'] ?? 100)" 
            :progress="true"
            :caption="$networkPayable['caption'] ?? 'All clear · All branch closings received'" 
            :href="route('day-closings.index')"
            icon="warehouse"
            class="rv mv-blur lift"
            style="--i:3; --stagger:160ms; --d:80ms"
          />
        @endif
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Total Stock Value" 
          :amount="$totalStockValue" 
          :change="$stockValueChangePct" 
          :caption="$stockValueCaption" 
          icon="box"
          class="rv mv-blur lift"
          style="--i:4; --stagger:160ms; --d:80ms"
        />
      </div>
    </div>

    {{-- ── 2. Middle Row: Sales vs. Collection Trend (2 Cols) + Field Distribution (1 Col) ── --}}
    <div class="grid gap-5 xl:grid-cols-3 items-stretch">
      <div class="xl:col-span-2 flex flex-col">
        @if ($dynamicChartPeriods)
          <x-dashboard.sales-chart 
            class="rv" 
            style="--i:5; --stagger:130ms; --d:100ms" 
            :title="$activeScopeLocation ? 'Sales & Collections (' . $activeScopeLocation->name . ')' : 'Sales & Collection Trend'"
            :periods="$dynamicChartPeriods" 
          />
        @else
          <x-dashboard.sales-chart class="rv" style="--i:5; --stagger:130ms; --d:100ms" />
        @endif
      </div>
      <div class="xl:col-span-1 flex flex-col">
        <x-dashboard.distribution-summary 
          class="rv" 
          style="--i:5; --stagger:130ms; --d:100ms" 
          :dispatchedUnits="$todayDispatched"
          :placedUnits="$todayPlaced"
          :returnsCount="$todayInTransit"
          :activeRoutes="$activeRoutes"
        />
      </div>
    </div>

    {{-- ── 3. Third Row: Target Pie Chart (50%) + Brand Sales Share (50%) ── --}}
    <div class="grid gap-5 lg:grid-cols-2 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.target-chart 
          class="rv" 
          style="--i:2; --d:40ms" 
          :isSuperAdmin="$isSuperAdmin && !$targetLocationId"
          :branchPerformance="$branchTargetPerformance"
          :networkOverview="$networkTargetOverview"
          :branchName="$activeScopeLocation ? $activeScopeLocation->name : null"
        />
      </div>
      <div class="flex flex-col">
        @if ($dynamicBrandShares !== null)
          <x-dashboard.brand-share-chart
            class="rv"
            style="--i:2; --d:40ms"
            :title="$activeScopeLocation ? 'Brand Stock Share (' . $activeScopeLocation->name . ')' : 'Brand Sales Share'"
            :subtitle="$activeScopeLocation ? 'Distribution stock across brands at ' . $activeScopeLocation->name : null"
            :brands="$dynamicBrandShares"
            :totalRegisteredBrands="$totalRegisteredBrands"
            :warehouseName="$primaryWarehouse"
            volumeScale="raw"
          />
        @else
          <x-dashboard.brand-share-chart
            class="rv"
            style="--i:2; --d:40ms"
            :title="$activeScopeLocation ? 'Brand Stock Share (' . $activeScopeLocation->name . ')' : 'Brand Sales Share'"
            :subtitle="$activeScopeLocation ? 'Distribution stock across brands at ' . $activeScopeLocation->name : null"
            :warehouseName="$primaryWarehouse"
          />
        @endif
      </div>
    </div>

    {{-- ── 4. Fourth Row: Low Stock & Reorder Alerts (Full Width) ── --}}
    <div class="w-full">
      <x-dashboard.stock-alerts 
        class="rv w-full" 
        style="--i:2; --d:40ms" 
        :title="$activeScopeLocation ? 'Low Stock Alerts (' . $activeScopeLocation->name . ')' : 'Low Stock & Reorder Alerts'"
        :items="$dynamicAlertItems" 
      />
    </div>

    {{-- ── 5. Bottom Row: Recent Invoices & Orders (Scoped) ── --}}
    <div>
      <x-dashboard.recent-invoices 
        class="rv" 
        style="--i:1; --d:40ms" 
        :title="$activeScopeLocation ? 'Recent Invoices — ' . $activeScopeLocation->name : 'Recent Distribution Invoices'"
        :invoices="$dynamicRecentInvoices" 
      />
    </div>

  </div>
@endsection
