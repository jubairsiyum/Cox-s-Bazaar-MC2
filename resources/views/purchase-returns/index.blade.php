@extends('layouts.app')

@section('title', 'Purchase Returns — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Purchase Returns')

@section('content')
@php
    $filterParams = array_filter([
        'search' => request('search'),
        'supplier_id' => request('supplier_id'),
        'location_id' => request('location_id'),
        'payment_status' => request('payment_status'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
    ], fn ($value) => $value !== null && $value !== '');

    $activeFilterCount = count(array_filter([
        request('supplier_id'),
        request('location_id'),
        request('payment_status'),
        request('date_from'),
        request('date_to'),
    ], fn ($value) => $value !== null && $value !== ''));

    $sortLink = fn (string $col) => route('purchase-returns.index', array_merge($filterParams, array_filter([
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc',
    ])));

    $totalReturnValue = (float) ($totals['grand_total'] ?? 0);
    $totalReturnDue = (float) ($totals['payment_due'] ?? 0);

    $scopedApprovalCounts = \App\Services\LocationScope::scopeQuery(\App\Models\PurchaseReturn::query(), auth()->user())
        ->toBase()
        ->selectRaw('approval_status, count(*) as total')
        ->groupBy('approval_status')
        ->pluck('total', 'approval_status');

    $pendingReturnsCount = (int) ($scopedApprovalCounts[\App\Enums\ApprovalStatus::Pending->value] ?? 0);
    $approvedReturnsCount = (int) ($scopedApprovalCounts[\App\Enums\ApprovalStatus::Approved->value] ?? 0);
@endphp

<div x-data="purchaseReturnsPage(@js([
        'filtersOpen' => $activeFilterCount > 0,
        'filters' => $filterParams,
    ]))" class="space-y-6">

    {{-- Executive Breadcrumb Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Purchasing</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Purchase Returns</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Purchase Returns</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($returns->total()) }} {{ Str::plural('return', $returns->total()) }}
                </span>
                @if($pendingReturnsCount > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning ring-1 ring-warning/20 tabular-nums">
                        <x-icon name="alert-triangle" class="size-3.5" />
                        <span>{{ number_format($pendingReturnsCount) }} awaiting {{ Str::plural('completion', $pendingReturnsCount) }}</span>
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Goods returned to your suppliers — manage supplier returns, stock deductions, and credit balances.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(!empty($filterParams) || request('sort'))
                <a href="{{ route('purchase-returns.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset all filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\PurchaseReturn::class)
                <a href="{{ route('purchase-returns.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Apply Return</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Returns --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Returns</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($returns->total()) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-muted">
                <span>{{ $activeFilterCount > 0 ? 'Matching current filters' : 'Registered supplier returns' }}</span>
                <a href="{{ route('purchase-invoices.index') }}" class="font-semibold text-brand-500 hover:underline inline-flex items-center gap-0.5 shrink-0">
                    <span>Invoices</span>
                    <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- KPI 2: Total Return Value --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Return Value</span>
                    <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate" title="{{ \App\Helpers\Money::format($totalReturnValue) }}">
                        {{ \App\Helpers\Money::format($totalReturnValue) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted tabular-nums">
                Due balance: <strong class="{{ $totalReturnDue > 0 ? 'text-danger' : 'text-success' }} font-semibold">{{ \App\Helpers\Money::format($totalReturnDue) }}</strong>
            </div>
        </div>

        {{-- KPI 3: Legacy Pending --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Awaiting Completion</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight {{ $pendingReturnsCount > 0 ? 'text-warning' : 'text-ink' }} mt-1.5 tabular-nums">
                        {{ number_format($pendingReturnsCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-muted">
                {{ $pendingReturnsCount > 0 ? 'Legacy returns awaiting sign-off' : 'Queue is completely clear' }}
            </p>
        </div>

        {{-- KPI 4: Approved / Completed --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Approved Returns</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-success mt-1.5 tabular-nums">
                        {{ number_format($approvedReturnsCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-muted tabular-nums">
                <strong class="text-ink font-semibold">{{ $totals['paid'] }}</strong> settled · <strong class="text-ink font-semibold">{{ $totals['due'] }}</strong> pending refund
            </p>
        </div>
    </div>

    {{-- Collapsible Filter Drawer --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="w-full flex items-center justify-between px-5 sm:px-6 py-4 text-left hover:bg-soft/40 transition-colors cursor-pointer">
            <span class="flex items-center gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </span>
                <span>
                    <span class="text-sm font-bold text-ink">Filters</span>
                    <span class="block text-xs text-muted">Filter returns by supplier, location, settlement status, or return date</span>
                </span>
                @if($activeFilterCount > 0)
                    <span class="inline-flex items-center rounded-full bg-brand-500 px-2.5 py-0.5 text-[11px] font-bold text-on-brand tabular-nums">
                        {{ $activeFilterCount }} active
                    </span>
                @endif
            </span>
            <x-icon name="chevron" class="size-4 text-muted transition-transform duration-200" ::class="{ 'rotate-180 text-brand-500': filtersOpen }" />
        </button>

        <div x-show="filtersOpen" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="px-5 sm:px-6 pb-5 pt-4 border-t border-line bg-canvas/30">
            <form method="GET" action="{{ route('purchase-returns.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                <div>
                    <label for="return_filter_supplier" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Supplier</label>
                    <select id="return_filter_supplier" name="supplier_id"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="return_filter_location" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Business Location</label>
                    <select id="return_filter_location" name="location_id"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All Locations</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="return_filter_payment" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Payment Status</label>
                    <select id="return_filter_payment" name="payment_status"
                            class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All Statuses</option>
                        @foreach(\App\Enums\PaymentStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="return_filter_from" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Date From</label>
                    <input id="return_filter_from" type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                </div>

                <div>
                    <label for="return_filter_to" class="block text-xs font-semibold uppercase tracking-wider text-muted mb-1.5">Date To</label>
                    <input id="return_filter_to" type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex items-end justify-end gap-2.5">
                    <a href="{{ route('purchase-returns.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface px-4 py-2 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Reset
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Apply Filters</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Unified Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        {{-- Shell Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All Purchase Returns</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ $returns->firstItem() ?? 0 }}–{{ $returns->lastItem() ?? 0 }} of {{ number_format($returns->total()) }} records · Sorted by <span class="font-semibold text-ink">{{ ucwords(str_replace('_', ' ', $sort)) }}</span> ({{ strtoupper($direction) }})
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap text-xs">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 font-semibold text-success tabular-nums">
                    Paid: {{ $totals['paid'] }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-2.5 py-1 font-semibold text-brand-500 tabular-nums">
                    Partial: {{ $totals['partial'] }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-2.5 py-1 font-semibold text-warning tabular-nums">
                    Due: {{ $totals['due'] }}
                </span>
            </div>
        </div>

        {{-- Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Show entries & Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    <form method="GET" action="{{ route('purchase-returns.index') }}" class="flex items-center gap-2 shrink-0">
                        @foreach(array_merge($filterParams, array_filter(['sort' => request('sort'), 'direction' => request('direction')])) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
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

                    <form method="GET" action="{{ route('purchase-returns.index') }}" class="relative flex-1 max-w-md">
                        @foreach(array_filter(['supplier_id' => request('supplier_id'), 'location_id' => request('location_id'), 'payment_status' => request('payment_status'), 'date_from' => request('date_from'), 'date_to' => request('date_to'), 'per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')]) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Search return ref, parent invoice, or supplier..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                                <a href="{{ route('purchase-returns.index', array_filter(['supplier_id' => request('supplier_id'), 'location_id' => request('location_id'), 'payment_status' => request('payment_status'), 'date_from' => request('date_from'), 'date_to' => request('date_to'), 'per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Segmented Export Bar + Column Visibility --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export purchase returns to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export purchase returns to Excel">
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
                                title="Print purchase returns list">
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
                            <span class="rounded-full bg-soft px-1.5 py-0.5 text-[10px] font-bold text-brand-500 tabular-nums"
                                  x-text="activeColumnsCount() + '/9'"></span>
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
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

        {{-- Data Table --}}
        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse" id="purchase-returns-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th x-show="columns.return_date" scope="col" class="py-3.5 px-4 whitespace-nowrap">
                            <a href="{{ $sortLink('return_date') }}" class="inline-flex items-center gap-1 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Date</span>
                                <span class="text-[10px] text-brand-500">{{ $sort === 'return_date' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.reference_no" scope="col" class="py-3.5 px-4 whitespace-nowrap">
                            <a href="{{ $sortLink('reference_no') }}" class="inline-flex items-center gap-1 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Reference No</span>
                                <span class="text-[10px] text-brand-500">{{ $sort === 'reference_no' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.parent_purchase" scope="col" class="py-3.5 px-4 font-bold text-ink whitespace-nowrap">Parent Purchase</th>
                        <th x-show="columns.location" scope="col" class="py-3.5 px-4 font-bold text-ink whitespace-nowrap">Location</th>
                        <th x-show="columns.supplier" scope="col" class="py-3.5 px-4 font-bold text-ink whitespace-nowrap">Supplier</th>
                        <th x-show="columns.approval_status" scope="col" class="py-3.5 px-4 font-bold text-ink whitespace-nowrap">Approval Status</th>
                        <th x-show="columns.payment_status" scope="col" class="py-3.5 px-4 whitespace-nowrap">
                            <a href="{{ $sortLink('payment_status') }}" class="inline-flex items-center gap-1 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Payment Status</span>
                                <span class="text-[10px] text-brand-500">{{ $sort === 'payment_status' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.grand_total" scope="col" class="py-3.5 px-4 text-right whitespace-nowrap">
                            <a href="{{ $sortLink('grand_total') }}" class="inline-flex items-center justify-end gap-1 font-bold text-ink hover:text-brand-500 transition-colors">
                                <span>Grand Total</span>
                                <span class="text-[10px] text-brand-500">{{ $sort === 'grand_total' ? ($direction === 'asc' ? '▲' : '▼') : '⇅' }}</span>
                            </a>
                        </th>
                        <th x-show="columns.payment_due" scope="col" class="py-3.5 px-4 text-right font-bold text-ink whitespace-nowrap">
                            <span class="inline-flex items-center gap-1">
                                <span>Payment due</span>
                                <span title="Outstanding amount the supplier still owes for this return"
                                      class="inline-flex size-4 items-center justify-center rounded-full bg-brand-500/15 text-brand-500 text-[10px] font-bold cursor-help">i</span>
                            </span>
                        </th>
                        @can('purchase-returns.manage')
                            <th scope="col" class="py-3.5 px-4 text-right font-bold text-ink whitespace-nowrap">Action</th>
                        @endcan
                    </tr>
                </thead>

                <tbody class="divide-y divide-line bg-surface">
                    @forelse($returns as $return)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td x-show="columns.return_date" data-col="return_date" class="py-3.5 px-4 text-xs sm:text-sm text-muted whitespace-nowrap tabular-nums">
                                <span class="font-medium text-ink block">{{ $return->return_date->format('d M Y') }}</span>
                                <span class="text-[11px] text-muted">{{ $return->return_date->format('H:i') }}</span>
                            </td>
                            <td x-show="columns.reference_no" data-col="reference_no" class="py-3.5 px-4 whitespace-nowrap">
                                <a href="{{ route('purchase-returns.show', $return) }}"
                                   class="text-sm font-bold text-ink group-hover:text-brand-500 transition-colors tabular-nums">
                                    {{ $return->reference_no }}
                                </a>
                            </td>
                            <td x-show="columns.parent_purchase" data-col="parent_purchase" class="py-3.5 px-4 whitespace-nowrap">
                                @if($return->purchaseOrder)
                                    <a href="{{ route('purchase-invoices.show', $return->purchaseOrder) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-soft px-2.5 py-1 text-xs font-semibold text-ink border border-line hover:border-brand-500/40 hover:text-brand-500 transition-colors tabular-nums">
                                        <span>{{ $return->purchaseOrder->reference_no }}</span>
                                        <x-icon name="arrow-up-right" class="size-3 text-muted" />
                                    </a>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td x-show="columns.location" data-col="location" class="py-3.5 px-4 text-sm text-ink/90 whitespace-nowrap">
                                {{ $return->location?->name ?? '—' }}
                            </td>
                            <td x-show="columns.supplier" data-col="supplier" class="py-3.5 px-4 text-sm font-medium text-ink whitespace-nowrap">
                                {{ $return->supplier?->name ?? '—' }}
                            </td>
                            <td x-show="columns.approval_status" data-col="approval_status" class="py-3.5 px-4 whitespace-nowrap">
                                <x-status-badge :status="$return->approval_status->value" :label="$return->approval_status->label()" />
                            </td>
                            <td x-show="columns.payment_status" data-col="payment_status" class="py-3.5 px-4 whitespace-nowrap">
                                <x-status-badge :status="$return->payment_status->value" :label="$return->payment_status->label()" />
                            </td>
                            <td x-show="columns.grand_total" data-col="grand_total" class="py-3.5 px-4 text-right text-sm font-bold text-ink whitespace-nowrap tabular-nums">
                                {{ \App\Helpers\Money::format($return->grand_total) }}
                            </td>
                            <td x-show="columns.payment_due" data-col="payment_due" class="py-3.5 px-4 text-right text-sm whitespace-nowrap tabular-nums">
                                <span class="font-semibold {{ (float) $return->payment_due > 0 ? 'text-warning' : 'text-success' }}">
                                    {{ \App\Helpers\Money::format($return->payment_due) }}
                                </span>
                            </td>
                            @can('purchase-returns.manage')
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <a href="{{ route('purchase-returns.show', $return) }}"
                                           class="btn-scale inline-flex items-center gap-1 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="View return details">
                                            <x-svg.eye class="size-3.5" />
                                            <span>View</span>
                                        </a>

                                        @can('approve', $return)
                                            @if($return->approval_status === \App\Enums\ApprovalStatus::Pending)
                                                <button type="button"
                                                        @click="openActionModal('approve', @js(['ref' => $return->reference_no, 'supplier' => $return->supplier?->name ?? '—', 'total' => \App\Helpers\Money::format($return->grand_total), 'url' => route('purchase-returns.approve', $return)]))"
                                                        class="btn-scale inline-flex items-center gap-1 rounded-xl border border-success/30 bg-success/10 px-2.5 py-1.5 text-xs font-semibold text-success hover:bg-success hover:text-on-brand transition-colors cursor-pointer"
                                                        title="Receive this legacy pending return">
                                                    <x-icon name="check" class="size-3.5" />
                                                    <span>Approve</span>
                                                </button>
                                            @endif
                                        @endcan

                                        <div x-data="dropdown" @click.outside="open = false" @keydown.escape.window="open = false" class="relative inline-flex">
                                            <button type="button" @click.stop="toggle()"
                                                    class="btn-scale inline-flex size-8 items-center justify-center rounded-xl border border-line bg-surface text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                                                    title="More actions"
                                                    aria-label="More actions">
                                                <x-icon name="dots" class="size-4" />
                                            </button>

                                            <div x-ref="dropdown" x-show="open" x-cloak
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="w-44 rounded-2xl border border-line bg-surface p-1.5 shadow-card text-left">
                                                <a href="{{ route('purchase-returns.show', $return) }}?print=1" target="_blank"
                                                   class="w-full flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors">
                                                    <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                    <span>Print Return</span>
                                                </a>

                                                @can('update', $return)
                                                    <a href="{{ route('purchase-returns.edit', $return) }}"
                                                       class="w-full flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors">
                                                        <x-svg.pencil class="size-3.5 text-muted" />
                                                        <span>Edit Return</span>
                                                    </a>
                                                @endcan

                                                @can('reject', $return)
                                                    @if($return->approval_status === \App\Enums\ApprovalStatus::Pending)
                                                        <div class="my-1 border-t border-line"></div>
                                                        <button type="button"
                                                                @click="open = false; openActionModal('reject', @js(['ref' => $return->reference_no, 'supplier' => $return->supplier?->name ?? '—', 'total' => \App\Helpers\Money::format($return->grand_total), 'url' => route('purchase-returns.reject', $return)]))"
                                                                class="w-full flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-danger hover:bg-danger/10 transition-colors cursor-pointer">
                                                            <x-svg.x class="size-3.5" />
                                                            <span>Reject Return</span>
                                                        </button>
                                                    @endif
                                                @endcan

                                                @can('delete', $return)
                                                    <div class="my-1 border-t border-line"></div>
                                                    <button type="button"
                                                            @click="open = false; openActionModal('delete', @js(['ref' => $return->reference_no, 'supplier' => $return->supplier?->name ?? '—', 'total' => \App\Helpers\Money::format($return->grand_total), 'url' => route('purchase-returns.destroy', $return)]))"
                                                            class="w-full flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-danger hover:bg-danger/10 transition-colors cursor-pointer">
                                                        <x-svg.trash-bin class="size-3.5" />
                                                        <span>Delete</span>
                                                    </button>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="box" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No purchase returns found</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if(!empty($filterParams))
                                            No return records match your active search or filter criteria.
                                        @else
                                            Initiate a purchase return against a supplier invoice whenever goods need to be sent back.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex items-center gap-2.5">
                                        @if(!empty($filterParams))
                                            <a href="{{ route('purchase-returns.index') }}"
                                               class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Filters
                                            </a>
                                        @endif
                                        @can('create', App\Models\PurchaseReturn::class)
                                            <a href="{{ route('purchase-returns.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                Apply Return
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr class="border-t-2 border-line bg-soft/70 text-xs sm:text-sm">
                        <td colspan="6" class="py-3.5 px-4 font-bold text-ink">Total Summary:</td>
                        <td class="py-3.5 px-4 text-xs text-muted whitespace-nowrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span>Due: <strong class="text-ink tabular-nums">{{ $totals['due'] }}</strong></span>
                                <span>·</span>
                                <span>Paid: <strong class="text-success tabular-nums">{{ $totals['paid'] }}</strong></span>
                                <span>·</span>
                                <span>Partial: <strong class="text-brand-500 tabular-nums">{{ $totals['partial'] }}</strong></span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 text-right font-extrabold text-ink whitespace-nowrap tabular-nums">
                            {{ \App\Helpers\Money::format($totals['grand_total']) }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-extrabold {{ $totalReturnDue > 0 ? 'text-warning' : 'text-success' }} whitespace-nowrap tabular-nums">
                            {{ \App\Helpers\Money::format($totals['payment_due']) }}
                        </td>
                        @can('purchase-returns.manage')
                            <td class="py-3.5 px-4"></td>
                        @endcan
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $returns->links() }}
        </div>
    </div>

    {{-- Workflow Confirmation Modal (Approve / Reject / Delete) --}}
    <div x-show="actionModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeActionModal()">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6 sm:p-7 relative my-8"
             @click.stop
             x-show="actionModal.open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <form :action="actionModal.url" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="actionModal.type === 'delete' ? 'DELETE' : 'PATCH'">

                <div class="flex items-start justify-between pb-4 border-b border-line">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl"
                             :class="actionModal.type === 'approve' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20'">
                            <template x-if="actionModal.type === 'approve'">
                                <x-icon name="check" class="size-5" />
                            </template>
                            <template x-if="actionModal.type !== 'approve'">
                                <x-icon name="alert-triangle" class="size-5" />
                            </template>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-ink"
                                x-text="actionModal.type === 'approve' ? 'Approve Purchase Return' : (actionModal.type === 'reject' ? 'Reject Purchase Return' : 'Delete Purchase Return')"></h3>
                            <p class="text-xs text-muted mt-0.5 tabular-nums" x-text="actionModal.ref"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeActionModal()"
                            class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                            aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                <div class="py-5 space-y-4">
                    <div class="rounded-2xl border border-line bg-soft/60 p-4 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Supplier</span>
                            <span class="font-semibold text-ink" x-text="actionModal.supplier"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Return Value</span>
                            <span class="font-bold text-ink tabular-nums" x-text="actionModal.total"></span>
                        </div>
                    </div>

                    <p class="text-sm text-muted leading-relaxed"
                       x-text="actionModal.type === 'approve'
                           ? 'Approving deducts the returned quantities from inventory stock and updates the supplier return due credit.'
                           : (actionModal.type === 'reject'
                               ? 'Rejecting marks this return as declined. Stock quantities and supplier balances will remain unchanged.'
                               : 'Deleting removes this purchase return and restores stock quantities if it had already been approved.')"></p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                    <button type="button" @click="closeActionModal()"
                            class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            :class="actionModal.type === 'approve' ? 'bg-success hover:bg-success/90 text-on-brand' : 'bg-danger hover:bg-danger/90 text-on-brand'"
                            class="btn-scale rounded-xl px-5 py-2 text-xs sm:text-sm font-semibold shadow-sm transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <span x-text="actionModal.type === 'approve' ? 'Approve Return' : (actionModal.type === 'reject' ? 'Confirm Reject' : 'Delete Return')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function purchaseReturnsPage(config) {
    return {
        filtersOpen: Boolean(config?.filtersOpen),
        filters: config?.filters || {},
        columnsDropdownOpen: false,
        actionModal: {
            open: false,
            type: 'approve',
            ref: '',
            supplier: '',
            total: '',
            url: ''
        },
        columns: {
            return_date: true,
            reference_no: true,
            parent_purchase: true,
            location: true,
            supplier: true,
            approval_status: true,
            payment_status: true,
            grand_total: true,
            payment_due: true
        },
        columnList: [
            { key: 'return_date', label: 'Date' },
            { key: 'reference_no', label: 'Reference No' },
            { key: 'parent_purchase', label: 'Parent Purchase' },
            { key: 'location', label: 'Location' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'approval_status', label: 'Approval Status' },
            { key: 'payment_status', label: 'Payment Status' },
            { key: 'grand_total', label: 'Grand Total' },
            { key: 'payment_due', label: 'Payment Due' }
        ],

        openActionModal(type, payload) {
            this.actionModal = {
                open: true,
                type,
                ref: payload.ref || '',
                supplier: payload.supplier || '—',
                total: payload.total || '৳0.00',
                url: payload.url || ''
            };
        },

        closeActionModal() {
            this.actionModal.open = false;
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

        buildQueryParams() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                ...this.filters
            });
            return params.toString();
        },

        exportUrl(format) {
            return `/purchase-returns/export/${format}?${this.buildQueryParams()}`;
        },

        openPdf() {
            window.open(`/purchase-returns/pdf-view?${this.buildQueryParams()}`, '_blank');
        },

        printTable() {
            window.open(`/purchase-returns/print-view?${this.buildQueryParams()}`, '_blank');
        }
    };
}
</script>
@endsection
