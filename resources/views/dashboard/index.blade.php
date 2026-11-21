@extends('layouts.app')

@section('title', 'Distribution Dashboard — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Distribution Dashboard')

@php
  // ── 1. Role & Outlet Scope Resolution ──────────────────────────────────────
  $user = auth()->user();
  $roles = $user ? $user->roles->pluck('name')->all() : [];
  $primaryRole = !empty($roles) ? ucfirst(str_replace(['_', '-'], ' ', $roles[0])) : 'Staff';

  // STRICT ACCESS RULE: ONLY Super Admin can view all branches and network overview.
  // All other roles (Manager, Outlet Manager, Sales Staff, Storekeeper, etc.) are strictly locked to their outlet.
  $isSuperAdmin = $user?->hasRole('Super Admin') ?? false;
  $isRestricted = ! $isSuperAdmin;

  // Resolve user assigned outlet / branch
  $assignedLocationId = $user?->branch_id ?? $user?->location_id;
  $assignedLocation = $assignedLocationId ? \App\Models\Location::find($assignedLocationId) : null;

  // If a non-Super Admin account does not have an assigned branch yet, fallback to primary Outlet 1
  // so they NEVER view cross-outlet or network-wide data!
  if ($isRestricted && ! $assignedLocation) {
      $assignedLocation = \App\Models\Location::where('status', 'active')
          ->where('type', '!=', \App\Enums\LocationType::Warehouse->value ?? 'warehouse')
          ->orderBy('id')
          ->first()
          ?? \App\Models\Location::where('status', 'active')->orderBy('id')->first();
      $assignedLocationId = $assignedLocation?->id;
  }

  // All active business locations for Super Admin switcher
  $allLocations = $isSuperAdmin
      ? \App\Models\Location::where('status', 'active')->orderBy('name')->get(['id', 'name', 'type'])
      : collect();

  // Resolve active scope
  if ($isRestricted) {
      // Non-Super Admin: permanently locked to their outlet (or Outlet 1 fallback)
      $scopedLocationId = $assignedLocationId ? (int) $assignedLocationId : -1;
      $activeScopeLocation = $assignedLocation;
  } else {
      // Super Admin: can view all network or filter to any individual outlet
      $requestedLocationId = request()->filled('location_id') ? (int) request('location_id') : null;
      if ($requestedLocationId && $allLocations->contains('id', $requestedLocationId)) {
          $scopedLocationId = $requestedLocationId;
          $activeScopeLocation = $allLocations->firstWhere('id', $requestedLocationId);
      } else {
          $scopedLocationId = null;
          $activeScopeLocation = null;
      }
  }

  $scopeTitle = $activeScopeLocation ? $activeScopeLocation->name : 'All Outlets & Warehouses';

  // ── 2. Safe Dynamic Data Resolution ────────────────────────────────────────
  try {
      // 2.1 Live Sales Query (Scoped to location if active)
      $baseSalesQuery = \App\Models\SalesInvoice::query();
      if ($scopedLocationId) {
          $baseSalesQuery->where('location_id', $scopedLocationId);
      }

      $todaySales = (float) (clone $baseSalesQuery)
          ->whereDate('invoice_date', today())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('grand_total');

      $yesterdaySales = (float) (clone $baseSalesQuery)
          ->whereDate('invoice_date', today()->subDay())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('grand_total');

      $salesChangePct = $yesterdaySales > 0
          ? (int) round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100)
          : ($todaySales > 0 ? 100 : 0);

      $todayInvoicesCount = (int) (clone $baseSalesQuery)
          ->whereDate('invoice_date', today())
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->count();

      // 2.2 Collections (Scoped payments on sales invoices)
      $todayCollection = (float) (clone $baseSalesQuery)
          ->whereDate('invoice_date', today())
          ->sum('paid_amount');

      $yesterdayCollection = (float) (clone $baseSalesQuery)
          ->whereDate('invoice_date', today()->subDay())
          ->sum('paid_amount');

      $collectionChangePct = $yesterdayCollection > 0
          ? (int) round((($todayCollection - $yesterdayCollection) / $yesterdayCollection) * 100)
          : ($todayCollection > 0 ? 100 : 0);

      // 2.3 Retailer Dues (Total Outstanding Receivables on posted invoices)
      $totalReceivableDue = (float) (clone $baseSalesQuery)
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->sum('due_amount');

      $overdueReceivable = (float) (clone $baseSalesQuery)
          ->where('status', \App\Enums\SalesInvoiceStatus::Posted)
          ->whereDate('invoice_date', '<', today()->subDays(30))
          ->where('due_amount', '>', 0)
          ->sum('due_amount');

      // Scoped active retailers count
      $activeRetailersCount = $scopedLocationId
          ? (int) \App\Models\SalesInvoice::where('location_id', $scopedLocationId)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id')
          : (int) \App\Models\Customer::where('status', 'active')->count();

      // 2.4 Stock Valuation & Inventory Units
      // Main Super Admin / Network view includes all branches; individual branch scope shows branch-only stock.
      $isAllBranchesStock = ! $scopedLocationId 
          || ($activeScopeLocation && (
              $activeScopeLocation->name === 'Main Warehouse' 
              || ($activeScopeLocation->type?->value ?? $activeScopeLocation->type) === 'warehouse'
          ));

      if ($isAllBranchesStock) {
          $totalStockUnits = (int) \App\Models\Unit::sum('quantity');

          $rawStockValue = \Illuminate\Support\Facades\DB::table('units')
              ->join('products', 'units.product_id', '=', 'products.id')
              ->whereNull('products.deleted_at')
              ->whereNull('units.deleted_at')
              ->selectRaw('SUM(units.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as total_val')
              ->value('total_val');
          $totalStockValue = (float) ($rawStockValue ?? 0);

          $primaryWarehouse = $activeScopeLocation?->name ?? (
              \App\Models\Location::where('status', 'active')
                  ->where('type', \App\Enums\LocationType::Warehouse->value ?? 'warehouse')
                  ->value('name') ?? 'Main Warehouse'
          );
          $stockScopeCaption = 'All branches included';
      } else {
          $locationStockMovements = \App\Models\StockMovement::where('location_id', $scopedLocationId)->sum('quantity');
          $totalStockUnits = max(0, (int) $locationStockMovements);

          $rawStockValue = \Illuminate\Support\Facades\DB::table('stock_movements')
              ->join('units', 'stock_movements.unit_id', '=', 'units.id')
              ->join('products', 'units.product_id', '=', 'products.id')
              ->where('stock_movements.location_id', $scopedLocationId)
              ->whereNull('products.deleted_at')
              ->whereNull('units.deleted_at')
              ->selectRaw('SUM(stock_movements.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as total_val')
              ->value('total_val');
          $totalStockValue = max(0, (float) ($rawStockValue ?? 0));
          $primaryWarehouse = $activeScopeLocation->name;
          $stockScopeCaption = $primaryWarehouse;
      }

      // 2.4.1 Day-over-Day (DoD) Stock Value % Change
      // Compares current stock value against yesterday's closing value based on today's net inventory movements.
      $todayMovementsQuery = \Illuminate\Support\Facades\DB::table('stock_movements')
          ->join('units', 'stock_movements.unit_id', '=', 'units.id')
          ->join('products', 'units.product_id', '=', 'products.id')
          ->whereDate('stock_movements.created_at', today())
          ->whereNull('products.deleted_at')
          ->whereNull('units.deleted_at');

      if (! $isAllBranchesStock && $scopedLocationId) {
          $todayMovementsQuery->where('stock_movements.location_id', $scopedLocationId);
      }

      $todayStockDelta = (float) ($todayMovementsQuery->selectRaw(
          'SUM(stock_movements.quantity * COALESCE(products.purchase_price, products.sale_price, 0)) as val_delta'
      )->value('val_delta') ?? 0);

      $stockValueChangePct = 0;
      if ($todayStockDelta != 0) {
          $yesterdayStockValue = $totalStockValue - $todayStockDelta;
          if ($yesterdayStockValue > 0) {
              $stockValueChangePct = (int) round(($todayStockDelta / $yesterdayStockValue) * 100);
          } else {
              $stockValueChangePct = $todayStockDelta > 0 ? 100 : -100;
          }
      }

      // 2.5 Stock Transfers & Field Placement
      $transferQuery = \App\Models\StockTransfer::whereDate('transfer_date', today());
      if ($scopedLocationId) {
          $transferQuery->where(function ($q) use ($scopedLocationId) {
              $q->where('from_location_id', $scopedLocationId)
                ->orWhere('to_location_id', $scopedLocationId);
          });
      }

      $todayPlaced = (int) (clone $transferQuery)
          ->where('stock_transfers.status', \App\Enums\StockTransferStatus::Received->value)
          ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
          ->sum('stock_transfer_items.quantity');

      $todayInTransit = (int) (clone $transferQuery)
          ->where('stock_transfers.status', \App\Enums\StockTransferStatus::InTransit->value)
          ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
          ->sum('stock_transfer_items.quantity');

      $todayDispatched = $todayPlaced + $todayInTransit;
      $activeRoutes = $scopedLocationId ? 1 : (int) \App\Models\Location::where('status', 'active')->count();

      // Dynamic captions
      $todaySalesCaption = $todayInvoicesCount > 0
          ? $todayInvoicesCount . ' ' . \Illuminate\Support\Str::plural('Invoice', $todayInvoicesCount) . ' Posted Today'
          : ($todayDispatched > 0 ? $todayDispatched . ' Handsets Dispatched' : 'No sales posted today');

      $todayCollectionCaption = $todayCollection > 0
          ? 'Collected today at ' . ($activeScopeLocation ? $activeScopeLocation->name : 'outlets')
          : 'No collections today';

      $retailerDuesCaption = $overdueReceivable > 0
          ? 'Overdue ৳' . number_format($overdueReceivable) . ' · ' . $activeRetailersCount . ' Retailers'
          : ($activeRetailersCount . ' Active ' . \Illuminate\Support\Str::plural('Retailer', $activeRetailersCount));

      $stockValueCaption = $totalStockUnits > 0
          ? number_format($totalStockUnits) . ' Units · ' . $stockScopeCaption
          : '0 units registered';

      // 2.6 Dynamic Low Stock & Reorder Alerts
      $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 10);
      if ($scopedLocationId) {
          $locationStockSub = \Illuminate\Support\Facades\DB::table('stock_movements')
              ->join('units', 'stock_movements.unit_id', '=', 'units.id')
              ->where('stock_movements.location_id', $scopedLocationId)
              ->groupBy('units.product_id')
              ->selectRaw('units.product_id, SUM(stock_movements.quantity) as loc_stock');

          $lowStockProducts = \App\Models\Product::with(['brand'])
              ->joinSub($locationStockSub, 'loc_stock_tbl', function ($join) {
                  $join->on('products.id', '=', 'loc_stock_tbl.product_id');
              })
              ->where('products.status', 'active')
              ->where('loc_stock_tbl.loc_stock', '<=', $lowStockThreshold)
              ->orderBy('loc_stock_tbl.loc_stock', 'asc')
              ->take(5)
              ->select('products.*', 'loc_stock_tbl.loc_stock as location_quantity')
              ->get();

          $dynamicAlertItems = $lowStockProducts->map(function ($p) use ($lowStockThreshold) {
              $stock = max(0, (int) ($p->location_quantity ?? 0));
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
      } else {
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
      }

      // 2.7 Dynamic Sales & Collection Trend Chart Data
      $start7d = today()->subDays(6);
      $invoices7d = (clone $baseSalesQuery)
          ->whereDate('invoice_date', '>=', $start7d)
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
      $invoices30d = (clone $baseSalesQuery)
          ->whereDate('invoice_date', '>=', $start30d)
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
      $invoicesYear = (clone $baseSalesQuery)
          ->whereYear('invoice_date', now()->year)
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

      // 2.8 Dynamic Recent Invoices (Scoped to outlet/location)
      $recentInvoicesList = (clone $baseSalesQuery)
          ->with(['customer', 'items.unit', 'creator', 'location'])
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
              'location' => filled($customer?->city) ? $customer->city : ($inv->location?->name ?? 'Outlet'),
              'items' => $itemsSummary,
              'sr' => $inv->creator?->name ?? 'Staff',
              'amount' => (float) $inv->grand_total,
              'status' => $inv->payment_status?->value ?? 'due',
              'date' => $inv->invoice_date ? $inv->invoice_date->format('d M, h:i A') : 'Today',
              'url' => route('sales-invoices.show', $inv),
          ];
      })->all();

      // 2.9 Stocked Brands Share
      $totalRegisteredBrands = (int) \App\Models\Brand::query()->count();
      if ($scopedLocationId) {
          $stockedBrandRows = \Illuminate\Support\Facades\DB::table('brands')
              ->join('products', function ($join) {
                  $join->on('products.brand_id', '=', 'brands.id')
                      ->whereNull('products.deleted_at');
              })
              ->join('units', function ($join) {
                  $join->on('units.product_id', '=', 'products.id')
                      ->whereNull('units.deleted_at');
              })
              ->join('stock_movements', function ($join) use ($scopedLocationId) {
                  $join->on('stock_movements.unit_id', '=', 'units.id')
                      ->where('stock_movements.location_id', '=', $scopedLocationId);
              })
              ->whereNull('brands.deleted_at')
              ->groupBy('brands.id', 'brands.name')
              ->havingRaw('SUM(stock_movements.quantity) > 0')
              ->selectRaw('brands.id, brands.name, SUM(stock_movements.quantity) as stock_units, COUNT(DISTINCT products.id) as sku_count, SUM(stock_movements.quantity * COALESCE(products.sale_price, products.purchase_price, 0)) as stock_value')
              ->orderByDesc('stock_value')
              ->orderByDesc('stock_units')
              ->get();
      } else {
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
      }

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
      $stockValueChangePct = 0;
      $primaryWarehouse = 'Central Warehouse';
      $todayDispatched = 0;
      $todayPlaced = 0;
      $todayInTransit = 0;
      $activeRoutes = 1;
      $todaySalesCaption = 'No sales recorded';
      $todayCollectionCaption = 'No collections';
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
            Showing live metrics for <strong class="text-ink">{{ $activeScopeLocation->name }}</strong> on {{ now()->format('l, d M Y') }}.
          @else
            Consolidated network overview across all distribution outlets and warehouses on {{ now()->format('l, d M Y') }}.
          @endif
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

    {{-- ── 3. Third Row: Low Stock Alerts (50%) + Brand Sales Share (50%) ── --}}
    <div class="grid gap-5 lg:grid-cols-2 items-stretch">
      <div class="flex flex-col">
        <x-dashboard.stock-alerts 
          class="rv" 
          style="--i:2; --d:40ms" 
          :title="$activeScopeLocation ? 'Low Stock Alerts (' . $activeScopeLocation->name . ')' : 'Low Stock & Reorder Alerts'"
          :items="$dynamicAlertItems" 
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

    {{-- ── 4. Bottom Row: Recent Invoices & Orders (Scoped) ── --}}
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
