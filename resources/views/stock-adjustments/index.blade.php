@extends('layouts.app')

@section('title', 'Stock Adjustments — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Adjustments')

@section('content')
@php
    $sortLink = fn (string $col) => route('stock-adjustments.index', array_filter([
        'search' => request('search'),
        'branch' => request('branch'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc',
    ]));

    // Single lightweight aggregate query for KPI intelligence strip (zero N+1),
    // scoped to the manager's location like the list itself.
    $adjStatsQuery = \App\Models\StockAdjustment::query();
    \App\Services\LocationScope::scopeQuery($adjStatsQuery, auth()->user(), 'location_id');
    $adjStats = $adjStatsQuery
        ->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN type = 'add' THEN 1 ELSE 0 END) as add_count,
            SUM(CASE WHEN type = 'remove' THEN 1 ELSE 0 END) as remove_count,
            SUM(CASE WHEN type = 'set' THEN 1 ELSE 0 END) as set_count,
            SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            COALESCE(SUM(total_amount), 0) as total_value,
            SUM(CASE WHEN adjustment_date >= ? THEN 1 ELSE 0 END) as month_count
        ", [now()->startOfMonth()])
        ->first();

    $totalCount = (int) ($adjStats?->total_count ?? 0);
    $addCount = (int) ($adjStats?->add_count ?? 0);
    $removeCount = (int) ($adjStats?->remove_count ?? 0);
    $setCount = (int) ($adjStats?->set_count ?? 0);
    $pendingCount = (int) ($adjStats?->pending_count ?? 0);
    $approvedCount = (int) ($adjStats?->approved_count ?? 0);
    $rejectedCount = (int) ($adjStats?->rejected_count ?? 0);
    $totalValue = (float) ($adjStats?->total_value ?? 0);
    $monthCount = (int) ($adjStats?->month_count ?? 0);

    $pageAddCount = $adjustments->filter(fn ($a) => $a->type === \App\Enums\StockAdjustmentType::Add)->count();
    $pageRemoveCount = $adjustments->filter(fn ($a) => $a->type === \App\Enums\StockAdjustmentType::Remove)->count();
    $pageSetCount = $adjustments->filter(fn ($a) => $a->type === \App\Enums\StockAdjustmentType::Set)->count();
    $pagePendingCount = $adjustments->filter(fn ($a) => $a->isPending())->count();
@endphp

