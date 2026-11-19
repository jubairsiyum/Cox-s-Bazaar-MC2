@extends('layouts.app')

@section('title', 'Suppliers List — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Suppliers List')

@section('content')
@php
    $currentSearch = (string) request('search', '');
    $currentStatus = (string) request('status', '');
    $currencySymbol = \App\Models\Setting::get('currency_symbol', '৳');

    // Single lightweight aggregate query for KPI intelligence strip (zero N+1)
    $supplierStats = \App\Models\Supplier::query()
        ->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
            SUM(CASE WHEN purchase_due > 0 THEN 1 ELSE 0 END) as with_due_count,
            SUM(CASE WHEN purchase_return_due > 0 THEN 1 ELSE 0 END) as with_return_due_count,
            COALESCE(SUM(opening_balance), 0) as total_opening_balance
        ")
        ->first();

    $totalSuppliers = (int) ($supplierStats->total_count ?? 0);
    $activeSuppliers = (int) ($supplierStats->active_count ?? 0);
    $inactiveSuppliers = (int) ($supplierStats->inactive_count ?? 0);
    $withDueCount = (int) ($supplierStats->with_due_count ?? 0);
    $withReturnDueCount = (int) ($supplierStats->with_return_due_count ?? 0);
    $totalOpeningBalance = (float) ($supplierStats->total_opening_balance ?? 0);
    $activeRatePct = $totalSuppliers > 0 ? (int) round(($activeSuppliers / $totalSuppliers) * 100) : 0;

    $statusFilterUrl = fn (?string $statusValue) => route('suppliers.index', array_filter([
        'search' => $currentSearch !== '' ? $currentSearch : null,
        'per_page' => request('per_page'),
        'status' => $statusValue,
    ]));
@endphp

