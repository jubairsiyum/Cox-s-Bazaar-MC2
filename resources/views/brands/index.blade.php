@extends('layouts.app')

@section('title', 'Brands — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Brands')

@section('content')
@php
    $modalState = [
        'hasErrors' => $errors->any(),
        'mode' => old('_form_mode', 'add'),
        'brandId' => (int) old('brand_id', 0) ?: null,
        'name' => (string) old('name', ''),
        'note' => (string) old('note', ''),
        'productCount' => (int) old('_product_count', 0),
    ];

    $sortLink = fn (string $col) => route('brands.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page', 15),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));

    // Single lightweight aggregate query for product counts per brand (zero N+1)
    $productStatsByBrand = \App\Models\Product::query()
        ->whereNotNull('brand_id')
        ->selectRaw("brand_id, COUNT(*) as total_count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count")
        ->groupBy('brand_id')
        ->get()
        ->keyBy('brand_id');

    $totalBrandsCount = $brands->total();
    $totalLinkedProducts = (int) $productStatsByBrand->sum('total_count');
    $totalActiveProducts = (int) $productStatsByBrand->sum('active_count');
    $brandsWithProductsCount = $productStatsByBrand->filter(fn ($row) => (int) $row->total_count > 0)->count();
    $emptyBrandsCount = max(0, $totalBrandsCount - $brandsWithProductsCount);
    $catalogCoveragePct = $totalBrandsCount > 0 ? (int) round(($brandsWithProductsCount / $totalBrandsCount) * 100) : 0;
    $avgProductsPerBrand = $brandsWithProductsCount > 0 ? round($totalLinkedProducts / $brandsWithProductsCount, 1) : 0;
    $maxBrandProductCount = max(1, (int) ($productStatsByBrand->max('total_count') ?? 1));

    // Leading brand (only shown when not filtering by search and products exist)
    $topBrandRow = (!request('search') && $totalLinkedProducts > 0)
        ? $productStatsByBrand->sortByDesc('total_count')->first()
        : null;
    $topBrand = $topBrandRow ? \App\Models\Brand::query()->find($topBrandRow->brand_id) : null;
    $topBrandCount = $topBrandRow ? (int) $topBrandRow->total_count : 0;

    // Current page filter counts
    $pageWithProductsCount = $brands->filter(fn ($b) => (int) ($productStatsByBrand->get($b->id)?->total_count ?? 0) > 0)->count();
    $pageWithoutProductsCount = $brands->count() - $pageWithProductsCount;
@endphp