<div x-data="adjustmentsPage()" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Stock Adjustments</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Stock Adjustments</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($totalCount) }} {{ Str::plural('adjustment', $totalCount) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Record damage, loss, found stock, and physical stock-take corrections with full audit trails.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request('search') || request('sort'))
                <a href="{{ route('stock-adjustments.index') }}"
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
                    <span>Add Adjustment</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Adjustment Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Adjustments --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Adjustments</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="enquiries" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-2 py-0.5 text-[11px] font-semibold text-brand-500">
                    {{ number_format($monthCount) }} this month
                </span>
            </div>
        </div>

        {{-- KPI 2: Additions vs Removals --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Add vs Remove</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        <span class="text-success">+{{ number_format($addCount) }}</span>
                        <span class="text-muted font-normal mx-1">/</span>
                        <span class="text-danger">-{{ number_format($removeCount) }}</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Found additions vs damage/loss removals
            </div>
        </div>

        {{-- KPI 3: Stock Count Sets --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Stock Counts (Set)</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($setCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Physical inventory stock-take reconciliations
            </div>
        </div>

        {{-- KPI 4: Total Adjusted Value --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Adjusted Value</span>
                    <p class="text-xl sm:text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums money truncate" title="{{ \App\Helpers\Money::format($totalValue) }}">
                        {{ \App\Helpers\Money::format($totalValue) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Cumulative value across posted adjustments
            </div>
        </div>
    </div>

    {{-- Main Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Type Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All stock adjustments</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ $adjustments->count() }} of {{ number_format($adjustments->total()) }} records · Sorted by <span class="font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($sort)) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button"
                        @click="typeFilter = 'all'"
                        :class="typeFilter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>All on page</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'all' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-ink'">
                        {{ $adjustments->count() }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'add'"
                        :class="typeFilter === 'add' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Add Stock</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'add' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-success'">
                        {{ $pageAddCount }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'remove'"
                        :class="typeFilter === 'remove' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Remove Stock</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'remove' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-danger'">
                        {{ $pageRemoveCount }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'set'"
                        :class="typeFilter === 'set' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Set Stock</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'set' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-brand-500'">
                        {{ $pageSetCount }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('stock-adjustments.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('branch'))<input type="hidden" name="branch" value="{{ request('branch') }}">@endif
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

                    <form method="GET" action="{{ route('stock-adjustments.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('branch'))<input type="hidden" name="branch" value="{{ request('branch') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search reference no, reason, or type..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('stock-adjustments.index', array_filter(['branch' => request('branch'), 'per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>

                    <form method="GET" action="{{ route('stock-adjustments.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                        <select name="status" onchange="this.form.submit()"
                                class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="">All statuses</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if(auth()->user()?->hasRole('Super Admin'))
                            <select name="location_id" onchange="this.form.submit()"
                                    class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="">All locations</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <select name="branch" onchange="this.form.submit()"
                                    class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="">All Branches</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(request('branch') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if(request('status') || request('location_id') || request('branch'))
                            <a href="{{ route('stock-adjustments.index', array_filter(['per_page' => request('per_page'), 'search' => request('search'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                               class="inline-flex items-center rounded-xl border border-line bg-surface px-2.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors"
                               title="Clear filters">
                                <x-svg.x class="size-3.5" />
                            </a>
                        @endif
                    </form>
                </div>

                {{-- Right: Segmented Export Bar + Column Visibility Dropdown --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export to Excel">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Excel</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <button type="button" @click="openPdf()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Open printable PDF preview">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span>PDF</span>
                        </button>
                        <span class="h-4 w-px bg-line"></span>
                        <button type="button" @click="printTable()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Print adjustments list">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Print</span>
                        </button>
                    </div>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Columns</span>
                            <span class="rounded-full bg-soft px-1.5 py-0.5 text-[10px] font-bold text-brand-500"
                                  x-text="activeColumnsCount() + '/6'"></span>
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
                                            <svg x-show="columns[col.key]" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
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

        {{-- Table --}}
        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse" id="stock-adjustments-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th x-show="columns.adjustment_date" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('adjustment_date') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Date</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'adjustment_date' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.reference_no" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('reference_no') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Reference No</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'reference_no' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.type" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('type') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Adjustment type</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'type' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Status</th>
                        <th x-show="columns.total_amount" scope="col" class="py-3.5 px-5 text-end">
                            <a href="{{ $sortLink('total_amount') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Total Amount</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'total_amount' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.reason" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('reason') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Reason</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'reason' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.creator" scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Added By</th>
                        @can('stock-adjustments.manage')
                            <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($adjustments as $adjustment)
                        @php
                            $typeBadgeClass = match ($adjustment->type) {
                                \App\Enums\StockAdjustmentType::Add => 'bg-success/10 text-success ring-1 ring-success/20',
                                \App\Enums\StockAdjustmentType::Remove => 'bg-danger/10 text-danger ring-1 ring-danger/20',
                                \App\Enums\StockAdjustmentType::Set => 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20',
                            };
                            $typeDotClass = match ($adjustment->type) {
                                \App\Enums\StockAdjustmentType::Add => 'bg-success',
                                \App\Enums\StockAdjustmentType::Remove => 'bg-danger',
                                \App\Enums\StockAdjustmentType::Set => 'bg-brand-500',
                            };
                            $displayStatus = $adjustment->displayStatus();
                        @endphp
                        <tr x-show="typeFilter === 'all' || typeFilter === @js($adjustment->type->value)" class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td x-show="columns.adjustment_date" data-col="adjustment_date" class="py-4 px-5 text-xs text-muted whitespace-nowrap tabular-nums">
                                <span class="block text-sm font-semibold text-ink">{{ $adjustment->adjustment_date->format('d M Y') }}</span>
                                <span>{{ $adjustment->adjustment_date->format('H:i') }}</span>
                            </td>
                            <td x-show="columns.reference_no" data-col="reference_no" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line tabular-nums">
                                    {{ $adjustment->reference_no }}
                                </span>
                            </td>
                            <td x-show="columns.type" data-col="type" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeBadgeClass }}">
                                    <span class="size-1.5 rounded-full {{ $typeDotClass }}"></span>
                                    {{ $adjustment->type->label() }}
                                </span>
                            </td>
                            <td x-show="columns.status" data-col="status" class="py-4 px-5 text-start whitespace-nowrap">
                                <x-status-badge :status="$displayStatus['value']" :label="$displayStatus['label']" />
                                <span class="mt-1 block text-[11px] text-muted">
                                    {{ $adjustment->location?->name ?? 'No location' }}
                                </span>
                            </td>
                            <td x-show="columns.total_amount" data-col="total_amount" class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap">
                                {{ \App\Helpers\Money::format($adjustment->total_amount) }}
                            </td>
                            <td x-show="columns.reason" data-col="reason" class="py-4 px-5 text-start text-sm text-muted max-w-xs truncate" title="{{ $adjustment->reason }}">
                                {{ $adjustment->reason ?: '—' }}
                            </td>
                            <td x-show="columns.creator" data-col="creator" class="py-4 px-5 text-start text-sm text-ink whitespace-nowrap">
                                {{ $adjustment->creator?->name ?? '—' }}
                            </td>
                            @can('stock-adjustments.manage')
                                <td class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        @can('update', $adjustment)
                                            <a href="{{ route('stock-adjustments.edit', $adjustment) }}"
                                               class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan
                                        @can('delete', $adjustment)
                                            <button type="button"
                                                    @click="openDeleteModal(@js(route('stock-adjustments.destroy', $adjustment)), @js($adjustment->reference_no))"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-danger/30 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer">
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
                            <td :colspan="activeColumnsCount() + 1" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="enquiries" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No stock adjustments found.</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if(request('search'))
                                            No adjustments match your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                                        @else
                                            Create a stock adjustment to record damages, stock-takes, or found inventory.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex items-center gap-2.5">
                                        @if(request('search'))
                                            <a href="{{ route('stock-adjustments.index') }}"
                                               class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Search
                                            </a>
                                        @endif
                                        @can('create', App\Models\StockAdjustment::class)
                                            <a href="{{ route('stock-adjustments.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + Add Adjustment
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

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $adjustments->links() }}
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="deleteModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-overlay backdrop-blur-xs"
         @keydown.escape.window="deleteModal.open = false">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6" @click.stop>
            <div class="flex items-start gap-3.5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-ink">Delete stock adjustment?</h3>
                    <p class="mt-1.5 text-xs text-muted leading-relaxed">
                        Are you sure you want to delete <strong class="text-ink" x-text="deleteModal.ref"></strong>? Its stock effect will be automatically reversed if no subsequent movements occurred.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                <button type="button" @click="deleteModal.open = false"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Cancel
                </button>
                <form :action="deleteModal.url" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                        <x-icon name="check" class="size-3.5" />
                        <span>Delete & Reverse</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function adjustmentsPage() {
    return {
        typeFilter: 'all',
        deleteModal: {
            open: false,
            url: '',
            ref: ''
        },

        columnsDropdownOpen: false,
        columns: {
            adjustment_date: true,
            reference_no: true,
            type: true,
            status: true,
            total_amount: true,
            reason: true,
            creator: true
        },
        columnList: [
            { key: 'adjustment_date', label: 'Date' },
            { key: 'reference_no', label: 'Reference No' },
            { key: 'type', label: 'Adjustment type' },
            { key: 'status', label: 'Status' },
            { key: 'total_amount', label: 'Total Amount' },
            { key: 'reason', label: 'Reason' },
            { key: 'creator', label: 'Added By' }
        ],

        searchQuery: @js((string) request('search', '')),

        openDeleteModal(url, ref) {
            this.deleteModal = { open: true, url, ref };
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
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            return `/stock-adjustments/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/stock-adjustments/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/stock-adjustments/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
