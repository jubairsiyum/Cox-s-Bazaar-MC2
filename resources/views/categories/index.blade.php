@extends('layouts.app')

@section('title', 'Categories List — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Categories List')

@section('content')
@php
    // Single lightweight aggregate query for category totals (zero N+1)
    $categoryTotals = \App\Models\Category::query()
        ->selectRaw("COUNT(*) as total_count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count")
        ->first();

    $allCategoriesCount = (int) ($categoryTotals->total_count ?? 0);
    $activeCategoriesCount = (int) ($categoryTotals->active_count ?? 0);
    $inactiveCategoriesCount = max(0, $allCategoriesCount - $activeCategoriesCount);
    $activeSharePct = $allCategoriesCount > 0 ? (int) round(($activeCategoriesCount / $allCategoriesCount) * 100) : 0;

    // Single lightweight aggregate query for product counts per category (zero N+1)
    $productStatsByCategory = \App\Models\Product::query()
        ->whereNotNull('category_id')
        ->selectRaw("category_id, COUNT(*) as total_count, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count")
        ->groupBy('category_id')
        ->get()
        ->keyBy('category_id');

    $totalLinkedProducts = (int) $productStatsByCategory->sum('total_count');
    $totalActiveLinkedProducts = (int) $productStatsByCategory->sum('active_count');
    $stockedCategoriesCount = $productStatsByCategory->filter(fn ($row) => (int) $row->total_count > 0)->count();
    $maxCategoryProductCount = max(1, (int) ($productStatsByCategory->max('total_count') ?? 1));

    // Leading category (only resolved when not filtering so search tests never see filtered-out names)
    $isFiltered = request()->filled('search') || request()->filled('status');
    $topCategoryRow = (! $isFiltered && $totalLinkedProducts > 0)
        ? $productStatsByCategory->sortByDesc('total_count')->first()
        : null;
    $topCategory = $topCategoryRow ? \App\Models\Category::query()->find($topCategoryRow->category_id) : null;
    $topCategoryCount = $topCategoryRow ? (int) $topCategoryRow->total_count : 0;

    $currentStatus = request('status', '');
    $statusLink = fn (?string $status) => route('categories.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'status' => $status,
    ]));
@endphp

