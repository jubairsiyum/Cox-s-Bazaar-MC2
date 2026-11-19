@extends('layouts.app')

@section('title', 'Units — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Units')

@section('content')
@php
    $modalState = [
        'hasErrors' => $errors->any(),
        'mode' => old('_form_mode', 'add'),
        'unitId' => (int) old('unit_id', 0) ?: null,
        'productName' => (string) old('product_name', ''),
        'storage' => (string) old('storage', ''),
        'color' => (string) old('color', ''),
        'quantity' => (string) old('quantity', ''),
        'unit' => (string) old('unit', 'Pcs'),
        'note' => (string) old('note', ''),
    ];

    $sortLink = fn (string $col) => route('units.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));

    $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 5);

    // Single lightweight aggregate query for stock intelligence KPIs (zero N+1)
    $unitStats = \App\Models\Unit::query()
        ->selectRaw(
            'COUNT(*) as total_rows, COALESCE(SUM(quantity), 0) as total_qty, SUM(CASE WHEN quantity <= ? THEN 1 ELSE 0 END) as low_count, COUNT(DISTINCT unit) as unit_types',
            [$lowStockThreshold]
        )
        ->first();

    $totalEntriesCount = (int) ($unitStats->total_rows ?? 0);
    $totalStockQuantity = (int) ($unitStats->total_qty ?? 0);
    $lowStockCount = (int) ($unitStats->low_count ?? 0);
    $healthyStockCount = max(0, $totalEntriesCount - $lowStockCount);
    $healthyPct = $totalEntriesCount > 0 ? (int) round(($healthyStockCount / $totalEntriesCount) * 100) : 100;
    $distinctUnitTypes = (int) ($unitStats->unit_types ?? 0);

    // Current page filter counts
    $pageLowStockCount = $units->filter(fn ($u) => $u->isLowStock())->count();
    $pageHealthyCount = $units->count() - $pageLowStockCount;
@endphp

