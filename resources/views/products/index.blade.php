@extends('layouts.app')

@section('title', 'Products List — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Products List')

@section('content')
@php
    // Single lightweight aggregate query for catalog KPIs (zero N+1)
    $productTotals = \App\Models\Product::query()
        ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count, COALESCE(AVG(NULLIF(sale_price, 0)), 0) as avg_sale_price, COUNT(DISTINCT brand_id) as brand_count, COUNT(DISTINCT category_id) as category_count")
        ->first();

    $allProductsCount = (int) ($productTotals->total_count ?? 0);
    $activeProductsCount = (int) ($productTotals->active_count ?? 0);
    $inactiveProductsCount = max(0, $allProductsCount - $activeProductsCount);
    $activeSharePct = $allProductsCount > 0 ? (int) round(($activeProductsCount / $allProductsCount) * 100) : 0;
    $avgSalePrice = (float) ($productTotals->avg_sale_price ?? 0);
    $stockedBrandsCount = (int) ($productTotals->brand_count ?? 0);
    $stockedCategoriesCount = (int) ($productTotals->category_count ?? 0);

    // Single batch query for current page stock rows (zero N+1)
    $stockByProduct = $products->isNotEmpty()
        ? \App\Models\Unit::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->keyBy('product_id')
        : collect();

    $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 5);

    $isFiltered = request()->filled('search')
        || request()->filled('status')
        || request()->filled('brand_id')
        || request()->filled('category_id');

    $currentStatus = request('status', '');
    $statusLink = fn (?string $status) => route('products.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'brand_id' => request('brand_id'),
        'category_id' => request('category_id'),
        'status' => $status,
    ]));
@endphp

