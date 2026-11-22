@extends('layouts.app')

@section('title', 'Warehouses — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Warehouses')

@section('content')
@php
    $sortLink = fn (string $col) => route('warehouses.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));

    // Single lightweight aggregate query for KPI intelligence strip (zero N+1)
    $locationStats = \App\Models\Location::query()
        ->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN type = 'warehouse' THEN 1 ELSE 0 END) as warehouse_count,
            SUM(CASE WHEN type = 'brand_shop' THEN 1 ELSE 0 END) as brand_shop_count,
            SUM(CASE WHEN type = 'sr_placement' THEN 1 ELSE 0 END) as sr_placement_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
        ")
        ->first();

    $totalLocations = (int) ($locationStats?->total_count ?? 0);
    $warehouseCount = (int) ($locationStats?->warehouse_count ?? 0);
    $brandShopCount = (int) ($locationStats?->brand_shop_count ?? 0);
    $srPlacementCount = (int) ($locationStats?->sr_placement_count ?? 0);
    $outletsCount = $brandShopCount + $srPlacementCount;
    $activeCount = (int) ($locationStats?->active_count ?? 0);
    $inactiveCount = max(0, $totalLocations - $activeCount);
    $activeRatePct = $totalLocations > 0 ? (int) round(($activeCount / $totalLocations) * 100) : 0;

    // Current page filter tab counts
    $pageWarehouseCount = $locations->filter(fn ($l) => $l->type === \App\Enums\LocationType::Warehouse)->count();
    $pageOutletCount = $locations->filter(fn ($l) => $l->type !== \App\Enums\LocationType::Warehouse)->count();
    $pageActiveCount = $locations->filter(fn ($l) => $l->status === 'active')->count();
@endphp

