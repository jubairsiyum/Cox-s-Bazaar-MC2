@extends('layouts.app')

@section('title', 'Stock Levels — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Levels')

@section('content')
@php
    $sortLink = fn (string $col) => route('inventory.index', array_filter([
        'search' => request('search'),
        'brand_id' => request('brand_id'),
        'category_id' => request('category_id'),
        'stock_status' => request('stock_status'),
        'branch' => request('branch'),
        'location_id' => request('location_id'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));

    $statusFilterLink = fn (?string $status) => route('inventory.index', array_filter([
        'search' => request('search'),
        'brand_id' => request('brand_id'),
        'category_id' => request('category_id'),
        'stock_status' => $status,
        'branch' => request('branch'),
        'location_id' => request('location_id'),
        'per_page' => request('per_page'),
        'sort' => request('sort'),
        'direction' => request('direction'),
    ]));

    $totalSkus = (int) ($summary->skus ?? 0);
    $totalQty = (int) ($summary->total_quantity ?? 0);
    $totalValue = (float) ($summary->stock_value ?? 0);
    $lowStockCount = (int) ($summary->low_stock ?? 0);
    $outOfStockCount = (int) ($summary->out_of_stock ?? 0);
    $healthyStockCount = max(0, $totalSkus - $lowStockCount - $outOfStockCount);
    $healthyPct = $totalSkus > 0 ? (int) round(($healthyStockCount / $totalSkus) * 100) : 0;
    $maxQtyOnPage = max(1, (int) ($stockLevels->max('quantity') ?? 1), $threshold * 3);
    $activeStatus = (string) request('stock_status', '');
    $hasActiveFilters = request()->filled('search') || request()->filled('brand_id') || request()->filled('category_id') || request()->filled('stock_status');
    $branchFilter = auth()->user()?->hasRole('Super Admin') && request()->filled('branch')
        ? (int) request('branch')
        : null;
    $branchFilterName = $branchFilter !== null
        ? ($branches->firstWhere('id', $branchFilter)?->name ?? 'the selected branch')
        : null;
@endphp

<div x-data="ledgerModal()" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Stock Levels</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Stock Levels</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($totalSkus) }} {{ Str::plural('SKU', $totalSkus) }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-[11px] font-medium text-muted border border-line">
                    <x-icon name="alert-triangle" class="size-3.5 text-warning" />
                    <span>Low-stock threshold ≤ {{ $threshold }}</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Monitor live stock quantities, inventory valuation in BDT, low-stock alerts, and unit movement ledgers.</p>
        </div>

        {{-- Right Quick Actions --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if($hasActiveFilters || request('sort'))
                <a href="{{ route('inventory.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\StockAdjustment::class)
                <a href="{{ route('stock-adjustments.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Adjustment</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Inventory Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Stock Units --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Stock Units</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalQty) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                    {{ number_format($healthyStockCount) }} healthy
                </span>
                <span>across {{ number_format($totalSkus) }} SKUs</span>
            </div>
        </div>

        {{-- KPI 2: Stock Value --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Stock Value</span>
                    <p class="text-xl sm:text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums money truncate" title="{{ \App\Helpers\Money::format($totalValue) }}">
                        {{ \App\Helpers\Money::format($totalValue) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-success transition-all duration-500" style="width: {{ $healthyPct }}%;"></div>
                </div>
                <p class="text-xs text-muted">
                    <strong class="text-ink font-semibold">{{ $healthyPct }}%</strong> of SKUs at healthy level
                </p>
            </div>
        </div>

        {{-- KPI 3: Low Stock Alerts --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Low Stock Alerts</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-warning mt-1.5 tabular-nums">
                        {{ number_format($lowStockCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>Qty between 1 and {{ $threshold }}</span>
                <a href="{{ $statusFilterLink('low') }}" class="font-semibold text-warning hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>View</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 4: Out of Stock --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Out of Stock</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-danger mt-1.5 tabular-nums">
                        {{ number_format($outOfStockCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-icon name="smartphone" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>Requires immediate restock</span>
                <a href="{{ $statusFilterLink('out') }}" class="font-semibold text-danger hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Inspect</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>
    </div>

    {{-- Main Stock Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Stock Status Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Stock overview</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ $stockLevels->count() }} of {{ number_format($stockLevels->total()) }} variants · Sorted by <span class="font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($sort)) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            {{-- Server-Backed Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ $statusFilterLink(null) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $activeStatus === '' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>All</span>
                </a>
                <a href="{{ $statusFilterLink('in') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $activeStatus === 'in' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span class="size-1.5 rounded-full {{ $activeStatus === 'in' ? 'bg-on-brand' : 'bg-success' }}"></span>
                    <span>In Stock</span>
                </a>
                <a href="{{ $statusFilterLink('low') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $activeStatus === 'low' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span class="size-1.5 rounded-full {{ $activeStatus === 'low' ? 'bg-on-brand' : 'bg-warning' }}"></span>
                    <span>Low Stock</span>
                </a>
                <a href="{{ $statusFilterLink('out') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $activeStatus === 'out' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span class="size-1.5 rounded-full {{ $activeStatus === 'out' ? 'bg-on-brand' : 'bg-danger' }}"></span>
                    <span>Out of Stock</span>
                </a>
            </div>
        </div>

        {{-- Location Scope Strip (branch/location-aware activity) --}}
        <div class="flex flex-col gap-3 border-b border-line px-5 sm:px-6 py-3.5 bg-brand-500/5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="warehouse" class="size-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-ink">
                        @if($locationFilter !== null)
                            Activity scoped to {{ $locations->firstWhere('id', $locationFilter)?->name ?? 'your location' }}
                        @elseif($branchFilter !== null)
                            Activity scoped to {{ $branchFilterName }} branch
                        @else
                            Activity across all locations
                        @endif
                    </p>
                    <p class="text-[11px] text-muted">Stock totals are shared across locations (one row per product variant); the ledger below shows the selected scope.</p>
                </div>
            </div>

            @if(auth()->user()?->hasRole('Super Admin'))
                <form method="GET" action="{{ route('inventory.index') }}" class="flex items-center gap-2 shrink-0">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                    @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                    @if(request('stock_status'))<input type="hidden" name="stock_status" value="{{ request('stock_status') }}">@endif
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <select name="branch" onchange="this.form.submit()"
                            class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchFilter === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="location_id" onchange="this.form.submit()"
                            class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All locations</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationFilter === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- Filter Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <form method="GET" action="{{ route('inventory.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                @if(request('branch'))<input type="hidden" name="branch" value="{{ request('branch') }}">@endif
                @if(request('location_id'))<input type="hidden" name="location_id" value="{{ request('location_id') }}">@endif

                {{-- Search --}}
                <div class="sm:col-span-2 lg:col-span-4">
                    <label for="inv-search" class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">Search</label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input id="inv-search" type="text" name="search" value="{{ request('search') }}"
                               placeholder="Product name, code, barcode..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-3 py-2 text-xs text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                    </div>
                </div>

                {{-- Brand --}}
                <div class="lg:col-span-2">
                    <label for="inv-brand" class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">Brand</label>
                    <select id="inv-brand" name="brand_id"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All Brands</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Category --}}
                <div class="lg:col-span-2">
                    <label for="inv-category" class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">Category</label>
                    <select id="inv-category" name="category_id"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stock Status --}}
                <div class="lg:col-span-2">
                    <label for="inv-status" class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">Stock Status</label>
                    <select id="inv-status" name="stock_status"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All</option>
                        <option value="in" @selected(request('stock_status') === 'in')>In Stock</option>
                        <option value="low" @selected(request('stock_status') === 'low')>Low Stock</option>
                        <option value="out" @selected(request('stock_status') === 'out')>Out of Stock</option>
                    </select>
                </div>

                {{-- Per Page & Submit --}}
                <div class="sm:col-span-2 lg:col-span-2 flex items-center gap-2">
                    <div class="w-20 shrink-0">
                        <label for="inv-per-page" class="block text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">Show</label>
                        <select id="inv-per-page" name="per_page"
                                class="w-full rounded-xl border border-line bg-surface px-2.5 py-2 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                            <option value="100" @selected($perPage == 100)>100</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="btn-scale flex-1 mt-4 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-3.5 py-2 text-xs font-semibold text-on-brand shadow-xs transition-colors cursor-pointer">
                        <span>Filter</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse" id="stock-levels-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('product_name') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Product</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'product_name' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Brand & Category</th>
                        <th scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Unit</th>
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('quantity') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Quantity</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'quantity' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-end">
                            <a href="{{ $sortLink('unit_cost') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Unit Cost</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'unit_cost' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-end">
                            <a href="{{ $sortLink('stock_value') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Stock Value</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'stock_value' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($stockLevels as $stockLevel)
                        @php
                            $stockValue = $stockLevel->quantity * (float) ($stockLevel->unit_cost ?? 0);
                            $brandName = $stockLevel->product?->brand?->name;
                            $categoryName = $stockLevel->product?->category?->name;
                            $isOut = $stockLevel->quantity <= 0;
                            $isLow = !$isOut && $stockLevel->isLowStock();
                            $qtyBarPct = $isOut ? 0 : min(100, max(8, (int) round(($stockLevel->quantity / $maxQtyOnPage) * 100)));
                            $qtyBarColor = $isOut ? 'bg-danger' : ($isLow ? 'bg-warning' : 'bg-success');
                            $qtyTextColor = $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-ink');
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            {{-- Product with Brand Logo --}}
                            <td class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    @if($brandName)
                                        <x-brand-logo :name="$brandName" size="sm" />
                                    @else
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-soft text-muted border border-line">
                                            <x-icon name="smartphone" class="size-4" />
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                            {{ $stockLevel->displayName() }}
                                        </span>
                                        <span class="block text-[11px] text-muted tabular-nums">
                                            {{ $stockLevel->product?->product_id ?: 'SKU #' . $stockLevel->id }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Brand & Category --}}
                            <td class="py-4 px-5 text-start">
                                <span class="block text-xs font-semibold text-ink">{{ $brandName ?? '—' }}</span>
                                <span class="block text-[11px] text-muted">{{ $categoryName ?? 'Uncategorized' }}</span>
                            </td>

                            {{-- Unit --}}
                            <td class="py-4 px-5 text-start">
                                <span class="inline-flex items-center rounded-lg bg-soft px-2 py-0.5 text-xs font-semibold text-ink border border-line">
                                    {{ $stockLevel->unit }}
                                </span>
                            </td>

                            {{-- Quantity + Visual Meter --}}
                            <td class="py-4 px-5 text-start">
                                <div class="flex flex-col gap-1.5 min-w-28 max-w-36">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-sm font-bold tabular-nums {{ $qtyTextColor }}">
                                            {{ number_format($stockLevel->quantity) }}
                                        </span>
                                        <span class="text-[10px] text-muted">{{ $stockLevel->unit }}</span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                        <div class="h-full rounded-full {{ $qtyBarColor }} transition-all duration-300" style="width: {{ $qtyBarPct }}%;"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Unit Cost --}}
                            <td class="py-4 px-5 text-end text-sm text-muted tabular-nums money whitespace-nowrap">
                                {{ \App\Helpers\Money::format($stockLevel->unit_cost ?? 0) }}
                            </td>

                            {{-- Stock Value --}}
                            <td class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap">
                                {{ \App\Helpers\Money::format($stockValue) }}
                            </td>

                            {{-- Status Badge (Exact text preserved for tests) --}}
                            <td class="py-4 px-5 text-start whitespace-nowrap">
                                @if($isOut)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 px-2.5 py-1 text-xs font-semibold text-danger ring-1 ring-danger/20">
                                        <span class="size-1.5 rounded-full bg-danger"></span>
                                        Out of stock
                                    </span>
                                @elseif($isLow)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning ring-1 ring-warning/20">
                                        <span class="size-1.5 rounded-full bg-warning"></span>
                                        Low stock
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success ring-1 ring-success/20">
                                        <span class="size-1.5 rounded-full bg-success"></span>
                                        In stock
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('create', App\Models\StockAdjustment::class)
                                        <a href="{{ route('stock-adjustments.create', ['unit_id' => $stockLevel->id]) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Adjust Stock</span>
                                        </a>
                                    @endcan
                                    <button type="button"
                                            @click="openLedger({{ $stockLevel->id }}, @js($stockLevel->displayName()), {{ (int) $stockLevel->quantity }}, @js($stockLevel->unit))"
                                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-brand-500/30 bg-brand-500/10 px-3 py-1.5 text-xs font-semibold text-brand-500 hover:bg-brand-500 hover:text-on-brand transition-colors cursor-pointer">
                                        <x-svg.time class="size-3.5" />
                                        <span>Ledger</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="box" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No stock found.</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if($hasActiveFilters)
                                            No product variants match your current search or filter criteria.
                                        @else
                                            Add products and purchase inventory to start tracking stock levels.
                                        @endif
                                    </p>
                                    @if($hasActiveFilters)
                                        <a href="{{ route('inventory.index') }}"
                                           class="mt-4 rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                            Reset Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $stockLevels->links() }}
        </div>
    </div>

    {{-- Scoped Stock Activity (branch/location-filtered ledger) --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Recent stock activity</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($locationFilter !== null)
                        Ledger movements at {{ $locations->firstWhere('id', $locationFilter)?->name ?? 'the selected location' }}
                    @elseif($branchFilter !== null)
                        Ledger movements for {{ $branchFilterName }} branch
                    @else
                        Ledger movements across all locations
                    @endif
                    · latest {{ $scopedMovements->count() }} entries
                </p>
            </div>
            @can('stock-adjustments.manage')
                <a href="{{ route('stock-adjustments.create') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors shrink-0">
                    <x-icon name="plus" class="size-3.5" />
                    <span>New Adjustment</span>
                </a>
            @endcan
        </div>

        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3 px-5 text-start">Date</th>
                        <th scope="col" class="py-3 px-5 text-start">Product</th>
                        <th scope="col" class="py-3 px-5 text-start">Type</th>
                        <th scope="col" class="py-3 px-5 text-end">Quantity</th>
                        <th scope="col" class="py-3 px-5 text-start">Location</th>
                        <th scope="col" class="py-3 px-5 text-start">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($scopedMovements as $movement)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3 px-5 text-xs text-muted whitespace-nowrap tabular-nums">
                                {{ $movement->created_at->format('d M Y H:i') }}
                            </td>
                            <td class="py-3 px-5 text-xs font-semibold text-ink">
                                {{ $movement->unit?->displayName() ?? '—' }}
                            </td>
                            <td class="py-3 px-5 text-xs text-ink whitespace-nowrap">
                                {{ $movement->type->label() }}
                            </td>
                            <td class="py-3 px-5 text-xs font-bold text-end tabular-nums whitespace-nowrap {{ (int) $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">
                                {{ (int) $movement->quantity > 0 ? '+' : '' }}{{ number_format((int) $movement->quantity) }}
                            </td>
                            <td class="py-3 px-5 text-xs text-muted whitespace-nowrap">
                                {{ $movement->location?->name ?? '—' }}
                            </td>
                            <td class="py-3 px-5 text-xs text-muted max-w-xs truncate" title="{{ $movement->note }}">
                                {{ $movement->note ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 px-6 text-center text-xs text-muted">
                                No stock movements recorded for this scope yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stock Movements History Modal / Drawer --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="modalOpen = false">
        <div class="w-full max-w-3xl rounded-3xl border border-line bg-surface shadow-card overflow-hidden my-8"
             @click.stop
             x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-5 bg-surface">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-svg.time class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-lg font-bold text-ink truncate">
                            Stock Ledger — <span x-text="productName"></span>
                        </h3>
                        <p class="text-xs text-muted mt-0.5">
                            Latest 50 stock movements · Current balance:
                            <strong class="text-ink tabular-nums" x-text="currentStock + ' ' + unitLabel"></strong>
                        </p>
                    </div>
                </div>
                <button type="button" @click="modalOpen = false"
                        class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                        aria-label="Close stock ledger">
                    <x-svg.x class="size-5" />
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="p-6 bg-canvas/20 max-h-[70vh] overflow-y-auto custom-scrollbar">
                {{-- Loading State --}}
                <div x-show="loading" class="py-12 text-center">
                    <div class="inline-flex items-center gap-2.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-muted shadow-2xs">
                        <span class="size-3.5 rounded-full border-2 border-brand-500 border-t-transparent animate-spin"></span>
                        <span>Loading stock movement history...</span>
                    </div>
                </div>

                {{-- Movements Table --}}
                <div x-show="!loading" class="rounded-2xl border border-line bg-surface overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-line bg-canvas/40 text-[11px] font-bold text-muted uppercase tracking-wider">
                                    <th class="py-3 px-4">Date</th>
                                    <th class="py-3 px-4">Type</th>
                                    <th class="py-3 px-4 text-end">Change</th>
                                    <th class="py-3 px-4 text-end">Balance After</th>
                                    <th class="py-3 px-4">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-surface">
                                <template x-for="(movement, index) in movements" :key="index">
                                    <tr class="hover:bg-soft/40 transition-colors">
                                        <td class="py-3 px-4 text-xs text-muted whitespace-nowrap tabular-nums" x-text="movement.date"></td>
                                        <td class="py-3 px-4 text-xs font-semibold text-ink whitespace-nowrap" x-text="movement.type"></td>
                                        <td class="py-3 px-4 text-end whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums"
                                                  :class="movement.quantity >= 0 ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20'"
                                                  x-text="(movement.quantity > 0 ? '+' : '') + movement.quantity"></span>
                                        </td>
                                        <td class="py-3 px-4 text-xs font-bold text-ink text-end tabular-nums" x-text="movement.quantity_after"></td>
                                        <td class="py-3 px-4 text-xs text-muted" x-text="movement.note || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="movements.length === 0">
                                    <td colspan="5" class="py-10 px-6 text-center text-xs font-medium text-muted">
                                        No movements recorded for this product variant.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between gap-3 border-t border-line px-6 py-4 bg-surface">
                <span class="text-xs text-muted" x-show="!loading" x-text="movements.length + ' movement entries shown'"></span>
                <button type="button" @click="modalOpen = false"
                        class="btn-scale ml-auto rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function ledgerModal() {
    return {
        modalOpen: false,
        loading: false,
        productName: '',
        currentStock: 0,
        unitLabel: '',
        movements: [],

        async openLedger(unitId, productName, currentStock = 0, unitLabel = '') {
            this.productName = productName;
            this.currentStock = currentStock;
            this.unitLabel = unitLabel;
            this.movements = [];
            this.loading = true;
            this.modalOpen = true;

            try {
                const response = await fetch(`/inventory/${unitId}/movements?${new URLSearchParams(window.location.search)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.movements = data.items || [];
            } catch (error) {
                this.movements = [];
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endsection