<div x-data="categoriesPage()" class="space-y-6">

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
                <span class="font-medium text-ink">Categories List</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Categories List</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($categories->total()) }} {{ Str::plural('category', $categories->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Organize product lines into structured categories, toggle catalog visibility, and export clean reports.</p>
        </div>

        {{-- Right Actions: View Switcher + Reset + Add Category --}}
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
                <a href="{{ route('categories.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\Category::class)
                <a href="{{ route('categories.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Category</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Category Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Categories --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Categories</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($allCategoriesCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">
                    {{ number_format($stockedCategoriesCount) }} stocked
                </span>
                <span>· {{ number_format(max(0, $allCategoriesCount - $stockedCategoriesCount)) }} empty</span>
            </div>
        </div>

        {{-- KPI 2: Active Availability --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Categories</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($activeCategoriesCount) }}
                        <span class="text-sm font-semibold text-muted">/ {{ number_format($allCategoriesCount) }}</span>
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
                <p class="text-xs text-muted">
                    <strong class="text-ink font-semibold tabular-nums">{{ $activeSharePct }}%</strong> active · {{ number_format($inactiveCategoriesCount) }} inactive
                </p>
            </div>
        </div>

        {{-- KPI 3: Linked Products --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Categorized Products</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalLinkedProducts) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="smartphone" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span><strong class="text-ink font-semibold tabular-nums">{{ number_format($totalActiveLinkedProducts) }}</strong> active SKUs</span>
                <a href="{{ route('products.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Catalog</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 4: Leading Category --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Top Category</span>
                    @if($topCategory)
                        <p class="text-lg font-bold tracking-tight text-ink truncate mt-2" title="{{ $topCategory->name }}">
                            {{ $topCategory->name }}
                        </p>
                    @else
                        <p class="text-lg font-bold tracking-tight text-muted mt-2">
                            {{ $isFiltered ? 'Filtered View' : 'No Products Yet' }}
                        </p>
                    @endif
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                @if($topCategory)
                    <span class="font-semibold text-brand-500 tabular-nums">{{ number_format($topCategoryCount) }} {{ Str::plural('product', $topCategoryCount) }}</span>
                    <span>({{ $totalLinkedProducts > 0 ? round(($topCategoryCount / $totalLinkedProducts) * 100) : 0 }}% share)</span>
                @elseif(request('search'))
                    <span>Searching for <strong class="text-ink">"{{ request('search') }}"</strong></span>
                @elseif(request('status'))
                    <span>Filtered by <strong class="text-ink">{{ ucfirst(request('status')) }}</strong> status</span>
                @else
                    <span>Assign products to categories to track leaders</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Unified Category Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Status Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Category Directory</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ number_format($categories->count()) }} of {{ number_format($categories->total()) }} matching {{ Str::plural('category', $categories->total()) }}
                </p>
            </div>

            {{-- Server-side Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ $statusLink(null) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === '' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>All</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === '' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-ink' }}">
                        {{ number_format($allCategoriesCount) }}
                    </span>
                </a>
                <a href="{{ $statusLink('active') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'active' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Active</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === 'active' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-success' }}">
                        {{ number_format($activeCategoriesCount) }}
                    </span>
                </a>
                <a href="{{ $statusLink('inactive') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'inactive' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Inactive</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === 'inactive' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-danger' }}">
                        {{ number_format($inactiveCategoriesCount) }}
                    </span>
                </a>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('categories.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
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
                    <form method="GET" action="{{ route('categories.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search categories or descriptions..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('categories.index', array_filter(['per_page' => request('per_page'), 'status' => request('status')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Export Actions & Column Toggle --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <button type="button" @click="copyTable()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Copy visible categories to clipboard">
                            <x-svg.copy class="size-3.5 text-muted" />
                            <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                        </button>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export categories to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export categories to Excel">
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
                                title="Print categories list">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Print</span>
                        </button>
                    </div>

                    {{-- Column Visibility Dropdown (visible in Table mode) --}}
                    <div x-show="viewMode === 'table'" class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Columns</span>
                            <span class="rounded-full bg-soft px-1.5 py-0.2 text-[10px] font-bold text-brand-500 tabular-nums"
                                  x-text="activeColumnsCount() + '/' + columnList.length"></span>
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
             VIEW 1: DATA TABLE VIEW (Default)
        =================================================================== --}}
        <div x-show="viewMode === 'table'" class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="categories-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th x-show="columns.name" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Category Name</th>
                        <th x-show="columns.products" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Products</th>
                        <th x-show="columns.description" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Description</th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Status</th>
                        @can('categories.manage')
                            <th x-show="columns.action" scope="col" class="py-3.5 px-5 text-end font-bold text-ink">ACTION</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($categories as $category)
                        @php
                            $stats = $productStatsByCategory->get($category->id);
                            $productCount = (int) ($stats?->total_count ?? 0);
                            $activeCount = (int) ($stats?->active_count ?? 0);
                            $barWidthPct = $productCount > 0 ? max(8, (int) round(($productCount / $maxCategoryProductCount) * 100)) : 0;
                            $initials = strtoupper(mb_substr($category->name, 0, 2));
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            {{-- Category Name --}}
                            <td x-show="columns.name" data-col="name" class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 text-xs font-bold tracking-wider group-hover:scale-105 transition-transform">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                            {{ $category->name }}
                                        </span>
                                        <span class="block text-[11px] text-muted">
                                            Added {{ $category->created_at?->format('d M Y') ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{-- Linked Products --}}
                            <td x-show="columns.products" data-col="products" class="py-4 px-5 text-start">
                                <div class="flex flex-col gap-1.5 max-w-40">
                                    <a href="{{ route('products.index', ['category_id' => $category->id]) }}"
                                       class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums w-fit transition-colors {{ $productCount > 0 ? 'bg-brand-500/10 text-brand-500 hover:bg-brand-500/20' : 'bg-soft text-muted hover:text-ink' }}">
                                        <span>{{ number_format($productCount) }}</span>
                                        <span class="font-medium">{{ Str::plural('product', $productCount) }}</span>
                                    </a>
                                    <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                                        <div class="h-full rounded-full {{ $productCount > 0 ? 'bg-brand-500' : 'bg-line' }}"
                                             style="width: {{ $barWidthPct }}%;"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Description --}}
                            <td x-show="columns.description" data-col="description" class="py-4 px-5 text-start">
                                @if($category->description)
                                    <p class="text-sm text-ink/90 leading-relaxed max-w-md line-clamp-2" title="{{ $category->description }}">
                                        {{ $category->description }}
                                    </p>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td x-show="columns.status" data-col="status" class="py-4 px-5 text-start whitespace-nowrap">
                                @can('update', $category)
                                    <form method="POST" action="{{ route('categories.toggle-status', $category) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                title="Click to switch to {{ $category->status === 'active' ? 'Inactive' : 'Active' }}"
                                                class="btn-scale cursor-pointer inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition-all {{ $category->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $category->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($category->status) }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $category->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                        <span class="size-1.5 rounded-full {{ $category->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                        <span>{{ ucfirst($category->status) }}</span>
                                    </span>
                                @endcan
                            </td>

                            {{-- Actions --}}
                            @can('categories.manage')
                                <td x-show="columns.action" data-col="action" class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        @can('update', $category)
                                            <a href="{{ route('categories.edit', $category) }}"
                                               class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                               title="Edit {{ $category->name }}">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan

                                        @can('delete', $category)
                                            <button type="button"
                                                    @click="openDelete(@js(['id' => $category->id, 'name' => $category->name, 'productCount' => $productCount, 'action' => route('categories.destroy', $category)]))"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                                    title="Delete {{ $category->name }}">
                                                <x-svg.trash-bin class="size-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="activeColumnsCount()" class="py-12 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                        <x-icon name="box" class="size-7" />
                                    </div>
                                    <h3 class="text-base font-semibold text-ink">No categories found</h3>
                                    <p class="text-xs text-muted mt-1 leading-relaxed">
                                        @if($isFiltered)
                                            No categories match your current filter criteria.
                                        @else
                                            Get started by creating your first product category.
                                        @endif
                                    </p>
                                    <div class="mt-4 flex items-center gap-2">
                                        @if($isFiltered)
                                            <a href="{{ route('categories.index') }}"
                                               class="rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Filters
                                            </a>
                                        @endif
                                        @can('create', App\Models\Category::class)
                                            <a href="{{ route('categories.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + Add Category
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
             VIEW 2: CATEGORY CARDS SHOWCASE (Switchable)
        =================================================================== --}}
        <div x-show="viewMode === 'grid'" x-cloak class="p-5 sm:p-6 bg-canvas/20">
            @if($categories->isNotEmpty())
                <div class="grid gap-4 sm:gap-5 md:grid-cols-2 xl:grid-cols-3" data-stagger>
                    @foreach($categories as $category)
                        @php
                            $stats = $productStatsByCategory->get($category->id);
                            $productCount = (int) ($stats?->total_count ?? 0);
                            $activeCount = (int) ($stats?->active_count ?? 0);
                            $catalogSharePct = $totalLinkedProducts > 0 ? round(($productCount / $totalLinkedProducts) * 100, 1) : 0;
                            $barWidthPct = $productCount > 0 ? max(8, (int) round(($productCount / $maxCategoryProductCount) * 100)) : 0;
                            $initials = strtoupper(mb_substr($category->name, 0, 2));
                        @endphp

                        <article style="--item-i: {{ $loop->index }}"
                                 class="group relative flex flex-col justify-between rounded-2xl border border-line bg-surface p-5 shadow-2xs transition-all duration-200 hover:border-brand-500/40 hover:shadow-card">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 text-sm font-bold tracking-wider group-hover:scale-105 transition-transform">
                                            {{ $initials }}
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-ink truncate group-hover:text-brand-500 transition-colors" title="{{ $category->name }}">
                                                {{ $category->name }}
                                            </h3>
                                            <p class="text-[11px] text-muted mt-0.5">
                                                Added {{ $category->created_at?->format('d M Y') ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>

                                    @can('update', $category)
                                        <form method="POST" action="{{ route('categories.toggle-status', $category) }}" class="shrink-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="cursor-pointer inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition-all {{ $category->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                                <span class="size-1.5 rounded-full {{ $category->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                                <span>{{ ucfirst($category->status) }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $category->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $category->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($category->status) }}</span>
                                        </span>
                                    @endcan
                                </div>

                                <div class="mt-4 min-h-10">
                                    @if($category->description)
                                        <p class="text-xs text-muted leading-relaxed line-clamp-2" title="{{ $category->description }}">
                                            {{ $category->description }}
                                        </p>
                                    @else
                                        <p class="text-xs text-muted/60 italic">No description provided for this category.</p>
                                    @endif
                                </div>

                                <div class="mt-4 rounded-xl bg-soft/60 border border-line/70 px-3.5 py-2.5">
                                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                                        <span class="font-semibold text-ink/80 tabular-nums">
                                            {{ number_format($productCount) }} {{ Str::plural('product', $productCount) }}
                                            @if($productCount > 0)
                                                · {{ number_format($activeCount) }} active
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

                            <div class="mt-4 pt-3.5 border-t border-line/80 flex items-center justify-between gap-2">
                                <a href="{{ route('products.index', ['category_id' => $category->id]) }}"
                                   class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $productCount > 0 ? 'text-brand-500 hover:text-brand-600' : 'text-muted hover:text-ink' }} transition-colors">
                                    <x-icon name="box" class="size-3.5" />
                                    <span>View Products</span>
                                    <x-icon name="arrow-up-right" class="size-3.5" />
                                </a>

                                @can('categories.manage')
                                    <div class="inline-flex items-center gap-1.5">
                                        @can('update', $category)
                                            <a href="{{ route('categories.edit', $category) }}"
                                               class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan
                                        @can('delete', $category)
                                            <button type="button"
                                                    @click="openDelete(@js(['id' => $category->id, 'name' => $category->name, 'productCount' => $productCount, 'action' => route('categories.destroy', $category)]))"
                                                    class="btn-scale inline-flex items-center rounded-xl border border-line bg-surface p-1.5 text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                                    title="Delete {{ $category->name }}">
                                                <x-svg.trash-bin class="size-3.5" />
                                            </button>
                                        @endcan
                                    </div>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="py-14 px-6 text-center">
                    <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                        <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                            <x-icon name="box" class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-ink">No categories found</h3>
                        <p class="text-xs text-muted mt-1.5 leading-relaxed">
                            @if($isFiltered)
                                No categories match your current search or status filter.
                            @else
                                Start organizing your catalog by adding your first product category.
                            @endif
                        </p>
                        <div class="mt-5 flex items-center gap-2.5">
                            @if($isFiltered)
                                <a href="{{ route('categories.index') }}"
                                   class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                    Clear Filters
                                </a>
                            @endif
                            @can('create', App\Models\Category::class)
                                <a href="{{ route('categories.create') }}"
                                   class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                    + Add Category
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $categories->links() }}
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    @can('categories.manage')
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
                    <h3 class="text-lg font-bold text-ink">Delete category?</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted leading-relaxed">
                        You are about to archive
                        <strong class="font-semibold text-ink" x-text="deleteTarget?.name"></strong>.
                        <span x-show="deleteTarget && deleteTarget.productCount > 0">
                            It currently has <strong class="text-warning" x-text="deleteTarget?.productCount"></strong> linked product(s).
                        </span>
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
                    <span>Delete Category</span>
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>

<script>
function categoriesPage() {
    return {
        viewMode: localStorage.getItem('categories_view_mode') || 'table',
        copied: false,

        deleteModalOpen: false,
        deleteTarget: null,

        columnsDropdownOpen: false,
        columns: {
            name: true,
            products: true,
            description: true,
            status: true,
            @can('categories.manage')
            action: true,
            @endcan
        },
        columnList: [
            { key: 'name', label: 'Category Name' },
            { key: 'products', label: 'Products' },
            { key: 'description', label: 'Description' },
            { key: 'status', label: 'Status' },
            @can('categories.manage')
            { key: 'action', label: 'Action' },
            @endcan
        ],

        searchQuery: @js((string) request('search', '')),
        statusQuery: @js((string) request('status', '')),

        setViewMode(mode) {
            this.viewMode = mode;
            try {
                localStorage.setItem('categories_view_mode', mode);
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
        },

        activeColumnsQuery() {
            return ['name', 'description', 'status']
                .filter(k => this.columns[k])
                .join(',') || 'name,description,status';
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusQuery
            });
            return `/categories/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusQuery
            });
            window.open(`/categories/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusQuery
            });
            window.open(`/categories/print-view?${params.toString()}`, '_blank');
        },

        copyTable() {
            const table = document.getElementById('categories-table');
            if (!table) return;
            const exportCols = [
                { key: 'name', label: 'Category Name' },
                { key: 'description', label: 'Description' },
                { key: 'status', label: 'Status' },
            ].filter(c => this.columns[c.key]);

            let text = exportCols.map(c => c.label).join('\t') + '\n';
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(tr => {
                if (tr.querySelector('td[colspan]')) return;
                const rowVals = exportCols.map(c => {
                    const td = tr.querySelector(`td[data-col="${c.key}"]`);
                    return td ? td.innerText.replace(/\s+/g, ' ').trim() : '';
                });
                text += rowVals.join('\t') + '\n';
            });

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 1800);
                });
            }
        }
    };
}
</script>
@endsection