<div x-data="suppliersPage()" class="space-y-6">

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
                <span class="text-muted">Parties</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Suppliers List</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Suppliers List</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($suppliers->total()) }} {{ Str::plural('supplier', $suppliers->total()) }}
                </span>
                @if($currentStatus !== '')
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $currentStatus === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                        <span>Filtered: {{ ucfirst($currentStatus) }}</span>
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage wholesale distributors, monitor purchase &amp; return dues, and record supplier settlements.</p>
        </div>

        {{-- Right Actions --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('suppliers.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('import', App\Models\Supplier::class)
                <a href="{{ route('suppliers.import') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors shadow-2xs">
                    <x-svg.upload class="size-4 text-muted" />
                    <span>Import</span>
                </a>
            @endcan

            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Supplier</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Supplier & Financial Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Suppliers --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Suppliers</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalSuppliers) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="truck" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success tabular-nums">
                    {{ number_format($activeSuppliers) }} active
                </span>
                <span>· {{ number_format($inactiveSuppliers) }} inactive</span>
            </div>
        </div>

        {{-- KPI 2: Total Purchase Due --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Purchase Due</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums truncate {{ (float) $totalPurchaseDue > 0 ? 'text-danger' : 'text-ink' }}">
                        {{ $currencySymbol }}{{ number_format((float) $totalPurchaseDue, 2) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl {{ (float) $totalPurchaseDue > 0 ? 'bg-danger/10 text-danger ring-1 ring-danger/20' : 'bg-success/10 text-success ring-1 ring-success/20' }}">
                    <x-icon name="wallet" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                <strong class="text-ink font-semibold tabular-nums">{{ number_format($withDueCount) }}</strong> {{ Str::plural('supplier', $withDueCount) }} with payable balance
            </div>
        </div>

        {{-- KPI 3: Purchase Return Due --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Return Due</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums truncate {{ (float) $totalPurchaseReturnDue > 0 ? 'text-warning' : 'text-ink' }}">
                        {{ $currencySymbol }}{{ number_format((float) $totalPurchaseReturnDue, 2) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="payments" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                <strong class="text-ink font-semibold tabular-nums">{{ number_format($withReturnDueCount) }}</strong> {{ Str::plural('supplier', $withReturnDueCount) }} with return credit
            </div>
        </div>

        {{-- KPI 4: Active Supplier Rate & Opening Balance --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Partners</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ $activeRatePct }}<span class="text-base font-semibold text-muted">%</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $activeRatePct }}%;"></div>
                </div>
                <p class="text-xs text-muted tabular-nums">
                    Opening bal: <strong class="text-ink font-semibold">{{ $currencySymbol }}{{ number_format($totalOpeningBalance, 2) }}</strong>
                </p>
            </div>
        </div>
    </div>

    {{-- Unified Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Status Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Supplier Directory</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($suppliers->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $suppliers->firstItem() }}–{{ $suppliers->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($suppliers->total()) }}</span> records
                    @else
                        No supplier records match the current filters
                    @endif
                </p>
            </div>

            {{-- Quick Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ $statusFilterUrl(null) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === '' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>All</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === '' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-ink' }}">
                        {{ number_format($totalSuppliers) }}
                    </span>
                </a>
                <a href="{{ $statusFilterUrl('active') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'active' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Active</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === 'active' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-success' }}">
                        {{ number_format($activeSuppliers) }}
                    </span>
                </a>
                <a href="{{ $statusFilterUrl('inactive') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'inactive' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Inactive</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === 'inactive' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-danger' }}">
                        {{ number_format($inactiveSuppliers) }}
                    </span>
                </a>
            </div>
        </div>

        {{-- Interactive Command Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3.5">
                {{-- Left: Per Page + Search --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    {{-- Per Page Selector --}}
                    <form method="GET" action="{{ route('suppliers.index') }}" class="flex items-center gap-2 shrink-0">
                        @if($currentSearch !== '')<input type="hidden" name="search" value="{{ $currentSearch }}">@endif
                        @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
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
                    <form method="GET" action="{{ route('suppliers.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ $currentSearch }}"
                                   placeholder="Search ID, supplier name, mobile, email, or TAX..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if($currentSearch !== '')
                                <a href="{{ route('suppliers.index', array_filter(['per_page' => request('per_page'), 'status' => $currentStatus !== '' ? $currentStatus : null])) }}"
                                   class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                   title="Clear search">
                                    <x-svg.x class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Segmented Export Bar + Column Visibility Dropdown --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                        <button type="button" @click="copyTable()"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                                title="Copy visible rows to clipboard">
                            <x-svg.copy class="size-3.5 text-muted" />
                            <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                        </button>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('csv')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export filtered suppliers to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export filtered suppliers to Excel">
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
                                title="Print suppliers list">
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
                            <span class="rounded-full bg-soft px-1.5 py-0.5 text-[10px] font-bold text-brand-500 tabular-nums"
                                  x-text="activeColumnsCount() + '/' + columnList.length"></span>
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

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="suppliers-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="w-11 py-3.5 px-4 text-center">
                            <input type="checkbox" @click="toggleSelectAll()" :checked="allSelected"
                                   aria-label="Select all suppliers on page"
                                   class="rounded border-line bg-surface text-brand-500 focus:ring-2 focus:ring-brand-500/20 size-4 cursor-pointer">
                        </th>
                        <th x-show="columns.supplier_id" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Supplier ID</th>
                        <th x-show="columns.name" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Supplier Name</th>
                        <th x-show="columns.mobile" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Mobile No.</th>
                        <th x-show="columns.email" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Email</th>
                        <th x-show="columns.purchase_due" scope="col" class="py-3.5 px-4 text-end font-bold text-ink whitespace-nowrap">Purchase Due</th>
                        <th x-show="columns.purchase_return_due" scope="col" class="py-3.5 px-4 text-end font-bold text-ink whitespace-nowrap">Return Due</th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-4 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        @can('suppliers.manage')
                            <th x-show="columns.action" scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($suppliers as $supplier)
                        @php
                            $initials = \Illuminate\Support\Str::of($supplier->name)
                                ->upper()
                                ->trim()
                                ->explode(' ')
                                ->filter()
                                ->map(fn ($part) => mb_substr($part, 0, 1))
                                ->take(2)
                                ->implode('') ?: '?';

                            $purchaseDue = (float) $supplier->purchase_due;
                            $returnDue = (float) $supplier->purchase_return_due;

                            $supplierPayload = [
                                'id' => $supplier->id,
                                'name' => $supplier->name,
                                'supplier_id' => $supplier->supplier_id,
                                'mobile' => $supplier->mobile ?: '',
                                'tel_no' => $supplier->tel_no ?: '',
                                'email' => $supplier->email ?: '',
                                'tax_number' => $supplier->tax_number ?: '',
                                'opening_balance' => (float) $supplier->opening_balance,
                                'due' => $purchaseDue,
                                'purchase_due' => $purchaseDue,
                                'purchase_return_due' => $returnDue,
                                'return_due' => $returnDue,
                            ];
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-3.5 px-4 text-center">
                                <input type="checkbox" value="{{ $supplier->id }}" x-model="selectedSuppliers"
                                       aria-label="Select {{ $supplier->name }}"
                                       class="rounded border-line bg-surface text-brand-500 focus:ring-2 focus:ring-brand-500/20 size-4 cursor-pointer">
                            </td>
                            <td x-show="columns.supplier_id" data-col="supplier_id" class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line/80 font-mono tracking-tight">
                                    {{ $supplier->supplier_id }}
                                </span>
                            </td>
                            <td x-show="columns.name" data-col="name" class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 text-xs font-bold tracking-wide" aria-hidden="true">
                                        {{ $initials }}
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors truncate max-w-56" title="{{ $supplier->name }}">
                                            {{ $supplier->name }}
                                        </span>
                                        <span class="block text-[11px] text-muted truncate max-w-56">
                                            @if($supplier->tax_number)
                                                TAX: {{ $supplier->tax_number }}
                                            @elseif($supplier->city)
                                                {{ $supplier->city }}
                                            @else
                                                Supplier Account
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td x-show="columns.mobile" data-col="mobile" class="py-3.5 px-4 text-sm text-ink whitespace-nowrap tabular-nums">
                                @if($supplier->mobile)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="phone" class="size-3.5 text-muted shrink-0" />
                                        <span>{{ $supplier->mobile }}</span>
                                    </span>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>
                            <td x-show="columns.email" data-col="email" class="py-3.5 px-4 text-xs text-muted whitespace-nowrap">
                                @if($supplier->email)
                                    <span class="inline-flex items-center gap-1.5 text-ink/90">
                                        <x-svg.envelope class="size-3.5 text-muted shrink-0" />
                                        <span class="truncate max-w-44" title="{{ $supplier->email }}">{{ $supplier->email }}</span>
                                    </span>
                                @else
                                    <span class="text-muted/60 italic">—</span>
                                @endif
                            </td>
                            <td x-show="columns.purchase_due" data-col="purchase_due" class="py-3.5 px-4 text-end whitespace-nowrap tabular-nums">
                                @if($purchaseDue > 0)
                                    <span class="inline-flex items-center rounded-lg bg-danger/10 px-2.5 py-1 text-xs font-bold text-danger ring-1 ring-danger/20">
                                        {{ $currencySymbol }}{{ number_format($purchaseDue, 2) }}
                                    </span>
                                @else
                                    <span class="text-xs font-medium text-muted">{{ $currencySymbol }}0.00</span>
                                @endif
                            </td>
                            <td x-show="columns.purchase_return_due" data-col="purchase_return_due" class="py-3.5 px-4 text-end whitespace-nowrap tabular-nums">
                                @if($returnDue > 0)
                                    <span class="inline-flex items-center rounded-lg bg-warning/10 px-2.5 py-1 text-xs font-bold text-warning ring-1 ring-warning/20">
                                        {{ $currencySymbol }}{{ number_format($returnDue, 2) }}
                                    </span>
                                @else
                                    <span class="text-xs font-medium text-muted">{{ $currencySymbol }}0.00</span>
                                @endif
                            </td>
                            <td x-show="columns.status" data-col="status" class="py-3.5 px-4 text-center whitespace-nowrap">
                                @can('update', $supplier)
                                    <form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                title="Click to switch status to {{ $supplier->status === 'active' ? 'Inactive' : 'Active' }}"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold transition-all cursor-pointer {{ $supplier->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $supplier->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($supplier->status) }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $supplier->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                        <span class="size-1.5 rounded-full {{ $supplier->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                        <span>{{ ucfirst($supplier->status) }}</span>
                                    </span>
                                @endcan
                            </td>
                            @can('suppliers.manage')
                                <td x-show="columns.action" data-col="action" class="py-3.5 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        @if($purchaseDue > 0)
                                            <button type="button"
                                                    @click="openPayModal('due', @js($supplierPayload))"
                                                    class="btn-scale inline-flex items-center gap-1 rounded-xl bg-brand-500/10 px-2.5 py-1.5 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 hover:bg-brand-500 hover:text-on-brand transition-colors cursor-pointer"
                                                    title="Record due payment for {{ $supplier->name }}">
                                                <x-icon name="wallet" class="size-3.5" />
                                                <span>Pay</span>
                                            </button>
                                        @endif
                                        <button type="button"
                                                @click.stop="toggleActionMenu($event, @js($supplierPayload))"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors cursor-pointer"
                                                title="Actions for {{ $supplier->name }}">
                                            <span>Actions</span>
                                            <svg class="size-3.5 text-muted transition-transform"
                                                 :class="{ 'rotate-180': actionMenuOpen && openActionSupplier && openActionSupplier.id === {{ $supplier->id }} }"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="1 + activeColumnsCount()" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="truck" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No suppliers found</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if($currentSearch !== '' || $currentStatus !== '')
                                            No supplier matches your active filters. Try clearing your search or status filter.
                                        @else
                                            Get started by registering your first distributor or wholesale supplier.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex items-center gap-2.5 flex-wrap justify-center">
                                        @if($currentSearch !== '' || $currentStatus !== '')
                                            <a href="{{ route('suppliers.index') }}"
                                               class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Filters
                                            </a>
                                        @endif
                                        @can('create', App\Models\Supplier::class)
                                            <a href="{{ route('suppliers.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + New Supplier
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- Table Total Footer --}}
                @if($suppliers->isNotEmpty())
                    <tfoot>
                        <tr class="bg-canvas/60 text-ink font-bold text-xs border-t-2 border-line">
                            <td :colspan="totalColspan()" class="py-3.5 px-4 text-end uppercase tracking-wider text-muted">
                                Filtered Totals
                            </td>
                            <td x-show="columns.purchase_due" class="py-3.5 px-4 text-end font-extrabold tabular-nums {{ (float) $totalPurchaseDue > 0 ? 'text-danger' : 'text-ink' }}">
                                {{ $currencySymbol }}{{ number_format((float) $totalPurchaseDue, 2) }}
                            </td>
                            <td x-show="columns.purchase_return_due" class="py-3.5 px-4 text-end font-extrabold tabular-nums {{ (float) $totalPurchaseReturnDue > 0 ? 'text-warning' : 'text-ink' }}">
                                {{ $currencySymbol }}{{ number_format((float) $totalPurchaseReturnDue, 2) }}
                            </td>
                            <td :colspan="(columns.status ? 1 : 0) + (hasActionCol && columns.action ? 1 : 0)" class="py-3.5 px-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- Pagination & Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $suppliers->links() }}
        </div>
    </div>

    {{-- Floating Teleport Action Dropdown Menu --}}
    @can('suppliers.manage')
        <template x-teleport="body">
            <div x-show="actionMenuOpen"
                 x-cloak
                 @click.outside="actionMenuOpen = false"
                 @scroll.window="actionMenuOpen = false"
                 @resize.window="actionMenuOpen = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="fixed z-9999 w-52 rounded-2xl bg-surface shadow-card border border-line p-1.5 text-xs text-ink text-left"
                 :style="{ top: menuTop, left: menuLeft }">
                <template x-if="openActionSupplier">
                    <div>
                        <div class="px-2.5 py-1.5 text-[11px] font-semibold text-muted truncate border-b border-line mb-1"
                             x-text="openActionSupplier.name"></div>

                        {{-- Option 1: Edit --}}
                        <a :href="'/suppliers/' + openActionSupplier.id + '/edit'"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-ink hover:bg-soft font-medium transition-colors">
                            <x-svg.pencil class="size-3.5 text-brand-500" />
                            <span>Edit &amp; Payment Ledger</span>
                        </a>

                        {{-- Option 2: Pay Due Payments --}}
                        <button type="button"
                                @click="openPayModal('due', openActionSupplier)"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-ink hover:bg-soft font-medium transition-colors text-left cursor-pointer">
                            <x-icon name="wallet" class="size-3.5 text-success" />
                            <span>Pay Due Payments</span>
                        </button>

                        {{-- Option 3: Pay Return Due --}}
                        <button type="button"
                                @click="openPayModal('return', openActionSupplier)"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-ink hover:bg-soft font-medium transition-colors text-left cursor-pointer">
                            <x-icon name="payments" class="size-3.5 text-warning" />
                            <span>Pay Return Due</span>
                        </button>

                        <div class="my-1 border-t border-line"></div>

                        {{-- Option 4: Delete --}}
                        <form :action="'/suppliers/' + openActionSupplier.id" method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-danger hover:bg-danger/10 font-semibold text-left transition-colors cursor-pointer">
                                <x-svg.trash-bin class="size-3.5" />
                                <span>Delete Supplier</span>
                            </button>
                        </form>
                    </div>
                </template>
            </div>
        </template>
    @endcan

    {{-- Payment Modal (Pay Due Payments & Pay Return Due) --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeModal()">
        <div class="w-full max-w-xl rounded-3xl border border-line bg-surface shadow-2xl overflow-hidden my-8"
             @click.stop
             x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 -translate-y-2">
            <form :action="modalActionUrl" method="POST">
                @csrf

                {{-- Modal Header --}}
                <div class="px-6 py-5 border-b border-line flex items-start justify-between gap-4 bg-surface">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl ring-1"
                             :class="modalType === 'due' ? 'bg-brand-500/10 text-brand-500 ring-brand-500/20' : 'bg-warning/10 text-warning ring-warning/20'">
                            <x-icon name="wallet" class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-ink"
                                x-text="modalType === 'due' ? 'Pay Due Payments' : 'Pay Purchase Return Due'">
                            </h3>
                            <p class="text-xs text-muted mt-0.5"
                               x-text="modalType === 'due' ? 'Record a settlement payment towards this supplier\'s payable balance.' : 'Record a return adjustment payment for this supplier.'"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeModal()"
                            class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                            aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-5">
                    {{-- Supplier Details & Financial Summary --}}
                    <div class="rounded-2xl border border-line bg-soft/50 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Supplier Details</span>
                                <h4 class="text-sm sm:text-base font-bold text-ink mt-0.5" x-text="activeSupplier ? activeSupplier.name : ''"></h4>
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                    <span x-show="activeSupplier?.mobile">Mobile: <strong class="text-ink font-medium" x-text="activeSupplier?.mobile"></strong></span>
                                    <span x-show="activeSupplier?.tel_no">Tel: <strong class="text-ink font-medium" x-text="activeSupplier?.tel_no"></strong></span>
                                    <span x-show="activeSupplier?.email">Email: <strong class="text-ink font-medium" x-text="activeSupplier?.email"></strong></span>
                                    <span x-show="activeSupplier?.tax_number">TAX: <strong class="text-ink font-medium" x-text="activeSupplier?.tax_number"></strong></span>
                                </div>
                            </div>
                            <span x-show="activeSupplier?.supplier_id"
                                  class="inline-flex items-center rounded-lg bg-surface px-2.5 py-1 text-xs font-bold text-ink border border-line font-mono shrink-0"
                                  x-text="activeSupplier?.supplier_id"></span>
                        </div>

                        {{-- Due Breakdown Grid --}}
                        <template x-if="modalType === 'due'">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-3 border-t border-line">
                                <div class="rounded-xl bg-surface border border-line px-3 py-2.5">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Opening Balance</span>
                                    <span class="block text-sm font-bold text-ink mt-0.5 tabular-nums"
                                          x-text="'{{ $currencySymbol }}' + Number(activeSupplier?.opening_balance || 0).toFixed(2)"></span>
                                </div>
                                <div class="rounded-xl bg-surface border border-line px-3 py-2.5">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Return Due</span>
                                    <span class="block text-sm font-bold text-warning mt-0.5 tabular-nums"
                                          x-text="'{{ $currencySymbol }}' + Number(activeSupplier?.purchase_return_due || 0).toFixed(2)"></span>
                                </div>
                                <div class="rounded-xl bg-danger/10 border border-danger/20 px-3 py-2.5">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-danger">Purchase Due</span>
                                    <span class="block text-sm font-extrabold text-danger mt-0.5 tabular-nums"
                                          x-text="'{{ $currencySymbol }}' + Number(activeSupplier?.due || 0).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Return Due Breakdown Grid --}}
                        <template x-if="modalType === 'return'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-3 border-t border-line">
                                <div class="rounded-xl bg-surface border border-line px-3 py-2.5">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Purchase Due</span>
                                    <span class="block text-sm font-bold text-ink mt-0.5 tabular-nums"
                                          x-text="'{{ $currencySymbol }}' + Number(activeSupplier?.purchase_due || 0).toFixed(2)"></span>
                                </div>
                                <div class="rounded-xl bg-warning/10 border border-warning/20 px-3 py-2.5">
                                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-warning">Return Due Balance</span>
                                    <span class="block text-sm font-extrabold text-warning mt-0.5 tabular-nums"
                                          x-text="'{{ $currencySymbol }}' + Number(activeSupplier?.due || 0).toFixed(2)"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Payment Form Fields --}}
                    <div class="rounded-2xl border border-line bg-canvas/40 p-4 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {{-- Date --}}
                            <div>
                                <label for="modal-payment-date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Date<span class="text-danger">*</span>
                                </label>
                                <input type="date" id="modal-payment-date" name="payment_date" required value="{{ date('Y-m-d') }}"
                                       class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20">
                            </div>

                            {{-- Amount --}}
                            <div>
                                <label for="modal-payment-amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Amount ({{ $currencySymbol }})<span class="text-danger">*</span>
                                </label>
                                <input type="number" id="modal-payment-amount" step="0.01" min="0.01" name="amount" required
                                       :max="activeSupplier && activeSupplier.due > 0 ? activeSupplier.due : undefined"
                                       :value="activeSupplier && activeSupplier.due > 0 ? activeSupplier.due.toFixed(2) : ''"
                                       placeholder="0.00"
                                       class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm font-bold text-ink text-right tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20">
                            </div>

                            {{-- Payment Type --}}
                            <div>
                                <label for="modal-payment-type" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Payment Type<span class="text-danger">*</span>
                                </label>
                                <select id="modal-payment-type" name="payment_type" required
                                        class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 cursor-pointer">
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Online">Online</option>
                                </select>
                            </div>
                        </div>

                        {{-- Payment Note --}}
                        <div>
                            <label for="modal-payment-note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Payment Note
                            </label>
                            <textarea id="modal-payment-note" name="payment_note" rows="3"
                                      placeholder="Optional transaction reference, cheque no., or remark..."
                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 bg-surface border-t border-line flex items-center justify-end gap-3">
                    <button type="button" @click="closeModal()"
                            class="btn-scale rounded-xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Close
                    </button>
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Save Payment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function suppliersPage() {
    return {
        allSelected: false,
        selectedSuppliers: [],
        modalOpen: false,
        modalType: 'due',
        activeSupplier: null,
        modalActionUrl: '',
        copied: false,

        actionMenuOpen: false,
        openActionSupplier: null,
        menuTop: '0px',
        menuLeft: '0px',

        hasActionCol: @json(auth()->user()?->can('suppliers.manage') ?? false),
        columnsDropdownOpen: false,
        columns: {
            supplier_id: true,
            name: true,
            mobile: true,
            email: true,
            purchase_due: true,
            purchase_return_due: true,
            status: true,
            action: true
        },
        columnList: [
            { key: 'supplier_id', label: 'Supplier ID' },
            { key: 'name', label: 'Supplier Name' },
            { key: 'mobile', label: 'Mobile No.' },
            { key: 'email', label: 'Email' },
            { key: 'purchase_due', label: 'Purchase Due' },
            { key: 'purchase_return_due', label: 'Return Due' },
            { key: 'status', label: 'Status' },
            @can('suppliers.manage') { key: 'action', label: 'Action' } @endcan
        ],

        searchQuery: @js($currentSearch),
        statusFilter: @js($currentStatus),

        toggleColumn(key) {
            if (this.columns[key] && this.activeColumnsCount() <= 1) {
                return;
            }
            this.columns[key] = !this.columns[key];
        },

        activeColumnsCount() {
            return this.columnList.filter(c => this.columns[c.key]).length;
        },

        activeColumnsQuery() {
            return Object.keys(this.columns)
                .filter(k => this.columns[k] && k !== 'action')
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusFilter
            });
            return `/suppliers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusFilter
            });
            window.open(`/suppliers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusFilter
            });
            window.open(`/suppliers/print-view?${params.toString()}`, '_blank');
        },

        copyTable() {
            const table = document.getElementById('suppliers-table');
            const printCols = this.columnList.filter(c => this.columns[c.key] && c.key !== 'action');
            let text = printCols.map(c => c.label).join('\t') + '\n';

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(tr => {
                if (tr.querySelector('td[colspan]')) return;
                const rowVals = printCols.map(c => {
                    const td = tr.querySelector(`td[data-col="${c.key}"]`);
                    return td ? td.innerText.replace(/\s+/g, ' ').trim() : '';
                });
                text += rowVals.join('\t') + '\n';
            });

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                });
            }
        },

        totalColspan() {
            let count = 1; // checkbox
            if (this.columns.supplier_id) count++;
            if (this.columns.name) count++;
            if (this.columns.mobile) count++;
            if (this.columns.email) count++;
            return count;
        },

        toggleActionMenu(event, supplier) {
            if (this.actionMenuOpen && this.openActionSupplier && this.openActionSupplier.id === supplier.id) {
                this.actionMenuOpen = false;
                this.openActionSupplier = null;
                return;
            }
            this.openActionSupplier = supplier;
            const rect = event.currentTarget.getBoundingClientRect();
            const menuHeight = 205;
            const spaceBelow = window.innerHeight - rect.bottom;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                this.menuTop = (rect.top - menuHeight) + 'px';
            } else {
                this.menuTop = (rect.bottom + 6) + 'px';
            }
            this.menuLeft = Math.max(12, rect.right - 208) + 'px';
            this.actionMenuOpen = true;
        },

        openPayModal(type, supplier) {
            this.actionMenuOpen = false;
            this.modalType = type;
            this.activeSupplier = {
                ...supplier,
                due: type === 'due' ? Number(supplier.purchase_due || 0) : Number(supplier.purchase_return_due || 0)
            };
            this.modalActionUrl = type === 'due'
                ? `/suppliers/${supplier.id}/pay-due`
                : `/suppliers/${supplier.id}/pay-return-due`;
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
            this.activeSupplier = null;
        },

        toggleSelectAll() {
            this.allSelected = !this.allSelected;
            const checkboxes = document.querySelectorAll('#suppliers-table tbody input[type="checkbox"]');
            this.selectedSuppliers = [];
            if (this.allSelected) {
                checkboxes.forEach(cb => {
                    this.selectedSuppliers.push(cb.value);
                });
            }
        }
    };
}
</script>
@endsection