<div x-data="productsPage()" class="space-y-6">

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
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Products List</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Products List</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($products->total()) }} {{ Str::plural('product', $products->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Browse your master product catalog, filter by brand or category, and manage pricing, IMEIs, and status.</p>
        </div>

        {{-- Right Actions: View Switcher + Reset + Add Product --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            {{-- View Mode Switcher --}}
            <div class="inline-flex items-center rounded-2xl border border-line bg-surface p-1 shadow-2xs" role="tablist" aria-label="Layout view">
                <button type="button"
                        @click="setViewMode('table')"
                        role="tab"
                        :aria-selected="viewMode === 'table'"
                        :class="viewMode === 'table' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>Table</span>
                </button>
                <button type="button"
                        @click="setViewMode('grid')"
                        role="tab"
                        :aria-selected="viewMode === 'grid'"
                        :class="viewMode === 'grid' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1.5" />
                        <rect x="14" y="3" width="7" height="7" rx="1.5" />
                        <rect x="3" y="14" width="7" height="7" rx="1.5" />
                        <rect x="14" y="14" width="7" height="7" rx="1.5" />
                    </svg>
                    <span>Cards</span>
                </button>
            </div>

            @if($isFiltered)
                <a href="{{ route('products.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset all filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Product</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Product Catalog Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Products --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Catalog SKUs</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($allProductsCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="smartphone" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success tabular-nums">
                    {{ number_format($activeProductsCount) }} active
                </span>
                <span>· {{ number_format($inactiveProductsCount) }} inactive</span>
            </div>
        </div>

        {{-- KPI 2: Active Availability --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Ratio</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ $activeSharePct }}<span class="text-base font-semibold text-muted">%</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-success transition-all duration-500" style="width: {{ $activeSharePct }}%;"></div>
                </div>
                <p class="text-xs text-muted">Ready for POS &amp; purchase orders</p>
            </div>
        </div>

        {{-- KPI 3: Average Sale Price --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Avg Sale Price</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ \App\Helpers\Money::format($avgSalePrice) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>Across priced catalog items</span>
                <a href="{{ route('units.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Stock</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 4: Brand & Category Coverage --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Catalog Coverage</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($stockedBrandsCount) }}
                        <span class="text-sm font-semibold text-muted">{{ Str::plural('brand', $stockedBrandsCount) }}</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="sparkles" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>Across <strong class="text-ink font-semibold tabular-nums">{{ number_format($stockedCategoriesCount) }}</strong> {{ Str::plural('category', $stockedCategoriesCount) }}</span>
                <a href="{{ route('brands.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Brands</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>
    </div>

    {{-- Unified Products Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Status Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Product Catalog</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ number_format($products->count()) }} of {{ number_format($products->total()) }} matching {{ Str::plural('product', $products->total()) }}
                </p>
            </div>

            {{-- Server-side Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ $statusLink(null) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === '' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>All</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === '' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-ink' }}">
                        {{ number_format($allProductsCount) }}
                    </span>
                </a>
                <a href="{{ $statusLink('active') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'active' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Active</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === 'active' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-success' }}">
                        {{ number_format($activeProductsCount) }}
                    </span>
                </a>
                <a href="{{ $statusLink('inactive') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'inactive' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Inactive</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === 'inactive' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-danger' }}">
                        {{ number_format($inactiveProductsCount) }}
                    </span>
                </a>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                        @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                        @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                        <label class="text-muted flex items-center gap-1.5 text-xs font-medium">
                            <span>Show</span>
                            <select name="per_page" onchange="this.form.submit()"
                                    class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="10" @selected($perPage == 10)>10</option>
                                <option value="25" @selected($perPage == 25)>25</option>
                                <option value="50" @selected($perPage == 50)>50</option>
                                <option value="100" @selected($perPage == 100)>100</option>
                            </select>
                            <span>entries</span>
                        </label>
                    </form>

                    {{-- Search Form --}}
                    <form method="GET" action="{{ route('products.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                        @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                        @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search name, PRD code, model, barcode, brand..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('products.index', array_filter(['per_page' => request('per_page'), 'status' => request('status'), 'brand_id' => request('brand_id'), 'category_id' => request('category_id')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Brand Filter + Category Filter + Column Visibility --}}
                <div class="flex items-center gap-2.5 flex-wrap shrink-0">
                    {{-- Brand Filter --}}
                    <form method="GET" action="{{ route('products.index') }}" class="shrink-0">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                        @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                        <select name="brand_id" onchange="this.form.submit()" aria-label="Filter by brand"
                                class="rounded-xl border border-line bg-surface py-2 px-3 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="">All Brands</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Category Filter --}}
                    <form method="GET" action="{{ route('products.index') }}" class="shrink-0">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                        @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                        <select name="category_id" onchange="this.form.submit()" aria-label="Filter by category"
                                class="rounded-xl border border-line bg-surface py-2 px-3 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Column Visibility Dropdown (Table view only) --}}
                    <div x-show="viewMode === 'table'" class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Columns</span>
                            <span class="rounded-full bg-soft px-1.5 py-0.2 text-[10px] font-bold text-brand-500 tabular-nums"
                                  x-text="activeColumnsCount() + '/7'"></span>
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-52 rounded-2xl border border-line bg-surface p-1.5 shadow-card z-50 text-left">
                            <div class="px-2.5 py-1.5 text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line mb-1">
                                Toggle Columns
                            </div>
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium rounded-xl text-ink transition-colors hover:bg-soft text-left cursor-pointer">
                                    <div class="flex items-center gap-2">
                                        <span class="flex size-4 items-center justify-center rounded border transition-colors"
                                              :class="columns[col.key] ? 'bg-brand-500 border-brand-500 text-on-brand' : 'border-line bg-surface'">
                                            <svg x-show="columns[col.key]" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span x-text="col.label"></span>
                                    </div>
                                    <span x-show="columns[col.key]" class="text-[10px] text-brand-500 font-semibold">On</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================================
             VIEW 1: DATA TABLE VIEW (Default)
        =================================================================== --}}
        <div x-show="viewMode === 'table'" class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="products-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th x-show="columns.product_id" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Product ID</th>
                        <th x-show="columns.product" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Product</th>
                        <th x-show="columns.model" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Model</th>
                        <th x-show="columns.barcode" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Barcode / IMEI</th>
                        <th x-show="columns.stock" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Stock</th>
                        <th x-show="columns.sale_price" scope="col" class="py-3.5 px-5 text-end font-bold text-ink">Sale Price</th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($products as $product)
                        @php
                            $stockRow = $stockByProduct->get($product->id);
                            $stockQty = $stockRow ? (int) $stockRow->quantity : 0;
                            $stockUnit = $stockRow?->unit ?? 'Pcs';
                            $isLowStock = $stockQty <= $lowStockThreshold;
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            {{-- Product ID --}}
                            <td x-show="columns.product_id" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center rounded-lg bg-soft px-2 py-1 font-mono text-xs font-semibold text-muted border border-line">
                                    {{ $product->product_id }}
                                </span>
                            </td>

                            {{-- Product + Photo / Brand Logo + Category + Variant --}}
                            <td x-show="columns.product" class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    @if($product->photo)
                                        <x-thumbnail :src="$product->photo_url" :alt="$product->name . ' photo'" :label="$product->name" size="size-9 rounded-lg" class="group-hover:scale-105 transition-transform" />
                                    @elseif($product->brand)
                                        <x-brand-logo :name="$product->brand->name" size="sm" class="group-hover:scale-105 transition-transform" />
                                    @else
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-soft text-muted border border-line">
                                            <x-icon name="smartphone" class="size-4" />
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                                {{ $product->name }}
                                            </span>
                                            @if($product->storage)
                                                <span class="inline-flex items-center rounded-md bg-soft px-1.5 py-0.5 text-[10px] font-semibold text-ink border border-line">
                                                    {{ $product->storage }}
                                                </span>
                                            @endif
                                            @if($product->color)
                                                <span class="inline-flex items-center rounded-md bg-soft px-1.5 py-0.5 text-[10px] font-medium text-muted border border-line">
                                                    {{ $product->color }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-muted mt-0.5">
                                            {{ $product->brand?->name ?? 'Unbranded' }} · {{ $product->category?->name ?? 'Uncategorized' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Model --}}
                            <td x-show="columns.model" class="py-4 px-5 text-start whitespace-nowrap text-sm text-muted">
                                {{ $product->model ?: '—' }}
                            </td>

                            {{-- Barcode / IMEI --}}
                            <td x-show="columns.barcode" class="py-4 px-5 text-start whitespace-nowrap">
                                @if($product->barcode)
                                    <span class="font-mono text-xs text-ink bg-canvas px-2 py-1 rounded-lg border border-line">
                                        {{ $product->barcode }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>

                            {{-- Stock --}}
                            <td x-show="columns.stock" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums {{ $isLowStock ? 'bg-warning/10 text-warning ring-1 ring-warning/20' : 'bg-soft text-ink border border-line' }}">
                                    <span>{{ number_format($stockQty) }}</span>
                                    <span class="text-[10px] opacity-80">{{ $stockUnit }}</span>
                                </span>
                            </td>

                            {{-- Sale Price --}}
                            <td x-show="columns.sale_price" class="py-4 px-5 text-end whitespace-nowrap">
                                <span class="block text-sm font-bold text-ink tabular-nums">
                                    {{ \App\Helpers\Money::format($product->sale_price) }}
                                </span>
                                @if((float) $product->purchase_price > 0)
                                    <span class="block text-[10px] text-muted tabular-nums">
                                        Cost {{ \App\Helpers\Money::format($product->purchase_price) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td x-show="columns.status" class="py-4 px-5 text-start whitespace-nowrap">
                                @can('update', $product)
                                    <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                title="Click to switch to {{ $product->status === 'active' ? 'Inactive' : 'Active' }}"
                                                class="btn-scale cursor-pointer inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-all {{ $product->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $product->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($product->status) }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $product->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                        <span class="size-1.5 rounded-full {{ $product->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                        <span>{{ ucfirst($product->status) }}</span>
                                    </span>
                                @endcan
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('update', $product)
                                        <a href="{{ route('products.edit', $product) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="Edit {{ $product->name }}">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                    @endcan
                                    @can('delete', $product)
                                        <button type="button"
                                                @click="openDelete(@js(['id' => $product->id, 'name' => $product->displayName(), 'code' => $product->product_id, 'action' => route('products.destroy', $product)]))"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Delete {{ $product->name }}">
                                            <x-svg.trash-bin class="size-3.5" />
                                            <span>Delete</span>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="1 + activeColumnsCount()" class="py-12 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                        <x-icon name="smartphone" class="size-7" />
                                    </div>
                                    <h3 class="text-base font-semibold text-ink">No products found</h3>
                                    <p class="text-xs text-muted mt-1 leading-relaxed">
                                        @if($isFiltered)
                                            No products match your current search or filter selection.
                                        @else
                                            Add your first mobile device or accessory to build your product catalog.
                                        @endif
                                    </p>
                                    <div class="mt-4 flex items-center gap-2">
                                        @if($isFiltered)
                                            <a href="{{ route('products.index') }}"
                                               class="rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Filters
                                            </a>
                                        @endif
                                        @can('create', App\Models\Product::class)
                                            <a href="{{ route('products.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + Add Product
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ===================================================================
             VIEW 2: PRODUCT CARDS SHOWCASE (Switchable)
        =================================================================== --}}
        <div x-show="viewMode === 'grid'" x-cloak class="p-5 sm:p-6 bg-canvas/20">
            @if($products->isNotEmpty())
                <div class="grid gap-4 sm:gap-5 md:grid-cols-2 xl:grid-cols-3" data-stagger>
                    @foreach($products as $product)
                        @php
                            $stockRow = $stockByProduct->get($product->id);
                            $stockQty = $stockRow ? (int) $stockRow->quantity : 0;
                            $stockUnit = $stockRow?->unit ?? 'Pcs';
                            $isLowStock = $stockQty <= $lowStockThreshold;
                        @endphp
                        <article style="--item-i: {{ $loop->index }}"
                                 class="group relative flex flex-col justify-between rounded-2xl border border-line bg-surface p-5 shadow-2xs transition-all duration-200 hover:border-brand-500/40 hover:shadow-card">
                            <div>
                                {{-- Top Row: Photo / Brand Logo + Name + Status --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($product->photo)
                                            <x-thumbnail :src="$product->photo_url" :alt="$product->name . ' photo'" :label="$product->name" size="size-10 rounded-xl" class="group-hover:scale-105 transition-transform" />
                                        @elseif($product->brand)
                                            <x-brand-logo :name="$product->brand->name" size="default" class="group-hover:scale-105 transition-transform" />
                                        @else
                                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-soft text-muted border border-line">
                                                <x-icon name="smartphone" class="size-5" />
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <h3 class="text-sm sm:text-base font-bold text-ink truncate group-hover:text-brand-500 transition-colors" title="{{ $product->name }}">
                                                {{ $product->name }}
                                            </h3>
                                            <p class="text-[11px] font-mono text-muted mt-0.5">
                                                {{ $product->product_id }} @if($product->model) · {{ $product->model }} @endif
                                            </p>
                                        </div>
                                    </div>

                                    @can('update', $product)
                                        <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="shrink-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="cursor-pointer inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-all {{ $product->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                                <span class="size-1.5 rounded-full {{ $product->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                                <span>{{ ucfirst($product->status) }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $product->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $product->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($product->status) }}</span>
                                        </span>
                                    @endcan
                                </div>

                                {{-- Tags Row: Category, Brand, Storage, Color --}}
                                <div class="mt-3.5 flex items-center gap-1.5 flex-wrap">
                                    @if($product->category)
                                        <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-500 ring-1 ring-brand-500/20">
                                            {{ $product->category->name }}
                                        </span>
                                    @endif
                                    @if($product->storage)
                                        <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-semibold text-ink border border-line">
                                            {{ $product->storage }}
                                        </span>
                                    @endif
                                    @if($product->color)
                                        <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-medium text-muted border border-line">
                                            {{ $product->color }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Price & Stock Strip --}}
                                <div class="mt-4 grid grid-cols-2 gap-2.5 rounded-xl bg-soft/60 border border-line/70 p-3">
                                    <div>
                                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Sale Price</span>
                                        <span class="block text-base font-extrabold text-ink tabular-nums mt-0.5">
                                            {{ \App\Helpers\Money::format($product->sale_price) }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">In Stock</span>
                                        <span class="inline-flex items-center gap-1 text-sm font-bold tabular-nums mt-0.5 {{ $isLowStock ? 'text-warning' : 'text-success' }}">
                                            {{ number_format($stockQty) }} {{ $stockUnit }}
                                        </span>
                                    </div>
                                </div>

                                @if($product->barcode)
                                    <div class="mt-3 flex items-center justify-between text-[11px] text-muted">
                                        <span>Barcode / IMEI</span>
                                        <span class="font-mono text-ink bg-canvas px-2 py-0.5 rounded-md border border-line">{{ $product->barcode }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Card Footer --}}
                            <div class="mt-4 pt-3.5 border-t border-line/80 flex items-center justify-between gap-2">
                                <span class="text-[11px] text-muted">
                                    {{ $product->brand?->name ?? 'Unbranded' }}
                                </span>
                                <div class="inline-flex items-center gap-1.5">
                                    @can('update', $product)
                                        <a href="{{ route('products.edit', $product) }}"
                                           class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                    @endcan
                                    @can('delete', $product)
                                        <button type="button"
                                                @click="openDelete(@js(['id' => $product->id, 'name' => $product->displayName(), 'code' => $product->product_id, 'action' => route('products.destroy', $product)]))"
                                                class="btn-scale inline-flex items-center rounded-xl border border-line bg-surface p-1.5 text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Delete {{ $product->name }}">
                                            <x-svg.trash-bin class="size-3.5" />
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-14 px-6 text-center">
                    <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                        <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                            <x-icon name="smartphone" class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-ink">No products found</h3>
                        <p class="text-xs text-muted mt-1.5 leading-relaxed">
                            @if($isFiltered)
                                No products match your current filter selection.
                            @else
                                Add your first product to start tracking pricing, IMEIs, and inventory stock.
                            @endif
                        </p>
                        <div class="mt-5 flex items-center gap-2.5">
                            @if($isFiltered)
                                <a href="{{ route('products.index') }}"
                                   class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                    Clear Filters
                                </a>
                            @endif
                            @can('create', App\Models\Product::class)
                                <a href="{{ route('products.create') }}"
                                   class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                    + Add Product
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $products->links() }}
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="deleteModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeDelete()">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-2xl p-6 sm:p-7 relative"
             @click.stop
             x-show="deleteModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 -translate-y-2">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-icon name="alert-triangle" class="size-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-bold text-ink">Delete product?</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted leading-relaxed">
                        You are about to remove
                        <strong class="font-semibold text-ink" x-text="deleteTarget?.name"></strong>
                        (<span class="font-mono text-xs text-ink" x-text="deleteTarget?.code"></span>)
                        along with its linked stock quantity row.
                    </p>
                </div>
            </div>

            <form :action="deleteTarget?.action" method="POST" class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-line">
                @csrf
                @method('DELETE')
                <button type="button" @click="closeDelete()"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:opacity-90 transition-opacity cursor-pointer">
                    <x-svg.trash-bin class="size-4" />
                    <span>Delete Product</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function productsPage() {
    return {
        viewMode: localStorage.getItem('products_view_mode') || 'table',

        deleteModalOpen: false,
        deleteTarget: null,

        columnsDropdownOpen: false,
        columns: {
            product_id: true,
            product: true,
            model: true,
            barcode: true,
            stock: true,
            sale_price: true,
            status: true
        },
        columnList: [
            { key: 'product_id', label: 'Product ID' },
            { key: 'product', label: 'Product' },
            { key: 'model', label: 'Model' },
            { key: 'barcode', label: 'Barcode / IMEI' },
            { key: 'stock', label: 'Stock' },
            { key: 'sale_price', label: 'Sale Price' },
            { key: 'status', label: 'Status' }
        ],

        setViewMode(mode) {
            this.viewMode = mode;
            try {
                localStorage.setItem('products_view_mode', mode);
            } catch (e) {}
        },

        openDelete(target) {
            this.deleteTarget = target;
            this.deleteModalOpen = true;
        },

        closeDelete() {
            this.deleteModalOpen = false;
        },

        toggleColumn(key) {
            if (this.columns[key] && this.activeColumnsCount() <= 1) {
                return;
            }
            this.columns[key] = !this.columns[key];
        },

        activeColumnsCount() {
            return Object.values(this.columns).filter(Boolean).length;
        }
    };
}
</script>
@endsection
