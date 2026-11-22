@extends('layouts.app')

@section('title', 'Stock Transfers — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Transfers')

@section('content')
@php
    $sortLink = fn (string $col) => route('stock-transfers.index', array_filter([
        'search' => request('search'),
        'branch' => request('branch'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc',
    ]));

    // Scoped aggregate query for KPI intelligence strip (respects Outlet Manager location scope)
    $accessibleIds = \App\Services\LocationScope::accessibleLocationIds(auth()->user());
    $transferStatsQuery = \App\Models\StockTransfer::query();
    if ($accessibleIds !== null) {
        $transferStatsQuery->where(fn ($q) => $q->whereIn('from_location_id', $accessibleIds)->orWhereIn('to_location_id', $accessibleIds));
    }
    $transferStats = $transferStatsQuery
        ->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'in-transit' THEN 1 ELSE 0 END) as in_transit_count,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            COALESCE(SUM(total_amount), 0) as total_value,
            COALESCE(SUM(shipping_charges), 0) as total_shipping
        ")
        ->first();

    $totalCount = (int) ($transferStats?->total_count ?? 0);
    $draftCount = (int) ($transferStats?->draft_count ?? 0);
    $inTransitCount = (int) ($transferStats?->in_transit_count ?? 0);
    $receivedCount = (int) ($transferStats?->received_count ?? 0);
    $rejectedCount = (int) ($transferStats?->rejected_count ?? 0);
    $cancelledCount = (int) ($transferStats?->cancelled_count ?? 0);
    $totalValue = (float) ($transferStats?->total_value ?? 0);
    $totalShipping = (float) ($transferStats?->total_shipping ?? 0);
    $completedPct = $totalCount > 0 ? (int) round(($receivedCount / $totalCount) * 100) : 0;

    $pageDraftCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::Draft)->count();
    $pageTransitCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::InTransit)->count();
    $pageReceivedCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::Received)->count();
    $pageRequestedCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::Requested)->count();
    $pageRejectedCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::Rejected)->count();
    $pageCancelledCount = $transfers->filter(fn ($t) => $t->status === \App\Enums\StockTransferStatus::Cancelled)->count();
@endphp

