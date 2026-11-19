@extends('layouts.app')

@section('title', 'Distribution Dashboard — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Distribution Dashboard')

@php
  // Safe Dynamic Data Resolution with Fallbacks
  try {
      // 1. Live Sales Metrics from SalesInvoices
      $todaySales = (float) \App\Models\SalesInvoice::whereDate('invoice_date', today())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('grand_total');

      $yesterdaySales = (float) \App\Models\SalesInvoice::whereDate('invoice_date', today()->subDay())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('grand_total');

      $salesChangePct = $yesterdaySales > 0
          ? (int) round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100)
          : ($todaySales > 0 ? 100 : 0);

      $todayInvoicesCount = (int) \App\Models\SalesInvoice::whereDate('invoice_date', today())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->count();

      // 2. Collections (Recorded payments on sales invoices)
      $todayCollection = (float) \App\Models\SalesInvoice::whereDate('invoice_date', today())
          ->sum('paid_amount');

      $yesterdayCollection = (float) \App\Models\SalesInvoice::whereDate('invoice_date', today()->subDay())
          ->sum('paid_amount');

      $collectionChangePct = $yesterdayCollection > 0
          ? (int) round((($todayCollection - $yesterdayCollection) / $yesterdayCollection) * 100)
          : ($todayCollection > 0 ? 100 : 0);

      // 3. Retailer Dues (Total Outstanding Receivables on posted invoices)
      $totalReceivableDue = (float) \App\Models\SalesInvoice::where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('due_amount');

      $overdueReceivable = (float) \App\Models\SalesInvoice::where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->whereDate('invoice_date', '<', today()->subDays(30))
          ->where('due_amount', '>', 0)
          ->sum('due_amount');

      $activeRetailersCount = (int) \App\Models\Customer::where('status', 'active')->count();

      // 4. Stock Valuation & Inventory Units
      $totalStockUnits = (int) \App\Models\Unit::sum('quantity');

      $rawStockValue = \Illuminate\Support\Facades\DB::table('units')
          ->join('products', 'units.product_id', '=', 'products.id')
          ->whereNull('products.deleted_at')
          ->whereNull('units.deleted_at')
          ->selectRaw('SUM(units.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as total_val')
          ->value('total_val');
      $totalStockValue = $rawStockValue ? (float) $rawStockValue : 0;

      // Primary active warehouse / central location
      $primaryWarehouse = \App\Models\Location::where('status', 'active')
          ->where('type', \App\Enums\LocationType::Warehouse->value ?? 'warehouse')
          ->value('name')
          ?? \App\Models\Location::where('status', 'active')->value('name')
          ?? 'Central Warehouse';

      // 5. Stock Transfers & Field Placement
      $todayDispatched = (int) \App\Models\StockTransfer::whereDate('transfer_date', today())
          ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
          ->sum('stock_transfer_items.quantity');

      $todayPlaced = (int) \App\Models\StockTransfer::whereDate('transfer_date', today())
          ->where('status', \App\Enums\StockTransferStatus::Received ?? 'received')
          ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
          ->sum('stock_transfer_items.quantity');

      $todayInTransit = max(0, $todayDispatched - $todayPlaced);
      $activeRoutes = (int) \App\Models\Location::where('status', 'active')->count();

      // Fallback captions
      $todaySalesCaption = $todayInvoicesCount > 0
          ? $todayInvoicesCount . ' ' . \Illuminate\Support\Str::plural('Invoice', $todayInvoicesCount) . ' Posted Today'
          : ($todayDispatched > 0 ? $todayDispatched . ' Handsets Dispatched' : 'No invoices posted yet today');

      $todayCollectionCaption = $todayCollection > 0
          ? 'Collected today against invoices'
          : 'No invoice collections today';

      $retailerDuesCaption = $overdueReceivable > 0
          ? 'Overdue ৳' . number_format($overdueReceivable) . ' · ' . $activeRetailersCount . ' Retailers'
          : ($activeRetailersCount . ' Active ' . \Illuminate\Support\Str::plural('Retailer', $activeRetailersCount));

      $stockValueCaption = $totalStockUnits > 0
          ? number_format($totalStockUnits) . ' Units in ' . $primaryWarehouse
          : '0 units registered in inventory';

      // 6. Dynamic Low Stock & Reorder Alerts
      $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 10);
      $lowStockProducts = \App\Models\Product::with(['brand', 'stockRow'])
          ->where('status', 'active')
          ->whereHas('stockRow', fn ($q) => $q->where('quantity', '<=', $lowStockThreshold))
          ->take(5)
          ->get();

      $dynamicAlertItems = $lowStockProducts->map(function ($p) use ($lowStockThreshold) {
          $stock = (int) ($p->stockRow?->quantity ?? 0);
          $brandName = $p->brand?->name ?? 'Brand';
          $modelName = $p->model
              ? "{$p->name} ({$p->model})"
              : ($p->storage ? "{$p->name} ({$p->storage})" : $p->name);

          return [
              'sku' => $p->product_id ?? ('PRD-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT)),
              'brand' => $brandName,
              'model' => $modelName,
              'color' => $p->color ?? 'Standard',
              'stock' => $stock,
              'min' => $lowStockThreshold,
              'status' => $stock <= 3 ? 'critical' : 'low',
          ];
      })->all();

      // 7. Dynamic Sales & Collection Trend Chart Data
      $start7d = today()->subDays(6);
      $invoices7d = \App\Models\SalesInvoice::whereDate('invoice_date', '>=', $start7d)
          ->selectRaw("DATE(invoice_date) as d, 
                       SUM(CASE WHEN status = 'posted' THEN grand_total ELSE 0 END) as s_total, 
                       SUM(paid_amount) as c_total")
          ->groupBy('d')
          ->get()
          ->keyBy('d');

      $days7Categories = [];
      $days7Sales = [];
      $days7Collections = [];
      for ($i = 6; $i >= 0; $i--) {
          $targetDay = today()->subDays($i);
          $dStr = $targetDay->toDateString();
          $days7Categories[] = $targetDay->format('D');
          $days7Sales[] = round((float) ($invoices7d[$dStr]->s_total ?? 0), 2);
          $days7Collections[] = round((float) ($invoices7d[$dStr]->c_total ?? 0), 2);
      }

      // 30d (Last 4 weeks aggregated)
      $weeksCategories = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
      $weeksSales = [0, 0, 0, 0];
      $weeksCollections = [0, 0, 0, 0];
      $start30d = today()->subDays(27);
      $invoices30d = \App\Models\SalesInvoice::whereDate('invoice_date', '>=', $start30d)
          ->selectRaw("DATE(invoice_date) as d, 
                       SUM(CASE WHEN status = 'posted' THEN grand_total ELSE 0 END) as s_total, 
                       SUM(paid_amount) as c_total")
          ->groupBy('d')
          ->get()
          ->keyBy('d');

      for ($w = 0; $w < 4; $w++) {
          $wStart = today()->subDays(27 - ($w * 7));
          for ($offset = 0; $offset < 7; $offset++) {
              $curDateStr = (clone $wStart)->addDays($offset)->toDateString();
              if (isset($invoices30d[$curDateStr])) {
                  $weeksSales[$w] += (float) $invoices30d[$curDateStr]->s_total;
                  $weeksCollections[$w] += (float) $invoices30d[$curDateStr]->c_total;
              }
          }
          $weeksSales[$w] = round($weeksSales[$w], 2);
          $weeksCollections[$w] = round($weeksCollections[$w], 2);
      }

      // Year (Current year, 12 months)
      $yearMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      $yearSales = array_fill(0, 12, 0);
      $yearCollections = array_fill(0, 12, 0);
      $invoicesYear = \App\Models\SalesInvoice::whereYear('invoice_date', now()->year)
          ->selectRaw("MONTH(invoice_date) as m, 
                       SUM(CASE WHEN status = 'posted' THEN grand_total ELSE 0 END) as s_total, 
                       SUM(paid_amount) as c_total")
          ->groupBy('m')
          ->get()
          ->keyBy('m');

      for ($m = 1; $m <= 12; $m++) {
          if (isset($invoicesYear[$m])) {
              $yearSales[$m - 1] = round((float) $invoicesYear[$m]->s_total, 2);
              $yearCollections[$m - 1] = round((float) $invoicesYear[$m]->c_total, 2);
          }
      }

      $dynamicChartPeriods = [
          '7d' => [
              'label' => 'Last 7 Days',
              'categories' => $days7Categories,
              'sales' => $days7Sales,
              'collections' => $days7Collections,
          ],
          '30d' => [
              'label' => 'This Month',
              'categories' => $weeksCategories,
              'sales' => $weeksSales,
              'collections' => $weeksCollections,
          ],
          'year' => [
              'label' => 'This Year',
              'categories' => $yearMonths,
              'sales' => $yearSales,
              'collections' => $yearCollections,
          ],
      ];

      // 8. Dynamic Recent Invoices
      $recentInvoicesList = \App\Models\SalesInvoice::with(['customer', 'items.unit', 'creator', 'location'])
          ->latest('invoice_date')
          ->take(5)
          ->get();

      $dynamicRecentInvoices = $recentInvoicesList->map(function ($inv) {
          $customer = $inv->customer;
          $items = $inv->items;
          $itemsSummary = $items->isEmpty()
              ? '—'
              : $items->take(2)->map(fn ($it) => ($it->unit?->displayName() ?? 'Handset') . ' (' . $it->quantity . 'x)')->implode(', ')
                . ($items->count() > 2 ? ' +' . ($items->count() - 2) . ' more' : '');

          return [
              'invoice_no' => $inv->invoice_no,
              'retailer' => $customer?->name ?? 'Walk-in Customer',
              'location' => filled($customer?->city) ? $customer->city : ($inv->location?->name ?? 'Main Outlet'),
              'items' => $itemsSummary,
              'sr' => $inv->creator?->name ?? 'Sales Staff',
              'amount' => (float) $inv->grand_total,
              'status' => $inv->payment_status?->value ?? 'due',
              'date' => $inv->invoice_date ? $inv->invoice_date->format('d M, h:i A') : 'Today',
              'url' => route('sales-invoices.show', $inv),
          ];
      })->all();

      // 9. Stocked Brands Share
      $totalRegisteredBrands = (int) \App\Models\Brand::query()->count();
      $stockedBrandRows = \Illuminate\Support\Facades\DB::table('brands')
          ->join('products', function ($join) {
              $join->on('products.brand_id', '=', 'brands.id')
                  ->whereNull('products.deleted_at');
          })
          ->join('units', function ($join) {
              $join->on('units.product_id', '=', 'products.id')
                  ->whereNull('units.deleted_at');
          })
          ->whereNull('brands.deleted_at')
          ->groupBy('brands.id', 'brands.name')
          ->havingRaw('SUM(units.quantity) > 0')
          ->selectRaw('brands.id, brands.name, SUM(units.quantity) as stock_units, COUNT(DISTINCT products.id) as sku_count, SUM(units.quantity * COALESCE(products.sale_price, products.purchase_price, 0)) as stock_value')
          ->orderByDesc('stock_value')
          ->orderByDesc('stock_units')
          ->get();

      $brandPalette = [
          '#465fff', '#f59e0b', '#10b981', '#8b5cf6',
          '#06b6d4', '#ec4899', '#f97316', '#14b8a6',
          '#6366f1', '#84cc16', '#e11d48', '#0ea5e9',
      ];

      $formatCompactBdt = static function (float $amount): string {
          $abs = abs($amount);
          if ($abs >= 10000000) {
              return '৳ ' . rtrim(rtrim(number_format($amount / 10000000, 2, '.', ''), '0'), '.') . 'Cr';
          }
          if ($abs >= 100000) {
              return '৳ ' . rtrim(rtrim(number_format($amount / 100000, 1, '.', ''), '0'), '.') . 'L';
          }
          if ($abs >= 1000) {
              return '৳ ' . rtrim(rtrim(number_format($amount / 1000, 1, '.', ''), '0'), '.') . 'K';
          }

          return '৳ ' . number_format($amount, 0);
      };

      $dynamicBrandShares = null;
      if ($stockedBrandRows->isNotEmpty()) {
          $sumValue = (float) $stockedBrandRows->sum('stock_value');
          $sumUnits = (int) $stockedBrandRows->sum('stock_units');
          $useValueMetric = $sumValue > 0;

          $dynamicBrandShares = $stockedBrandRows->values()->map(function ($row, $idx) use (
              $sumValue, $sumUnits, $useValueMetric, $brandPalette, $formatCompactBdt
          ) {
              $val = (float) $row->stock_value;
              $units = (int) $row->stock_units;
              $share = $useValueMetric
                  ? round(($val / max(1.0, $sumValue)) * 100, 1)
                  : round(($units / max(1, $sumUnits)) * 100, 1);

              $color = $brandPalette[$idx % count($brandPalette)];

              // 12-month share distribution initialized with current baseline
              $shareSeries = array_fill(0, 12, $share);
              $volumeSeries = array_fill(0, 12, round($val, 2));

              return [
                  'id' => (int) $row->id,
                  'name' => (string) $row->name,
                  'share' => $share,
                  'amount' => $useValueMetric ? $formatCompactBdt($val) : number_format($units) . ' pcs',
                  'units' => $units,
                  'skus' => (int) $row->sku_count,
                  'colorHex' => $color,
                  'shareData' => $shareSeries,
                  'volumeData' => $volumeSeries,
              ];
          })->all();
      } elseif ($totalRegisteredBrands > 0) {
          $dynamicBrandShares = [];
      }

  } catch (\Throwable $e) {
      $todaySales = 0;
      $salesChangePct = 0;
      $todayInvoicesCount = 0;
      $todayCollection = 0;
      $collectionChangePct = 0;
      $totalReceivableDue = 0;
      $overdueReceivable = 0;
      $activeRetailersCount = 0;
      $totalStockUnits = 0;
      $totalStockValue = 0;
      $primaryWarehouse = 'Central Warehouse';
      $todayDispatched = 0;
      $todayPlaced = 0;
      $todayInTransit = 0;
      $activeRoutes = 1;
      $todaySalesCaption = 'No sales recorded yet';
      $todayCollectionCaption = 'No collections today';
      $retailerDuesCaption = '0 Retailers';
      $stockValueCaption = '0 Units registered';
      $dynamicAlertItems = [];
      $dynamicChartPeriods = null;
      $dynamicRecentInvoices = [];
      $totalRegisteredBrands = 0;
      $dynamicBrandShares = [];
  }

  $hour = (int) now()->format('H');
  $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
  $userName = auth()->user()?->name ?? 'Admin';
@endphp

@section('content')
  <div class="space-y-6">

    {{-- Greeting & Quick Add Shortcuts --}}
    <div class="enter mv-down flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-1" style="--i:0">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            {{ $greeting }}, {{ $userName }}!
          </h1>
          <span class="text-xl sm:text-2xl select-none">👋</span>
        </div>
        <p class="mt-1 text-xs sm:text-sm text-muted">
          Here is what is happening across your distribution network today, <span class="font-medium text-ink">{{ now()->format('l, d M Y') }}</span>.
        </p>
      </div>

      {{-- Right Side: Quick Add Shortcut Buttons --}}
      <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
        {{-- Add Product Shortcut --}}
        <a href="{{ route('products.create') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500">
          <x-icon name="plus" class="size-3.5 text-muted" />
          <span>Add Product</span>
        </a>

        {{-- Primary Action: Transfer / Dispatch --}}
        <a href="{{ route('stock-transfers.create') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-full bg-brand-500 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600">
          <x-icon name="plus" class="size-3.5" />
          <span>New Transfer</span>
        </a>

        {{-- More Quick Actions Dropdown --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
          <button type="button"
                  @click="open = !open"
                  class="btn-scale grid size-9 place-items-center rounded-full border border-line bg-surface text-muted shadow-2xs hover:bg-soft hover:text-ink"
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
            <a href="{{ route('customers.create') }}"
               class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
              <x-icon name="store" class="size-4 text-muted" />
              <span>Add Retailer</span>
            </a>
            <a href="{{ route('stock-adjustments.create') }}"
               class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
              <x-icon name="box" class="size-4 text-muted" />
              <span>Stock Adjustment</span>
            </a>
            <a href="{{ route('suppliers.create') }}"
               class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
              <x-icon name="truck" class="size-4 text-muted" />
              <span>Add Supplier</span>
            </a>
            <a href="{{ route('pos.index') }}"
               class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-ink transition-colors hover:bg-soft hover:text-brand-500">
              <x-icon name="cart" class="size-4 text-muted" />
              <span>POS Terminal</span>
            </a>
          </div>
        </div>
      </div>
    </div>

    {{-- 1. Top 4 Metric KPI Stat Cards --}}
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
        <x-dashboard.stat-card 
          title="Today's Collection" 
          :amount="$todayCollection" 
          :change="$collectionChangePct" 
          :caption="$todayCollectionCaption" 
          icon="wallet"
          class="rv mv-blur lift"
          style="--i:2; --stagger:160ms; --d:80ms"
        />
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Retailer Dues (Receivable)" 
          :amount="$totalReceivableDue" 
          :change="0" 
          :caption="$retailerDuesCaption" 
          icon="store"
          class="rv mv-blur lift"
          style="--i:3; --stagger:160ms; --d:80ms"
        />
      </div>
      <div class="flex flex-col">
        <x-dashboard.stat-card 
          title="Total Stock Value" 
          :amount="$totalStockValue" 
          :change="0" 
          :caption="$stockValueCaption" 
          icon="box"
          class="rv mv-blur lift"
          style="--i:4; --stagger:160ms; --d:80ms"
        />
      </div>
    </div>

    {{-- 2. Middle Row: Sales vs. Collection Trend (2 Cols) + SR Field Distribution (1 Col) --}}
    <div class="grid gap-5 xl:grid-cols-3 items-stretch">
      <div class="xl:col-span-2 flex flex-col">
        @if ($dynamicChartPeriods)
          <x-dashboard.sales-chart class="rv" style="--i:5; --stagger:130ms; --d:100ms" :periods="$dynamicChartPeriods" />
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

    {{-- 3. Third Row: Low Stock & Reorder Alerts (50%) + Brand Sales Share (50%) --}}
    <div class="grid gap-5 lg:grid-cols-2 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.stock-alerts class="rv" style="--i:2; --d:40ms" :items="$dynamicAlertItems" />
      </div>
      <div class="flex flex-col">
        @if ($dynamicBrandShares !== null)
          <x-dashboard.brand-share-chart
            class="rv"
            style="--i:2; --d:40ms"
            :brands="$dynamicBrandShares"
            :totalRegisteredBrands="$totalRegisteredBrands"
            :warehouseName="$primaryWarehouse"
            volumeScale="raw"
          />
        @else
          <x-dashboard.brand-share-chart
            class="rv"
            style="--i:2; --d:40ms"
            :warehouseName="$primaryWarehouse"
          />
        @endif
      </div>
    </div>

    {{-- 4. Bottom Row: Recent Retailer Invoices & Orders --}}
    <div>
      <x-dashboard.recent-invoices class="rv" style="--i:1; --d:40ms" :invoices="$dynamicRecentInvoices" />
    </div>

  </div>
@endsection