<div x-data="warehousesPage()" class="space-y-6">

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
                <span class="font-medium text-ink">Warehouses</span>
            </nav>

            {{-- Title & Live Count Pill --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Warehouses</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($totalLocations) }} {{ Str::plural('location', $totalLocations) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage business locations (warehouses, brand shops, SR placements) used by stock transfers and outlet operations.</p>
        </div>

        {{-- Right Actions: View Switcher + Reset + Add Location --}}
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
                <a href="{{ route('warehouses.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\Location::class)
                <a href="{{ route('warehouses.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Location</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Location Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Locations --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Locations</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalLocations) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="warehouse" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-500/10 px-2 py-0.5 text-[11px] font-semibold text-brand-500">
                    {{ number_format($warehouseCount) }} hubs
                </span>
                <span>· {{ number_format($outletsCount) }} outlets & SR</span>
            </div>
        </div>

        {{-- KPI 2: Warehouses --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Warehouses</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($warehouseCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Primary bulk storage & dispatch hubs
            </div>
        </div>

        {{-- KPI 3: Outlets & Placements --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Outlets</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($outletsCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="store" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span><strong class="text-ink font-semibold">{{ number_format($brandShopCount) }}</strong> brand shops</span>
                <span>· <strong class="text-ink font-semibold">{{ number_format($srPlacementCount) }}</strong> SR placements</span>
            </div>
        </div>

        {{-- KPI 4: Active Locations --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Locations</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($activeCount) }}
                        <span class="text-sm font-semibold text-muted">/ {{ number_format($totalLocations) }}</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-success transition-all duration-500" style="width: {{ $activeRatePct }}%;"></div>
                </div>
                <p class="text-xs text-muted">
                    <strong class="text-ink font-semibold">{{ $activeRatePct }}%</strong> operational
                    @if($inactiveCount > 0) · {{ number_format($inactiveCount) }} inactive @endif
                </p>
            </div>
        </div>
    </div>

    {{-- Main Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Type Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All your locations</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ $locations->count() }} of {{ number_format($locations->total()) }} locations · Sorted by <span class="font-semibold text-ink">{{ ucfirst($sort) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            {{-- Quick Page Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button"
                        @click="typeFilter = 'all'"
                        :class="typeFilter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>All on page</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'all' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-ink'">
                        {{ $locations->count() }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'warehouse'"
                        :class="typeFilter === 'warehouse' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Warehouses</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'warehouse' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-brand-500'">
                        {{ $pageWarehouseCount }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'outlet'"
                        :class="typeFilter === 'outlet' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Outlets & SR</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'outlet' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-warning'">
                        {{ $pageOutletCount }}
                    </span>
                </button>
                <button type="button"
                        @click="typeFilter = 'active'"
                        :class="typeFilter === 'active' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Active</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="typeFilter === 'active' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-success'">
                        {{ $pageActiveCount }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    {{-- Per Page Selector --}}
                    <form method="GET" action="{{ route('warehouses.index') }}" class="flex items-center gap-2 shrink-0">
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
                    <form method="GET" action="{{ route('warehouses.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search locations by name, code, address, or phone..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('warehouses.index', array_filter(['per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right Quick Sort Controls --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <a href="{{ $sortLink('name') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                        <span>Name</span>
                        <span class="text-[10px] text-brand-500 font-black">
                            {{ $sort === 'name' ? ($direction === 'asc' ? 'A→Z' : 'Z→A') : '⇅' }}
                        </span>
                    </a>
                    <a href="{{ $sortLink('type') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                        <span>Type</span>
                        <span class="text-[10px] text-brand-500 font-black">
                            {{ $sort === 'type' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}
                        </span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ===================================================================
             VIEW 1: LOCATION CARDS SHOWCASE
        =================================================================== --}}
        <div x-show="viewMode === 'grid'" class="p-5 sm:p-6 bg-canvas/20">
            @if($locations->isNotEmpty())
                <div class="grid gap-4 sm:gap-5 md:grid-cols-2 xl:grid-cols-3" data-stagger>
                    @foreach($locations as $location)
                        @php
                            $isWarehouse = $location->type === \App\Enums\LocationType::Warehouse;
                            $isBrandShop = $location->type === \App\Enums\LocationType::BrandShop;
                            $typeIcon = $isWarehouse ? 'warehouse' : ($isBrandShop ? 'store' : 'drivers');
                            $typeBadgeClass = $isWarehouse
                                ? 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20'
                                : ($isBrandShop
                                    ? 'bg-warning/10 text-warning ring-1 ring-warning/20'
                                    : 'bg-success/10 text-success ring-1 ring-success/20');
                        @endphp

                        <article x-show="matchesTypeFilter(@js($location->type->value), @js($location->status))"
                                 style="--item-i: {{ $loop->index }}"
                                 class="group relative flex flex-col justify-between rounded-2xl border border-line bg-surface p-5 shadow-2xs transition-all duration-200 hover:border-brand-500/40 hover:shadow-card">

                            <div>
                                {{-- Card Top Row: Icon + Name + Code + Status --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $typeBadgeClass }} group-hover:scale-105 transition-transform duration-200">
                                            <x-icon :name="$typeIcon" class="size-6" />
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-ink truncate group-hover:text-brand-500 transition-colors" title="{{ $location->name }}">
                                                {{ $location->name }}
                                            </h3>
                                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                <span class="inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-[11px] font-bold text-ink border border-line tabular-nums">
                                                    {{ $location->code ?: 'NO-CODE' }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $typeBadgeClass }}">
                                                    {{ $location->type->label() }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="shrink-0">
                                        <x-status-badge :status="$location->status" />
                                    </div>
                                </div>

                                {{-- Address & Contact Details --}}
                                <div class="mt-4 rounded-xl bg-soft/60 border border-line/70 px-3.5 py-3 space-y-2 text-xs">
                                    <div class="flex items-start gap-2 text-muted">
                                        <svg class="size-3.5 shrink-0 mt-0.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="text-ink/90 leading-snug">{{ $location->address ?: 'No street address recorded' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-muted">
                                        <x-icon name="phone" class="size-3.5 shrink-0 text-brand-500" />
                                        <span class="text-ink/90 tabular-nums">{{ $location->phone ?: 'No contact phone' }}</span>
                                    </div>
                                </div>

                                {{-- Note --}}
                                @if($location->note)
                                    <p class="mt-3 text-xs text-muted leading-relaxed line-clamp-2" title="{{ $location->note }}">
                                        {{ $location->note }}
                                    </p>
                                @endif
                            </div>

                            {{-- Card Footer: Quick Edit / Delete --}}
                            @can('warehouses.manage')
                                <div class="mt-4 pt-3.5 border-t border-line/80 flex items-center justify-between gap-2">
                                    <span class="text-[11px] text-muted">
                                        ID #{{ $location->id }}
                                    </span>

                                    <div class="flex items-center gap-2">
                                        @can('update', $location)
                                            <a href="{{ route('warehouses.edit', $location) }}"
                                               class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan

                                        @can('delete', $location)
                                            <button type="button"
                                                    @click="confirmDelete(@js(route('warehouses.destroy', $location)), @js($location->name))"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-danger/30 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer">
                                                <x-svg.trash-bin class="size-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            @endcan
                        </article>
                    @endforeach
                </div>

                {{-- Client-Side Filter Empty Notice --}}
                <div x-show="(typeFilter === 'warehouse' && {{ $pageWarehouseCount }} === 0) || (typeFilter === 'outlet' && {{ $pageOutletCount }} === 0) || (typeFilter === 'active' && {{ $pageActiveCount }} === 0)"
                     x-cloak
                     class="py-10 px-6 text-center">
                    <p class="text-sm font-semibold text-ink">No locations match this filter on the current page.</p>
                    <button type="button" @click="typeFilter = 'all'"
                            class="mt-3 rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-brand-500 hover:bg-soft transition-colors cursor-pointer">
                        Show all locations on page
                    </button>
                </div>
            @else
                <div class="py-14 px-6 text-center">
                    <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                        <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                            <x-icon name="warehouse" class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-ink">No locations found</h3>
                        <p class="text-xs text-muted mt-1.5 leading-relaxed">
                            @if(request('search'))
                                No warehouse or outlet matches your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                            @else
                                Add your first warehouse, brand shop, or SR placement to track stock movements across locations.
                            @endif
                        </p>
                        <div class="mt-5 flex items-center gap-2.5">
                            @if(request('search'))
                                <a href="{{ route('warehouses.index') }}"
                                   class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                    Clear Search
                                </a>
                            @endif
                            @can('create', App\Models\Location::class)
                                <a href="{{ route('warehouses.create') }}"
                                   class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                    + Add Location
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ===================================================================
             VIEW 2: COMPACT DATA TABLE VIEW
        =================================================================== --}}
        <div x-show="viewMode === 'table'" x-cloak class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse" id="warehouses-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Name</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'name' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('code') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Code</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'code' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('type') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Type</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'type' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('status') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Status</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'status' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Address</th>
                        <th scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Phone</th>
                        @can('warehouses.manage')
                            <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($locations as $location)
                        @php
                            $isWarehouse = $location->type === \App\Enums\LocationType::Warehouse;
                            $isBrandShop = $location->type === \App\Enums\LocationType::BrandShop;
                            $typeIcon = $isWarehouse ? 'warehouse' : ($isBrandShop ? 'store' : 'drivers');
                            $typeBadgeClass = $isWarehouse
                                ? 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20'
                                : ($isBrandShop
                                    ? 'bg-warning/10 text-warning ring-1 ring-warning/20'
                                    : 'bg-success/10 text-success ring-1 ring-success/20');
                        @endphp
                        <tr x-show="matchesTypeFilter(@js($location->type->value), @js($location->status))" class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $typeBadgeClass }}">
                                        <x-icon :name="$typeIcon" class="size-4" />
                                    </div>
                                    <div>
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">{{ $location->name }}</span>
                                        @if($location->note)
                                            <span class="block text-xs text-muted max-w-xs truncate">{{ $location->note }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-5 text-start">
                                @if($location->code)
                                    <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line tabular-nums">
                                        {{ $location->code }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-start">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeBadgeClass }}">
                                    {{ $location->type->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-start">
                                <x-status-badge :status="$location->status" />
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted max-w-xs truncate">{{ $location->address ?: '—' }}</td>
                            <td class="py-4 px-5 text-start text-sm text-muted tabular-nums whitespace-nowrap">{{ $location->phone ?: '—' }}</td>
                            @can('warehouses.manage')
                                <td class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        @can('update', $location)
                                            <a href="{{ route('warehouses.edit', $location) }}"
                                               class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan
                                        @can('delete', $location)
                                            <button type="button"
                                                    @click="confirmDelete(@js(route('warehouses.destroy', $location)), @js($location->name))"
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
                            <td colspan="7" class="py-12 px-6 text-center text-sm font-medium text-muted">
                                No locations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $locations->links() }}
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="deleteModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-overlay backdrop-blur-xs"
         @keydown.escape.window="deleteModalOpen = false">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6" @click.stop>
            <div class="flex items-start gap-3.5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-ink">Delete location?</h3>
                    <p class="mt-1.5 text-xs text-muted leading-relaxed">
                        Are you sure you want to delete <strong class="text-ink" x-text="deleteName"></strong>? This location will be archived and removed from active transfer lists.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                <button type="button" @click="deleteModalOpen = false"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Cancel
                </button>
                <form :action="deleteAction" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                        <x-svg.trash-bin class="size-3.5" />
                        <span>Delete Location</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    function warehousesPage() {
        return {
            viewMode: localStorage.getItem('warehouses_view_mode') || 'grid',
            typeFilter: 'all',
            deleteModalOpen: false,
            deleteAction: '',
            deleteName: '',

            setViewMode(mode) {
                this.viewMode = mode;
                try {
                    localStorage.setItem('warehouses_view_mode', mode);
                } catch (e) {}
            },

            matchesTypeFilter(type, status) {
                if (this.typeFilter === 'warehouse') {
                    return type === 'warehouse';
                }
                if (this.typeFilter === 'outlet') {
                    return type !== 'warehouse';
                }
                if (this.typeFilter === 'active') {
                    return status === 'active';
                }
                return true;
            },

            confirmDelete(action, name) {
                this.deleteAction = action;
                this.deleteName = name;
                this.deleteModalOpen = true;
            }
        };
    }
</script>
@endsection