<div x-data="brandsPage(@js($modalState))" class="space-y-6">

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
                <span class="font-medium text-ink">Brands</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Brands</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($totalBrandsCount) }} {{ Str::plural('brand', $totalBrandsCount) }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-[11px] font-medium text-muted border border-line"
                      title="Brands are protected from deletion to keep linked products and sales history intact.">
                    <x-icon name="roles" class="size-3.5 text-brand-500" />
                    <span>Update-only catalog</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage your brands, track product inventory coverage per manufacturer, and keep profiles up to date.</p>
        </div>

        {{-- Right Actions: View Switcher + Reset + Add Brand --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            {{-- View Mode Switcher --}}
            <div class="inline-flex items-center rounded-2xl border border-line bg-surface p-1 shadow-2xs" role="tablist" aria-label="Layout view">
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
            </div>

            @if(request('search') || request('sort'))
                <a href="{{ route('brands.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\Brand::class)
                <button type="button" @click="openAdd()"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Brand</span>
                </button>
            @endcan
        </div>
    </div>

    {{-- 4-Card Product & Brand Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Brands --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Registered Brands</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalBrandsCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="sparkles" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                    {{ number_format($brandsWithProductsCount) }} stocked
                </span>
                @if($emptyBrandsCount > 0)
                    <span>· {{ number_format($emptyBrandsCount) }} empty</span>
                @else
                    <span>· All stocked</span>
                @endif
            </div>
        </div>

        {{-- KPI 2: Linked Products --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Products</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalLinkedProducts) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="smartphone" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span><strong class="text-ink font-semibold">{{ number_format($totalActiveProducts) }}</strong> active in catalog</span>
                <a href="{{ route('products.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Catalog</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 3: Top Brand --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Leading Brand</span>
                    @if($topBrand)
                        <div class="mt-2 flex items-center gap-2.5 min-w-0">
                            <x-brand-logo :name="$topBrand->name" size="sm" />
                            <p class="text-lg font-bold tracking-tight text-ink truncate" title="{{ $topBrand->name }}">
                                {{ $topBrand->name }}
                            </p>
                        </div>
                    @else
                        <p class="text-lg font-bold tracking-tight text-muted mt-2">
                            {{ request('search') ? 'Filtered View' : 'No Products Yet' }}
                        </p>
                    @endif
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                @if($topBrand)
                    <span class="font-semibold text-brand-500">{{ number_format($topBrandCount) }} {{ Str::plural('product', $topBrandCount) }}</span>
                    <span>({{ $totalLinkedProducts > 0 ? round(($topBrandCount / $totalLinkedProducts) * 100) : 0 }}% of catalog)</span>
                @elseif(request('search'))
                    <span>Searching for <strong class="text-ink">"{{ request('search') }}"</strong></span>
                @else
                    <span>Assign products to brands to track leaders</span>
                @endif
            </div>
        </div>

        {{-- KPI 4: Catalog Coverage --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Catalog Coverage</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ $catalogCoveragePct }}<span class="text-base font-semibold text-muted">%</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $catalogCoveragePct }}%;"></div>
                </div>
                <p class="text-xs text-muted">
                    Avg <strong class="text-ink font-semibold">{{ $avgProductsPerBrand }}</strong> products per active brand
                </p>
            </div>
        </div>
    </div>

    {{-- Main Brand Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Stock Filter Tabs --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All your brands</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing product counts per brand · Sorted by <span class="font-semibold text-ink">{{ ucfirst($sort) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            {{-- Quick Page Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button"
                        @click="stockFilter = 'all'"
                        :class="stockFilter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>All on page</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px]"
                          :class="stockFilter === 'all' ? 'bg-white/20 text-on-brand' : 'bg-surface text-ink'">
                        {{ $brands->count() }}
                    </span>
                </button>
                <button type="button"
                        @click="stockFilter = 'stocked'"
                        :class="stockFilter === 'stocked' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>With products</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px]"
                          :class="stockFilter === 'stocked' ? 'bg-white/20 text-on-brand' : 'bg-surface text-success'">
                        {{ $pageWithProductsCount }}
                    </span>
                </button>
                <button type="button"
                        @click="stockFilter = 'empty'"
                        :class="stockFilter === 'empty' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>No products</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px]"
                          :class="stockFilter === 'empty' ? 'bg-white/20 text-on-brand' : 'bg-surface text-muted'">
                        {{ $pageWithoutProductsCount }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search + Sort --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    {{-- Per Page Selector (Max 15 per page) --}}
                    <form method="GET" action="{{ route('brands.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                        <label class="text-muted flex items-center gap-1.5 text-xs font-medium">
                            <span>Show</span>
                            <select name="per_page" onchange="this.form.submit()"
                                    class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="10" @selected($perPage == 10)>10</option>
                                <option value="15" @selected($perPage == 15 || $perPage > 15)>15</option>
                            </select>
                            <span>max</span>
                        </label>
                    </form>

                    {{-- Search Form --}}
                    <form method="GET" action="{{ route('brands.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search brands or descriptions..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('brands.index', array_filter(['per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>

                    {{-- Quick Sort Button (especially handy in Cards view) --}}
                    <a href="{{ $sortLink('name') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0"
                       title="Toggle alphabetical order">
                        <span>Name</span>
                        <span class="text-[10px] text-brand-500 font-black">
                            {{ $sort === 'name' ? ($direction === 'asc' ? 'A→Z' : 'Z→A') : '⇅' }}
                        </span>
                    </a>
                </div>

                {{-- Right: Export Actions & Table Column Toggle --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export filtered brands to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export filtered brands to Excel">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Excel</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <button type="button" @click="openPdf()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Open printable PDF preview">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span>PDF</span>
                        </button>
                        <span class="h-4 w-px bg-line"></span>
                        <button type="button" @click="printTable()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Print brands list">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Print</span>
                        </button>
                    </div>

                    {{-- Column Visibility Dropdown (visible in Table mode) --}}
                    <div x-show="viewMode === 'table'" x-cloak class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Columns</span>
                            <span class="rounded-full bg-soft px-1.5 py-0.2 text-[10px] font-bold text-brand-500"
                                  x-text="activeColumnsCount() + '/3'"></span>
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 rounded-2xl border border-line bg-surface p-1.5 shadow-card z-50 text-left">
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
             VIEW 1: BRAND CARDS SHOWCASE (Default Modern Visual Design)
        =================================================================== --}}
        <div x-show="viewMode === 'grid'" class="p-5 sm:p-6 bg-canvas/20">
            @if($brands->isNotEmpty())
                <div class="grid gap-4 sm:gap-5 md:grid-cols-2 xl:grid-cols-3" data-stagger>
                    @foreach($brands as $brand)
                        @php
                            $stats = $productStatsByBrand->get($brand->id);
                            $productCount = (int) ($stats?->total_count ?? 0);
                            $activeCount = (int) ($stats?->active_count ?? 0);
                            $inactiveCount = max(0, $productCount - $activeCount);
                            $catalogSharePct = $totalLinkedProducts > 0
                                ? round(($productCount / $totalLinkedProducts) * 100, 1)
                                : 0;
                            $barWidthPct = $productCount > 0
                                ? max(8, (int) round(($productCount / $maxBrandProductCount) * 100))
                                : 0;
                        @endphp

                        <article x-show="matchesStockFilter({{ $productCount }})"
                                 style="--item-i: {{ $loop->index }}"
                                 class="group relative flex flex-col justify-between rounded-2xl border border-line bg-surface p-5 shadow-2xs transition-all duration-200 hover:border-brand-500/40 hover:shadow-card">

                            <div>
                                {{-- Card Top Row: Logo + Title + Product Count Badge --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <x-brand-logo :name="$brand->name" size="lg" class="group-hover:scale-105 transition-transform duration-200" />
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-ink truncate group-hover:text-brand-500 transition-colors" title="{{ $brand->name }}">
                                                {{ $brand->name }}
                                            </h3>
                                            <p class="text-[11px] text-muted mt-0.5">
                                                ID #{{ $brand->id }} · Added {{ $brand->created_at?->format('d M Y') ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Product Count Badge --}}
                                    <a href="{{ route('products.index', ['brand_id' => $brand->id]) }}"
                                       class="shrink-0 inline-flex flex-col items-end rounded-xl px-2.5 py-1.5 text-right transition-colors {{ $productCount > 0 ? 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 hover:bg-brand-500/15' : 'bg-soft text-muted border border-line hover:text-ink' }}"
                                       title="View all {{ $productCount }} products for {{ $brand->name }}">
                                        <span class="text-sm font-extrabold leading-none tabular-nums">{{ number_format($productCount) }}</span>
                                        <span class="text-[10px] font-semibold mt-0.5 leading-none opacity-85">{{ Str::plural('product', $productCount) }}</span>
                                    </a>
                                </div>

                                {{-- Brand Description / Note --}}
                                <div class="mt-4 min-h-10">
                                    @if($brand->note)
                                        <p class="text-xs text-muted leading-relaxed line-clamp-2" title="{{ $brand->note }}">
                                            {{ $brand->note }}
                                        </p>
                                    @else
                                        <p class="text-xs text-muted/60 italic">No description provided for this brand.</p>
                                    @endif
                                </div>

                                {{-- Product Distribution Meter --}}
                                <div class="mt-4 rounded-xl bg-soft/60 border border-line/70 px-3.5 py-2.5">
                                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                                        <span class="font-semibold text-ink/80">
                                            @if($productCount > 0)
                                                {{ $activeCount }} active @if($inactiveCount > 0) · {{ $inactiveCount }} inactive @endif
                                            @else
                                                No linked products yet
                                            @endif
                                        </span>
                                        <span class="font-bold tabular-nums {{ $productCount > 0 ? 'text-brand-500' : 'text-muted' }}">
                                            {{ $catalogSharePct }}% share
                                        </span>
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-surface overflow-hidden">
                                        <div class="h-full rounded-full {{ $productCount > 0 ? 'bg-brand-500' : 'bg-line' }} transition-all duration-500"
                                             style="width: {{ $barWidthPct }}%;"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Card Footer: View Products + Update (No Delete Allowed) --}}
                            <div class="mt-4 pt-3.5 border-t border-line/80 flex items-center justify-between gap-2">
                                <a href="{{ route('products.index', ['brand_id' => $brand->id]) }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $productCount > 0 ? 'text-brand-500 hover:text-brand-600' : 'text-muted hover:text-ink' }} transition-colors">
                                    <x-icon name="box" class="size-3.5" />
                                    <span>{{ $productCount > 0 ? 'Browse Products' : 'View Products' }}</span>
                                    <x-icon name="arrow-up-right" class="size-3.5" />
                                </a>

                                @can('update', $brand)
                                    <button type="button"
                                            @click="openEdit(@js(['id' => $brand->id, 'name' => $brand->name, 'note' => $brand->note, 'productCount' => $productCount]))"
                                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors cursor-pointer"
                                            title="Update {{ $brand->name }}">
                                        <x-svg.pencil class="size-3.5 text-muted group-hover:text-brand-500" />
                                        <span>Update</span>
                                    </button>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Client-Side Stock Filter Empty Notice --}}
                <div x-show="stockFilter === 'stocked' && {{ $pageWithProductsCount }} === 0 || stockFilter === 'empty' && {{ $pageWithoutProductsCount }} === 0"
                     x-cloak
                     class="py-10 px-6 text-center">
                    <p class="text-sm font-semibold text-ink">No brands match this filter on the current page.</p>
                    <button type="button" @click="stockFilter = 'all'"
                            class="mt-3 rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-brand-500 hover:bg-soft transition-colors cursor-pointer">
                        Show all brands on page
                    </button>
                </div>
            @else
                {{-- Empty State --}}
                <div class="py-14 px-6 text-center">
                    <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                        <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                            <x-icon name="box" class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-ink">No brands found</h3>
                        <p class="text-xs text-muted mt-1.5 leading-relaxed">
                            @if(request('search'))
                                No brand manufacturer matches your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                            @else
                                Get started by registering your first manufacturer brand to organize your product catalog.
                            @endif
                        </p>
                        <div class="mt-5 flex items-center gap-2.5">
                            @if(request('search'))
                                <a href="{{ route('brands.index') }}"
                                   class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                    Clear Search
                                </a>
                            @endif
                            @can('create', App\Models\Brand::class)
                                <button type="button" @click="openAdd()"
                                        class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                    + Add Brand
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ===================================================================
             VIEW 2: COMPACT DATA TABLE VIEW (Switchable)
        =================================================================== --}}
        <div x-show="viewMode === 'table'" x-cloak class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="brands-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        {{-- Brand Column Header --}}
                        <th x-show="columns.name" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Brands</span>
                                <span class="flex flex-col text-[10px] leading-none opacity-60 group-hover:opacity-100 transition-opacity">
                                    @if($sort === 'name')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>

                        {{-- Products Count Column Header --}}
                        <th x-show="columns.products" scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">
                            Products
                        </th>

                        {{-- Note Column Header --}}
                        <th x-show="columns.note" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('note') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Note</span>
                                <span class="flex flex-col text-[10px] leading-none opacity-60 group-hover:opacity-100 transition-opacity">
                                    @if($sort === 'note')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>

                        {{-- Action Column Header (Preserved for permission assertions; Update only, no Delete) --}}
                        @can('brands.manage')
                            <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                        @endcan
                    </tr>
                </thead>

                <tbody class="divide-y divide-line bg-surface">
                    @forelse($brands as $brand)
                        @php
                            $stats = $productStatsByBrand->get($brand->id);
                            $productCount = (int) ($stats?->total_count ?? 0);
                            $activeCount = (int) ($stats?->active_count ?? 0);
                            $catalogSharePct = $totalLinkedProducts > 0
                                ? round(($productCount / $totalLinkedProducts) * 100, 1)
                                : 0;
                            $barWidthPct = $productCount > 0
                                ? max(8, (int) round(($productCount / $maxBrandProductCount) * 100))
                                : 0;
                        @endphp
                        <tr x-show="matchesStockFilter({{ $productCount }})" class="hover:bg-soft/40 transition-colors duration-150 group">
                            {{-- Brand Name Column --}}
                            <td x-show="columns.name" data-col="name" class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    <x-brand-logo :name="$brand->name" size="default" class="group-hover:scale-110 transition-transform duration-200" />
                                    <div>
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                            {{ $brand->name }}
                                        </span>
                                        <span class="block text-[11px] text-muted">
                                            ID #{{ $brand->id }} · {{ $brand->created_at?->format('d M Y') ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Products Count & Share Column --}}
                            <td x-show="columns.products" data-col="products" class="py-4 px-5 text-start">
                                <div class="flex flex-col gap-1.5 max-w-44">
                                    <div class="flex items-center justify-between gap-2">
                                        <a href="{{ route('products.index', ['brand_id' => $brand->id]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums transition-colors {{ $productCount > 0 ? 'bg-brand-500/10 text-brand-500 hover:bg-brand-500/20' : 'bg-soft text-muted hover:text-ink' }}">
                                            <span>{{ number_format($productCount) }}</span>
                                            <span class="font-medium">{{ Str::plural('product', $productCount) }}</span>
                                        </a>
                                        @if($productCount > 0)
                                            <span class="text-[11px] font-semibold text-muted tabular-nums">{{ $catalogSharePct }}%</span>
                                        @endif
                                    </div>
                                    <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                        <div class="h-full rounded-full {{ $productCount > 0 ? 'bg-brand-500' : 'bg-line' }}"
                                             style="width: {{ $barWidthPct }}%;"></div>
                                    </div>
                                    @if($productCount > 0 && $activeCount !== $productCount)
                                        <span class="text-[10px] text-muted">{{ $activeCount }} active</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Note Column --}}
                            <td x-show="columns.note" data-col="note" class="py-4 px-5 text-start">
                                @if($brand->note)
                                    <p class="text-sm text-ink/90 font-normal leading-relaxed max-w-xl">
                                        {{ $brand->note }}
                                    </p>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>

                            {{-- Actions Column (View Products + Update ONLY — Delete intentionally disabled) --}}
                            @can('brands.manage')
                                <td class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="{{ route('products.index', ['brand_id' => $brand->id]) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-muted shadow-2xs hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="View {{ $brand->name }} products">
                                            <x-icon name="box" class="size-3.5" />
                                            <span>Products</span>
                                        </a>

                                        @can('update', $brand)
                                            <button type="button"
                                                    @click="openEdit(@js(['id' => $brand->id, 'name' => $brand->name, 'note' => $brand->note, 'productCount' => $productCount]))"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors cursor-pointer"
                                                    title="Update {{ $brand->name }}">
                                                <x-svg.pencil class="size-3.5 text-muted group-hover:text-brand-500" />
                                                <span>Update</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="1 + activeColumnsCount()" class="py-12 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                        <x-icon name="box" class="size-7" />
                                    </div>
                                    <h3 class="text-base font-semibold text-ink">No brands found</h3>
                                    <p class="text-xs text-muted mt-1 leading-relaxed">
                                        @if(request('search'))
                                            No brand manufacturer matches your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                                        @else
                                            Get started by adding your first brand manufacturer to the catalog.
                                        @endif
                                    </p>
                                    <div class="mt-4 flex items-center gap-2">
                                        @if(request('search'))
                                            <a href="{{ route('brands.index') }}"
                                               class="rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Search
                                            </a>
                                        @endif
                                        @can('create', App\Models\Brand::class)
                                            <button type="button" @click="openAdd()"
                                                    class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                                + Add Brand
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination & Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $brands->links() }}
        </div>
    </div>

    {{-- Add / Update Brand Modal Dialog --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeModal()">

        <div class="w-full max-w-lg rounded-3xl border border-line bg-surface shadow-card p-6 sm:p-7 relative my-8"
             @click.stop
             x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 -translate-y-2">

            <form :action="formAction()" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="editMode ? 'PATCH' : 'POST'">
                <input type="hidden" name="_form_mode" :value="editMode ? 'edit' : 'add'">
                <input type="hidden" name="brand_id" :value="brandId ?? ''">
                <input type="hidden" name="_product_count" :value="selectedProductCount">

                {{-- Modal Header --}}
                <div class="flex items-start justify-between pb-4 border-b border-line">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                            <template x-if="!editMode">
                                <x-icon name="plus" class="size-5" />
                            </template>
                            <template x-if="editMode">
                                <x-svg.pencil class="size-5" />
                            </template>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-ink"
                                x-text="editMode ? 'Update brand' : 'Add brand'">Add brand</h3>
                            <p class="text-xs text-muted mt-0.5"
                               x-text="editMode ? 'Modify brand details without affecting linked products.' : 'Register a new brand manufacturer in the catalog.'"></p>
                        </div>
                    </div>

                    <button type="button" @click="closeModal()"
                            class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                            aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                {{-- Linked Products Info Banner (Shown in Update Mode) --}}
                <div x-show="editMode" x-cloak class="mt-4 flex items-center gap-3 rounded-2xl border border-brand-500/20 bg-brand-500/5 px-4 py-3">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500">
                        <x-icon name="box" class="size-4" />
                    </div>
                    <p class="text-xs text-ink/90 leading-relaxed">
                        This brand currently has
                        <strong class="font-bold text-brand-500" x-text="selectedProductCount + (selectedProductCount === 1 ? ' linked product' : ' linked products')"></strong>.
                        Updating its name or description keeps all product associations intact.
                    </p>
                </div>

                {{-- Modal Body Inputs --}}
                <div class="space-y-4 py-5">
                    {{-- Brand Name Field --}}
                    <div>
                        <label for="brand-name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Brand name<span class="text-danger">*</span>
                        </label>
                        <input type="text" id="brand-name" name="name" x-model="form.name" required
                               placeholder="e.g. Samsung, Apple, Xiaomi, Vivo"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Brand Short Description / Note Field --}}
                    <div>
                        <label for="brand-note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Short description
                        </label>
                        <textarea id="brand-note" name="note" x-model="form.note" rows="3"
                                  placeholder="e.g. Flagship & mid-range smartphones, tablets, and accessories"
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror"></textarea>
                        @error('note')
                            <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-line">
                    <button type="button" @click="closeModal()"
                            class="btn-scale rounded-xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn-scale rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-5 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <x-icon name="check" class="size-4" />
                        <span x-text="editMode ? 'Save Changes' : 'Save'">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function brandsPage(state) {
        return {
            viewMode: localStorage.getItem('brands_view_mode') || 'grid',
            stockFilter: 'all',

            modalOpen: state.hasErrors,
            editMode: state.mode === 'edit',
            brandId: state.brandId,
            selectedProductCount: state.productCount ?? 0,
            form: {
                name: state.name,
                note: state.note
            },

            columnsDropdownOpen: false,
            columns: {
                name: true,
                products: true,
                note: true
            },
            columnList: [
                { key: 'name', label: 'Brands' },
                { key: 'products', label: 'Products' },
                { key: 'note', label: 'Note' }
            ],

            createAction: @js(route('brands.store')),
            searchQuery: @js((string) request('search', '')),

            setViewMode(mode) {
                this.viewMode = mode;
                try {
                    localStorage.setItem('brands_view_mode', mode);
                } catch (e) {}
            },

            matchesStockFilter(count) {
                if (this.stockFilter === 'stocked') {
                    return count > 0;
                }
                if (this.stockFilter === 'empty') {
                    return count === 0;
                }
                return true;
            },

            formAction() {
                return this.editMode ? @js(url('brands')) + '/' + this.brandId : this.createAction;
            },

            openAdd() {
                this.editMode = false;
                this.brandId = null;
                this.selectedProductCount = 0;
                this.form = { name: '', note: '' };
                this.modalOpen = true;
            },

            openEdit(brand) {
                this.editMode = true;
                this.brandId = brand.id;
                this.selectedProductCount = brand.productCount ?? 0;
                this.form = {
                    name: brand.name,
                    note: brand.note ?? ''
                };
                this.modalOpen = true;
            },

            closeModal() {
                this.modalOpen = false;
            },

            toggleColumn(key) {
                if (this.columns[key] && this.activeColumnsCount() <= 1) {
                    return;
                }
                this.columns[key] = !this.columns[key];
            },

            activeColumnsCount() {
                return Object.values(this.columns).filter(Boolean).length;
            },

            activeColumnsQuery() {
                return ['name', 'note']
                    .filter(k => this.columns[k])
                    .join(',') || 'name,note';
            },

            exportUrl(format) {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: this.searchQuery
                });
                return `/brands/export/${format}?${params.toString()}`;
            },

            openPdf() {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: this.searchQuery
                });
                window.open(`/brands/pdf-view?${params.toString()}`, '_blank');
            },

            printTable() {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: this.searchQuery
                });
                window.open(`/brands/print-view?${params.toString()}`, '_blank');
            }
        };
    }
</script>
@endsection