<div x-data="unitsPage(@js($modalState))" class="space-y-6">

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
                <span class="font-medium text-ink">Units</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Units</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($units->total()) }} {{ Str::plural('entry', $units->total()) }}
                </span>
                @if($lowStockCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning ring-1 ring-warning/20 tabular-nums">
                        <x-icon name="alert-triangle" class="size-3.5" />
                        <span>{{ number_format($lowStockCount) }} low stock</span>
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage your quantity data, monitor variant stock levels, and keep inventory units synchronized.</p>
        </div>

        {{-- Right Actions: Reset + Import + Add --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request('search') || request('sort'))
                <a href="{{ route('units.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('import', App\Models\Unit::class)
                <a href="{{ route('units.import') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                    <x-svg.upload class="size-4 text-muted" />
                    <span>Import</span>
                </a>
            @endcan

            @can('create', App\Models\Unit::class)
                <button type="button" @click="openAdd()"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                    <x-icon name="plus" class="size-4" />
                    <span>Add</span>
                </button>
            @endcan
        </div>
    </div>

    {{-- 4-Card Quantity & Stock Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Tracked Variants --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Tracked Variants</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalEntriesCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success tabular-nums">
                    {{ number_format($healthyStockCount) }} healthy
                </span>
                <span>· 1 row per variant</span>
            </div>
        </div>

        {{-- KPI 2: Total Stock On Hand --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Quantity</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalStockQuantity) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="warehouse" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>Combined units across catalog</span>
                <a href="{{ route('products.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Products</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 3: Low Stock Alerts --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Low Stock Alerts</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums {{ $lowStockCount > 0 ? 'text-warning' : 'text-ink' }}">
                        {{ number_format($lowStockCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl {{ $lowStockCount > 0 ? 'bg-warning/10 text-warning ring-1 ring-warning/20' : 'bg-success/10 text-success ring-1 ring-success/20' }}">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                <span>Threshold: <strong class="text-ink font-semibold tabular-nums">&le; {{ number_format($lowStockThreshold) }}</strong> units</span>
            </div>
        </div>

        {{-- KPI 4: Stock Health & Unit Types --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Stock Health</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ $healthyPct }}<span class="text-base font-semibold text-muted">%</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full {{ $healthyPct >= 75 ? 'bg-success' : 'bg-warning' }} transition-all duration-500" style="width: {{ $healthyPct }}%;"></div>
                </div>
                <p class="text-xs text-muted">
                    <strong class="text-ink font-semibold tabular-nums">{{ number_format($distinctUnitTypes) }}</strong> active {{ Str::plural('unit', $distinctUnitTypes) }} of measure
                </p>
            </div>
        </div>
    </div>

    {{-- Unified Units Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Stock Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All your units</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ number_format($units->count()) }} of {{ number_format($units->total()) }} records · Sorted by <span class="font-semibold text-ink">{{ ucwords(str_replace('_', ' ', $sort)) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            {{-- Quick Page Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button"
                        @click="stockFilter = 'all'"
                        :class="stockFilter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>All on page</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                          :class="stockFilter === 'all' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-ink'">
                        {{ $units->count() }}
                    </span>
                </button>
                <button type="button"
                        @click="stockFilter = 'healthy'"
                        :class="stockFilter === 'healthy' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Healthy</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                          :class="stockFilter === 'healthy' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-success'">
                        {{ $pageHealthyCount }}
                    </span>
                </button>
                <button type="button"
                        @click="stockFilter = 'low'"
                        :class="stockFilter === 'low' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Low stock</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums"
                          :class="stockFilter === 'low' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-warning'">
                        {{ $pageLowStockCount }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('units.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
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
                    <form method="GET" action="{{ route('units.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search product name, variant, unit, or note..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('units.index', array_filter(['per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Sample CSV + Export Segmented Bar + Column Visibility --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <a href="{{ route('units.import-template') }}"
                       class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors"
                       title="Download sample CSV import template">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Sample CSV</span>
                    </a>

                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export units to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export units to Excel">
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
                                title="Print units list">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Print</span>
                        </button>
                    </div>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Columns</span>
                            <span class="rounded-full bg-soft px-1.5 py-0.2 text-[10px] font-bold text-brand-500 tabular-nums"
                                  x-text="activeColumnsCount() + '/4'"></span>
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

        {{-- Responsive Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="units-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th x-show="columns.product_name" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('product_name') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Product Name</span>
                                <span class="text-[10px] leading-none opacity-60 group-hover:opacity-100">
                                    @if($sort === 'product_name')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>
                        <th x-show="columns.quantity" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('quantity') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Quantity</span>
                                <span class="text-[10px] leading-none opacity-60 group-hover:opacity-100">
                                    @if($sort === 'quantity')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>
                        <th x-show="columns.unit" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('unit') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Unit</span>
                                <span class="text-[10px] leading-none opacity-60 group-hover:opacity-100">
                                    @if($sort === 'unit')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>
                        <th x-show="columns.note" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('note') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Note</span>
                                <span class="text-[10px] leading-none opacity-60 group-hover:opacity-100">
                                    @if($sort === 'note')
                                        <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($units as $unit)
                        @php $isLow = $unit->isLowStock(); @endphp
                        <tr x-show="matchesStockFilter({{ $isLow ? 'true' : 'false' }})" class="hover:bg-soft/40 transition-colors duration-150 group">
                            {{-- Product Variant Name --}}
                            <td x-show="columns.product_name" data-col="product_name" class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $isLow ? 'bg-warning/10 text-warning ring-1 ring-warning/20' : 'bg-soft text-brand-500 ring-1 ring-line' }}">
                                        <x-icon name="smartphone" class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                            {{ $unit->displayName() }}
                                        </span>
                                        @if($unit->product?->product_id)
                                            <span class="block text-[11px] font-mono text-muted">
                                                {{ $unit->product->product_id }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Quantity --}}
                            <td x-show="columns.quantity" data-col="quantity" class="py-4 px-5 text-start whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <span class="text-sm font-bold tabular-nums {{ $isLow ? 'text-warning' : 'text-ink' }}">
                                        {{ number_format((int) $unit->quantity) }}
                                    </span>
                                    @if($isLow)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-bold text-warning ring-1 ring-warning/20 tracking-wide">
                                            <span class="size-1.5 rounded-full bg-warning"></span>
                                            LOW STOCK
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Unit --}}
                            <td x-show="columns.unit" data-col="unit" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-0.5 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                                    {{ $unit->unit }}
                                </span>
                            </td>

                            {{-- Note --}}
                            <td x-show="columns.note" data-col="note" class="py-4 px-5 text-start">
                                @if($unit->note)
                                    <p class="text-sm text-ink/90 max-w-md line-clamp-2" title="{{ $unit->note }}">
                                        {{ $unit->note }}
                                    </p>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('update', $unit)
                                        <button type="button"
                                                @click="openEdit(@js(['id' => $unit->id, 'productName' => $unit->product?->name ?? '', 'storage' => $unit->product?->storage ?? '', 'color' => $unit->product?->color ?? '', 'quantity' => (string) ((int) $unit->quantity), 'unit' => $unit->unit, 'note' => $unit->note]))"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors cursor-pointer"
                                                title="Edit {{ $unit->displayName() }}">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </button>
                                    @endcan

                                    @can('delete', $unit)
                                        <button type="button"
                                                @click="openDelete(@js(['id' => $unit->id, 'name' => $unit->displayName(), 'quantity' => (int) $unit->quantity, 'unit' => $unit->unit, 'action' => route('units.destroy', $unit)]))"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Delete {{ $unit->displayName() }}">
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
                                        <x-icon name="box" class="size-7" />
                                    </div>
                                    <h3 class="text-base font-semibold text-ink">No quantity entries found</h3>
                                    <p class="text-xs text-muted mt-1 leading-relaxed">
                                        @if(request('search'))
                                            No stock rows match <strong class="text-ink">"{{ request('search') }}"</strong>.
                                        @else
                                            Add your first quantity entry or bulk-import from CSV to populate stock levels.
                                        @endif
                                    </p>
                                    <div class="mt-4 flex items-center gap-2">
                                        @if(request('search'))
                                            <a href="{{ route('units.index') }}"
                                               class="rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Search
                                            </a>
                                        @endif
                                        @can('create', App\Models\Unit::class)
                                            <button type="button" @click="openAdd()"
                                                    class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                                + Add Quantity
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Client-Side Stock Filter Empty Notice --}}
            @if($units->isNotEmpty())
                <div x-show="(stockFilter === 'low' && {{ $pageLowStockCount }} === 0) || (stockFilter === 'healthy' && {{ $pageHealthyCount }} === 0)"
                     x-cloak
                     class="py-10 px-6 text-center border-t border-line">
                    <p class="text-sm font-semibold text-ink">No units match this stock filter on the current page.</p>
                    <button type="button" @click="stockFilter = 'all'"
                            class="mt-3 rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-brand-500 hover:bg-soft transition-colors cursor-pointer">
                        Show all entries on page
                    </button>
                </div>
            @endif
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $units->links() }}
        </div>
    </div>

    {{-- Add / Edit Quantity Modal Dialog --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeModal()">

        <div class="w-full max-w-xl rounded-3xl border border-line bg-surface shadow-2xl p-6 sm:p-7 relative my-8"
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
                <input type="hidden" name="unit_id" :value="unitId ?? ''">

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
                                x-text="editMode ? 'Edit quantity' : 'Add quantity'">Add quantity</h3>
                            <p class="text-xs text-muted mt-0.5"
                               x-text="editMode ? 'Adjust stock count via the ledger or update variant details.' : 'Register a product variant and its opening stock quantity.'"></p>
                        </div>
                    </div>

                    <button type="button" @click="closeModal()"
                            class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                            aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="space-y-4 py-5">
                    <div>
                        <label for="unit-product-name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Product name<span class="text-danger ml-0.5">*</span>
                        </label>
                        <input type="text" id="unit-product-name" name="product_name" x-model="form.productName" required
                               placeholder="e.g. Phone Charger 20W, Screen Protector, iPhone 13"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('product_name') border-danger @enderror">
                        @error('product_name')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-[11px] text-muted">
                            One row per variant — different storage capacities or colors are tracked separately.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="unit-storage" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Storage
                            </label>
                            <input type="text" id="unit-storage" name="storage" x-model="form.storage"
                                   placeholder="e.g. 128GB (optional)"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('storage') border-danger @enderror">
                            @error('storage')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="unit-color" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Color
                            </label>
                            <input type="text" id="unit-color" name="color" x-model="form.color"
                                   placeholder="e.g. Black (optional)"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('color') border-danger @enderror">
                            @error('color')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="unit-quantity" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Quantity<span class="text-danger ml-0.5">*</span>
                            </label>
                            <input type="number" step="1" min="0" id="unit-quantity" name="quantity" x-model="form.quantity" required
                                   placeholder="e.g. 50"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs tabular-nums focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('quantity') border-danger @enderror">
                            @error('quantity')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="unit-unit" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Unit<span class="text-danger ml-0.5">*</span>
                            </label>
                            <select id="unit-unit" name="unit" x-model="form.unit" required
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('unit') border-danger @enderror">
                                @foreach (\App\Models\Unit::UNITS as $unitOption)
                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                @endforeach
                            </select>
                            @error('unit')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="unit-note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Note
                        </label>
                        <input type="text" id="unit-note" name="note" x-model="form.note"
                               placeholder="e.g. 10 pcs per box"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('note') border-danger @enderror">
                        @error('note')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
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
                    <h3 class="text-lg font-bold text-ink">Delete quantity entry?</h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted leading-relaxed">
                        You are about to remove
                        <strong class="font-semibold text-ink" x-text="deleteTarget?.name"></strong>
                        (<span class="tabular-nums font-semibold text-ink" x-text="(deleteTarget?.quantity ?? 0) + ' ' + (deleteTarget?.unit ?? '')"></span>)
                        along with its linked product variant.
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
                    <span>Delete Entry</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function unitsPage(state) {
    return {
        stockFilter: 'all',

        modalOpen: state.hasErrors,
        editMode: state.mode === 'edit',
        unitId: state.unitId,
        form: {
            productName: state.productName,
            storage: state.storage,
            color: state.color,
            quantity: state.quantity,
            unit: state.unit,
            note: state.note
        },

        deleteModalOpen: false,
        deleteTarget: null,

        columnsDropdownOpen: false,
        columns: {
            product_name: true,
            quantity: true,
            unit: true,
            note: true
        },
        columnList: [
            { key: 'product_name', label: 'Product Name' },
            { key: 'quantity', label: 'Quantity' },
            { key: 'unit', label: 'Unit' },
            { key: 'note', label: 'Note' }
        ],

        createAction: @js(route('units.store')),
        searchQuery: @js((string) request('search', '')),

        matchesStockFilter(isLow) {
            if (this.stockFilter === 'low') {
                return isLow === true;
            }
            if (this.stockFilter === 'healthy') {
                return isLow === false;
            }
            return true;
        },

        formAction() {
            return this.editMode ? @js(url('units')) + '/' + this.unitId : this.createAction;
        },

        openAdd() {
            this.editMode = false;
            this.unitId = null;
            this.form = { productName: '', storage: '', color: '', quantity: '', unit: 'Pcs', note: '' };
            this.modalOpen = true;
        },

        openEdit(unit) {
            this.editMode = true;
            this.unitId = unit.id;
            this.form = {
                productName: unit.productName,
                storage: unit.storage ?? '',
                color: unit.color ?? '',
                quantity: unit.quantity,
                unit: unit.unit ?? 'Pcs',
                note: unit.note ?? ''
            };
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
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
            return Object.keys(this.columns)
                .filter(k => this.columns[k])
                .join(',') || 'product_name,quantity,unit,note';
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            return `/units/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/units/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/units/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