<div x-data="transfersPage()" class="space-y-6">

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
                <span class="font-medium text-ink">Stock Transfers</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Stock Transfers</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($totalCount) }} {{ Str::plural('transfer', $totalCount) }}
                </span>
                @if($inTransitCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning ring-1 ring-warning/20 tabular-nums">
                        <x-icon name="truck" class="size-3.5" />
                        {{ number_format($inTransitCount) }} in transit
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Dispatch, track, and approve stock transfers between warehouses, brand shops, and SR placements.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request('search') || request('sort') || request('branch'))
                <a href="{{ route('stock-transfers.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\StockTransfer::class)
                <a href="{{ route('stock-transfers.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Transfer</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Transfer Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Transfers --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Transfers</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="trips" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span><strong class="text-ink font-semibold">{{ number_format($draftCount) }}</strong> drafts</span>
                @if($cancelledCount > 0)
                    <span>· {{ number_format($cancelledCount) }} cancelled</span>
                @endif
            </div>
        </div>

        {{-- KPI 2: Pending Approval / In Transit --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Pending Approval</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-warning mt-1.5 tabular-nums">
                        {{ number_format($inTransitCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="truck" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Dispatched in-transit awaiting receipt sign-off
            </div>
        </div>

        {{-- KPI 3: Approved / Completed --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Approved / Completed</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-success mt-1.5 tabular-nums">
                        {{ number_format($receivedCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-success transition-all duration-500" style="width: {{ $completedPct }}%;"></div>
                </div>
                <p class="text-xs text-muted">
                    <strong class="text-ink font-semibold">{{ $completedPct }}%</strong> delivery completion rate
                </p>
            </div>
        </div>

        {{-- KPI 4: Total Transfer Value --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Transfer Value</span>
                    <p class="text-xl sm:text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums money truncate" title="{{ \App\Helpers\Money::format($totalValue) }}">
                        {{ \App\Helpers\Money::format($totalValue) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted tabular-nums money">
                Includes <strong class="text-ink font-semibold">{{ \App\Helpers\Money::format($totalShipping) }}</strong> shipping charges
            </div>
        </div>
    </div>

    {{-- Main Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Status Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All Stock Transfers</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ $transfers->count() }} of {{ number_format($transfers->total()) }} transfers · Sorted by <span class="font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($sort)) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button"
                        @click="statusFilter = 'all'"
                        :class="statusFilter === 'all' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>All on page</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="statusFilter === 'all' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-ink'">
                        {{ $transfers->count() }}
                    </span>
                </button>
                <button type="button"
                        @click="statusFilter = 'in-transit'"
                        :class="statusFilter === 'in-transit' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>In Transit</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="statusFilter === 'in-transit' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-warning'">
                        {{ $pageTransitCount }}
                    </span>
                </button>
                <button type="button"
                        @click="statusFilter = 'received'"
                        :class="statusFilter === 'received' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Completed</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="statusFilter === 'received' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-success'">
                        {{ $pageReceivedCount }}
                    </span>
                </button>
                <button type="button"
                        @click="statusFilter = 'draft'"
                        :class="statusFilter === 'draft' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                    <span>Draft</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                          :class="statusFilter === 'draft' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-muted'">
                        {{ $pageDraftCount }}
                    </span>
                </button>
                @if($pageRequestedCount > 0)
                    <button type="button"
                            @click="statusFilter = 'requested'"
                            :class="statusFilter === 'requested' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                        <span>Requested</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                              :class="statusFilter === 'requested' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-brand-500'">
                            {{ $pageRequestedCount }}
                        </span>
                    </button>
                @endif
                @if($pageRejectedCount > 0)
                    <button type="button"
                            @click="statusFilter = 'rejected'"
                            :class="statusFilter === 'rejected' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                        <span>Rejected</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                              :class="statusFilter === 'rejected' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-danger'">
                            {{ $pageRejectedCount }}
                        </span>
                    </button>
                @endif
                @if($pageCancelledCount > 0)
                    <button type="button"
                            @click="statusFilter = 'cancelled'"
                            :class="statusFilter === 'cancelled' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink'"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all cursor-pointer">
                        <span>Cancelled</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px]"
                              :class="statusFilter === 'cancelled' ? 'bg-surface/20 text-on-brand' : 'bg-surface text-danger'">
                            {{ $pageCancelledCount }}
                        </span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('stock-transfers.index') }}" class="flex items-center gap-2 shrink-0">
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

                    <form method="GET" action="{{ route('stock-transfers.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('branch'))<input type="hidden" name="branch" value="{{ request('branch') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search reference no, location, or notes..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('stock-transfers.index', array_filter(['branch' => request('branch'), 'per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>

                    @if(auth()->user()?->hasRole('Super Admin'))
                        <form method="GET" action="{{ route('stock-transfers.index') }}" class="flex items-center gap-2 shrink-0">
                            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                            @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                            @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                            <select name="branch" onchange="this.form.submit()"
                                    class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="">All Branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
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
                                title="Print transfers list">
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
                                  x-text="activeColumnsCount() + '/8'"></span>
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 rounded-2xl border border-line bg-surface p-1.5 shadow-card z-50 text-left">
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
            <table class="data-table w-full text-left border-collapse" id="stock-transfers-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th x-show="columns.transfer_date" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('transfer_date') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Date</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'transfer_date' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.reference_no" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('reference_no') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Reference No</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'reference_no' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.from_location" scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Location (From)</th>
                        <th x-show="columns.to_location" scope="col" class="py-3.5 px-5 text-start text-xs font-bold text-ink">Location (To)</th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('status') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Status</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'status' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.shipping_charges" scope="col" class="py-3.5 px-5 text-end">
                            <a href="{{ $sortLink('shipping_charges') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Shipping Charges</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'shipping_charges' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.total_amount" scope="col" class="py-3.5 px-5 text-end">
                            <a href="{{ $sortLink('total_amount') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Total Amount</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'total_amount' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.additional_notes" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('additional_notes') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Additional Notes</span>
                                <span class="text-[10px] text-brand-500 font-black">{{ $sort === 'additional_notes' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        @canany(['stock-transfers.manage', 'stock-transfers.approve'])
                            <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($transfers as $transfer)
                        @php($transferStatus = $transfer->displayStatusFor(auth()->user()))
                        <tr x-show="statusFilter === 'all' || statusFilter === @js($transfer->status->value)" class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td x-show="columns.transfer_date" data-col="transfer_date" class="py-4 px-5 text-xs text-muted whitespace-nowrap tabular-nums">
                                <span class="block text-sm font-semibold text-ink">{{ $transfer->transfer_date->format('d M Y') }}</span>
                                <span>{{ $transfer->transfer_date->format('H:i') }}</span>
                            </td>
                            <td x-show="columns.reference_no" data-col="reference_no" class="py-4 px-5 text-start whitespace-nowrap">
                                <a href="{{ route('stock-transfers.show', $transfer) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line hover:border-brand-500 hover:text-brand-500 transition-colors tabular-nums">
                                    <span>{{ $transfer->reference_no }}</span>
                                    <x-icon name="arrow-up-right" class="size-3 opacity-60" />
                                </a>
                                @if($transfer->is_request)
                                    <span class="mt-1 block text-[11px] font-semibold text-brand-500">Requested from {{ $transfer->fromLocation?->name ?? '—' }}</span>
                                @endif
                            </td>
                            <td x-show="columns.from_location" data-col="from_location" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-xl bg-soft/70 px-2.5 py-1 text-xs font-semibold text-ink border border-line/80">
                                    <x-icon name="warehouse" class="size-3.5 text-muted" />
                                    <span>{{ $transfer->fromLocation?->name ?? '—' }}</span>
                                </span>
                            </td>
                            <td x-show="columns.to_location" data-col="to_location" class="py-4 px-5 text-start whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 rounded-xl bg-brand-500/10 px-2.5 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                                    <x-svg.arrow-right class="size-3 shrink-0" />
                                    <span>{{ $transfer->toLocation?->name ?? '—' }}</span>
                                </span>
                            </td>
                            <td x-show="columns.status" data-col="status" class="py-4 px-5 text-start whitespace-nowrap">
                                <x-status-badge :status="$transferStatus['value']" :label="$transferStatus['label']" />
                            </td>
                            <td x-show="columns.shipping_charges" data-col="shipping_charges" class="py-4 px-5 text-end text-sm text-muted tabular-nums money whitespace-nowrap">
                                {{ \App\Helpers\Money::format($transfer->shipping_charges) }}
                            </td>
                            <td x-show="columns.total_amount" data-col="total_amount" class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap">
                                {{ \App\Helpers\Money::format($transfer->total_amount) }}
                            </td>
                            <td x-show="columns.additional_notes" data-col="additional_notes" class="py-4 px-5 text-start text-xs text-muted max-w-44 truncate" title="{{ $transfer->additional_notes }}">
                                {{ $transfer->additional_notes ?: '—' }}
                            </td>
                            @canany(['stock-transfers.manage', 'stock-transfers.approve'])
                                <td class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        {{-- Prominent Accept button when eligible --}}
                                        @can('approve', $transfer)
                                            @if(in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit, \App\Enums\StockTransferStatus::Requested], true))
                                                <button type="button"
                                                        @click="openActionModal('approve', @js(route('stock-transfers.approve', $transfer)), @js($transfer->reference_no))"
                                                        class="btn-scale inline-flex items-center gap-1 rounded-xl bg-success px-2.5 py-1.5 text-xs font-semibold text-on-brand shadow-2xs hover:opacity-95 transition-opacity cursor-pointer"
                                                        title="Accept and receive this transfer">
                                                    <x-icon name="check" class="size-3.5" />
                                                    <span>Accept</span>
                                                </button>
                                            @endif
                                        @endcan

                                        {{-- Reject button when eligible --}}
                                        @can('reject', $transfer)
                                            @if($transfer->is_request && $transfer->status === \App\Enums\StockTransferStatus::Requested)
                                                {{-- A stock request is rejected with a reason on its invoice page. --}}
                                                <a href="{{ route('stock-transfers.show', $transfer) }}"
                                                   class="btn-scale inline-flex items-center gap-1 rounded-xl border border-danger/30 bg-danger/10 px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors"
                                                   title="Review this stock request">
                                                    <x-svg.x class="size-3.5" />
                                                    <span>Review</span>
                                                </a>
                                            @elseif(! $transfer->is_request && in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit], true))
                                                <button type="button"
                                                        @click="openActionModal('reject', @js(route('stock-transfers.reject', $transfer)), @js($transfer->reference_no))"
                                                        class="btn-scale inline-flex items-center gap-1 rounded-xl border border-danger/30 bg-danger/10 px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer"
                                                        title="Reject this transfer">
                                                    <x-svg.x class="size-3.5" />
                                                    <span>Reject</span>
                                                </button>
                                            @endif
                                        @endcan

                                        {{-- Withdraw a pending request (requesting branch only) --}}
                                        @if($transfer->created_by === auth()->id() && auth()->user()?->can('withdraw', $transfer))
                                            <form method="POST" action="{{ route('stock-transfers.withdraw', $transfer) }}" class="inline-block"
                                                  onsubmit="return confirm('Withdraw this stock request? The source will not need to decide on it.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                                                        title="Withdraw this stock request">
                                                    <x-svg.x class="size-3.5" />
                                                    <span>Withdraw</span>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Cancel button for the sending side --}}
                                        @can('cancel', $transfer)
                                            @if(in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit], true))
                                                <button type="button"
                                                        @click="openActionModal('cancel', @js(route('stock-transfers.cancel', $transfer)), @js($transfer->reference_no))"
                                                        class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                                                        title="Cancel this transfer">
                                                    <x-svg.x class="size-3.5" />
                                                    <span>Cancel</span>
                                                </button>
                                            @endif
                                        @endcan

                                        {{-- View Invoice --}}
                                        <a href="{{ route('stock-transfers.show', $transfer) }}"
                                           class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="View Invoice">
                                            <x-svg.eye class="size-3.5 text-muted" />
                                            <span>View</span>
                                        </a>

                                        {{-- Print Invoice --}}
                                        <a href="{{ route('stock-transfers.show', $transfer) }}?print=1" target="_blank"
                                           class="btn-scale inline-flex size-8 items-center justify-center rounded-xl border border-line bg-surface text-muted shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="Print Invoice">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                        </a>

                                        {{-- Edit --}}
                                        @can('update', $transfer)
                                            <a href="{{ route('stock-transfers.edit', $transfer) }}"
                                               class="btn-scale inline-flex size-8 items-center justify-center rounded-xl border border-line bg-surface text-muted shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                               title="Edit Transfer">
                                                <x-svg.pencil class="size-3.5" />
                                            </a>
                                        @endcan

                                        {{-- Delete --}}
                                        @can('delete', $transfer)
                                            <button type="button"
                                                    @click="openActionModal('delete', @js(route('stock-transfers.destroy', $transfer)), @js($transfer->reference_no))"
                                                    class="btn-scale inline-flex size-8 items-center justify-center rounded-xl border border-danger/30 bg-danger/10 text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer"
                                                    title="Delete Transfer">
                                                <x-svg.trash-bin class="size-3.5" />
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            @endcanany
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="activeColumnsCount() + 1" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="truck" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No stock transfers found.</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if(request('search'))
                                            No stock transfer matches your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                                        @else
                                            Create your first stock transfer to move inventory between warehouses and outlets.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex items-center gap-2.5">
                                        @if(request('search'))
                                            <a href="{{ route('stock-transfers.index') }}"
                                               class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Search
                                            </a>
                                        @endif
                                        @can('create', App\Models\StockTransfer::class)
                                            <a href="{{ route('stock-transfers.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + New Transfer
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
            {{ $transfers->links() }}
        </div>
    </div>

    {{-- Unified Approve / Reject / Delete Confirmation Modal --}}
    <div x-show="actionModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-overlay backdrop-blur-xs"
         @keydown.escape.window="actionModal.open = false">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6" @click.stop>
            <div class="flex items-start gap-3.5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl ring-1"
                     :class="actionModal.kind === 'approve' ? 'bg-success/10 text-success ring-success/20' : (actionModal.kind === 'cancel' ? 'bg-soft text-muted ring-line' : 'bg-danger/10 text-danger ring-danger/20')">
                    <template x-if="actionModal.kind === 'approve'">
                        <x-icon name="check" class="size-5" />
                    </template>
                    <template x-if="actionModal.kind !== 'approve'">
                        <x-icon name="alert-triangle" class="size-5" />
                    </template>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-ink"
                        x-text="actionModal.kind === 'approve' ? 'Accept & receive transfer?' : (actionModal.kind === 'reject' ? 'Reject stock transfer?' : (actionModal.kind === 'cancel' ? 'Cancel stock transfer?' : 'Delete stock transfer?'))"></h3>
                    <p class="mt-1.5 text-xs text-muted leading-relaxed">
                        <template x-if="actionModal.kind === 'approve'">
                            <span>Confirm receipt of transfer <strong class="text-ink" x-text="actionModal.ref"></strong>. Stock will be posted as received at the destination location.</span>
                        </template>
                        <template x-if="actionModal.kind === 'reject'">
                            <span>Reject transfer <strong class="text-ink" x-text="actionModal.ref"></strong>? It will be marked rejected and any dispatched stock will return to the sender.</span>
                        </template>
                        <template x-if="actionModal.kind === 'cancel'">
                            <span>Cancel transfer <strong class="text-ink" x-text="actionModal.ref"></strong>? The receiving side will be notified and any dispatched stock will return to the source.</span>
                        </template>
                        <template x-if="actionModal.kind === 'delete'">
                            <span>Are you sure you want to delete transfer <strong class="text-ink" x-text="actionModal.ref"></strong>?</span>
                        </template>
                    </p>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                <button type="button" @click="actionModal.open = false"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Close
                </button>
                <form :action="actionModal.url" method="POST">
                    @csrf
                    <input type="hidden" name="_method" :value="actionModal.kind === 'delete' ? 'DELETE' : 'PATCH'">
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer"
                            :class="actionModal.kind === 'approve' ? 'bg-success' : (actionModal.kind === 'cancel' ? 'bg-soft text-ink' : 'bg-danger')">
                        <span x-text="actionModal.kind === 'approve' ? 'Accept / Receive' : (actionModal.kind === 'reject' ? 'Reject Transfer' : (actionModal.kind === 'cancel' ? 'Cancel Transfer' : 'Delete Transfer'))"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function transfersPage() {
    return {
        statusFilter: 'all',
        actionModal: {
            open: false,
            kind: 'approve',
            url: '',
            ref: ''
        },

        columnsDropdownOpen: false,
        columns: {
            transfer_date: true,
            reference_no: true,
            from_location: true,
            to_location: true,
            status: true,
            shipping_charges: true,
            total_amount: true,
            additional_notes: true
        },
        columnList: [
            { key: 'transfer_date', label: 'Date' },
            { key: 'reference_no', label: 'Reference No' },
            { key: 'from_location', label: 'Location (From)' },
            { key: 'to_location', label: 'Location (To)' },
            { key: 'status', label: 'Status' },
            { key: 'shipping_charges', label: 'Shipping Charges' },
            { key: 'total_amount', label: 'Total Amount' },
            { key: 'additional_notes', label: 'Additional Notes' }
        ],

        searchQuery: @js((string) request('search', '')),

        openActionModal(kind, url, ref) {
            this.actionModal = { open: true, kind, url, ref };
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
            return `/stock-transfers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/stock-transfers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery
            });
            window.open(`/stock-transfers/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
