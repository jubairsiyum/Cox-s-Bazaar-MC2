@extends('layouts.app')

@section('title', 'Customers List — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Customers List')

@section('content')
@php
    $currentSearch = (string) request('search', '');
    $currentStatus = (string) request('status', '');

    // Single lightweight aggregate query for KPI intelligence strip (zero N+1)
    $customerStats = \App\Models\Customer::query()
        ->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
            SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as with_email_count,
            COUNT(DISTINCT CASE WHEN city IS NOT NULL AND city != '' THEN city END) as cities_count,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent_count
        ", [now()->subDays(30)])
        ->first();

    $totalCustomers = (int) ($customerStats->total_count ?? 0);
    $activeCustomers = (int) ($customerStats->active_count ?? 0);
    $inactiveCustomers = (int) ($customerStats->inactive_count ?? 0);
    $withEmailCount = (int) ($customerStats->with_email_count ?? 0);
    $citiesCount = (int) ($customerStats->cities_count ?? 0);
    $recentCustomers = (int) ($customerStats->recent_count ?? 0);
    $activeRatePct = $totalCustomers > 0 ? (int) round(($activeCustomers / $totalCustomers) * 100) : 0;

    $statusFilterUrl = fn (?string $statusValue) => route('customers.index', array_filter([
        'search' => $currentSearch !== '' ? $currentSearch : null,
        'per_page' => request('per_page'),
        'status' => $statusValue,
    ]));
@endphp

<div x-data="customersPage()" class="space-y-6">

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
                <span class="font-medium text-ink">Customers List</span>
            </nav>

            {{-- Title & Context Pills --}}
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Customers List</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($customers->total()) }} {{ Str::plural('customer', $customers->total()) }}
                </span>
                @if($currentStatus !== '')
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $currentStatus === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                        <span>Filtered: {{ ucfirst($currentStatus) }}</span>
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage customer profiles, contact directories, regional reach, and account statuses.</p>
        </div>

        {{-- Right Actions --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('customers.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            @can('import', App\Models\Customer::class)
                <a href="{{ route('customers.import') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors shadow-2xs">
                    <x-svg.upload class="size-4 text-muted" />
                    <span>Import</span>
                </a>
            @endcan

            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Customer</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Customer Intelligence KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- KPI 1: Total Customers --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Customers</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($totalCustomers) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="drivers" class="size-5" />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success tabular-nums">
                    {{ number_format($activeCustomers) }} active
                </span>
                <span>· {{ number_format($inactiveCustomers) }} inactive</span>
            </div>
        </div>

        {{-- KPI 2: Active Account Rate --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Rate</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ $activeRatePct }}<span class="text-base font-semibold text-muted">%</span>
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
                    <strong class="text-ink font-semibold tabular-nums">{{ number_format($activeCustomers) }}</strong> of {{ number_format($totalCustomers) }} accounts enabled
                </p>
            </div>
        </div>

        {{-- KPI 3: New This Month --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">New (Last 30 Days)</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($recentCustomers) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                <strong class="text-ink font-semibold tabular-nums">{{ number_format($withEmailCount) }}</strong> customers with verified email on file
            </div>
        </div>

        {{-- KPI 4: Regional Reach --}}
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Cities Served</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($citiesCount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="store" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Distinct cities across registered customer directory
            </div>
        </div>
    </div>

    {{-- Unified Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        {{-- Shell Header & Quick Status Filter Pills --}}
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Customer Directory</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($customers->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $customers->firstItem() }}–{{ $customers->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($customers->total()) }}</span> records
                    @else
                        No customer records match the current filters
                    @endif
                </p>
            </div>

            {{-- Quick Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <a href="{{ $statusFilterUrl(null) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === '' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>All</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === '' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-ink' }}">
                        {{ number_format($totalCustomers) }}
                    </span>
                </a>
                <a href="{{ $statusFilterUrl('active') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'active' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Active</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === 'active' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-success' }}">
                        {{ number_format($activeCustomers) }}
                    </span>
                </a>
                <a href="{{ $statusFilterUrl('inactive') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === 'inactive' ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                    <span>Inactive</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums {{ $currentStatus === 'inactive' ? 'bg-on-brand/20 text-on-brand' : 'bg-surface text-danger' }}">
                        {{ number_format($inactiveCustomers) }}
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
                    <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2 shrink-0">
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
                    <form method="GET" action="{{ route('customers.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="search" name="search" value="{{ $currentSearch }}"
                                   placeholder="Search ID, name, mobile, email, or city..."
                                   class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if($currentSearch !== '')
                                <a href="{{ route('customers.index', array_filter(['per_page' => request('per_page'), 'status' => $currentStatus !== '' ? $currentStatus : null])) }}"
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
                           title="Export filtered customers to CSV">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>CSV</span>
                        </a>
                        <span class="h-4 w-px bg-line"></span>
                        <a :href="exportUrl('xlsx')"
                           class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                           title="Export filtered customers to Excel">
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
                                title="Print customers list">
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

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table id="customers-table" class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="w-11 py-3.5 px-4 text-center">
                            <input type="checkbox" @change="toggleSelectAll()" :checked="allSelected"
                                   aria-label="Select all customers on page"
                                   class="rounded border-line bg-surface text-brand-500 focus:ring-2 focus:ring-brand-500/20 size-4 cursor-pointer">
                        </th>
                        <th x-show="columns.customer_id" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Customer ID</th>
                        <th x-show="columns.name" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Customer Name</th>
                        <th x-show="columns.mobile" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Mobile No.</th>
                        <th x-show="columns.tel_no" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Tel No.</th>
                        <th x-show="columns.email" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">Email</th>
                        <th x-show="columns.city" scope="col" class="py-3.5 px-4 text-start font-bold text-ink whitespace-nowrap">City</th>
                        <th x-show="columns.status" scope="col" class="py-3.5 px-4 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        @can('customers.manage')
                            <th x-show="columns.action" scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($customers as $customer)
                        @php
                            $initials = \Illuminate\Support\Str::of($customer->name)
                                ->upper()
                                ->trim()
                                ->explode(' ')
                                ->filter()
                                ->map(fn ($part) => mb_substr($part, 0, 1))
                                ->take(2)
                                ->implode('') ?: '?';
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-3.5 px-4 text-center">
                                <input type="checkbox" value="{{ $customer->id }}" x-model="selectedCustomers"
                                       aria-label="Select {{ $customer->name }}"
                                       class="rounded border-line bg-surface text-brand-500 focus:ring-2 focus:ring-brand-500/20 size-4 cursor-pointer">
                            </td>
                            <td x-show="columns.customer_id" data-col="customer_id" class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line/80 font-mono tracking-tight">
                                    {{ $customer->customer_id }}
                                </span>
                            </td>
                            <td x-show="columns.name" data-col="name" class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 text-xs font-bold tracking-wide" aria-hidden="true">
                                        {{ $initials }}
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors truncate max-w-56" title="{{ $customer->name }}">
                                            {{ $customer->name }}
                                        </span>
                                        @if($customer->address)
                                            <span class="block text-[11px] text-muted truncate max-w-56" title="{{ $customer->address }}">
                                                {{ $customer->address }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td x-show="columns.mobile" data-col="mobile" class="py-3.5 px-4 text-sm text-ink whitespace-nowrap tabular-nums">
                                @if($customer->mobile)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="phone" class="size-3.5 text-muted shrink-0" />
                                        <span>{{ $customer->mobile }}</span>
                                    </span>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>
                            <td x-show="columns.tel_no" data-col="tel_no" class="py-3.5 px-4 text-xs text-muted whitespace-nowrap tabular-nums">
                                {{ $customer->tel_no ?: '—' }}
                            </td>
                            <td x-show="columns.email" data-col="email" class="py-3.5 px-4 text-xs text-muted whitespace-nowrap">
                                @if($customer->email)
                                    <span class="inline-flex items-center gap-1.5 text-ink/90">
                                        <x-svg.envelope class="size-3.5 text-muted shrink-0" />
                                        <span class="truncate max-w-48" title="{{ $customer->email }}">{{ $customer->email }}</span>
                                    </span>
                                @else
                                    <span class="text-muted/60 italic">—</span>
                                @endif
                            </td>
                            <td x-show="columns.city" data-col="city" class="py-3.5 px-4 text-xs text-ink/90 whitespace-nowrap">
                                {{ $customer->city ?: '—' }}
                            </td>
                            <td x-show="columns.status" data-col="status" class="py-3.5 px-4 text-center whitespace-nowrap">
                                @can('update', $customer)
                                    <form method="POST" action="{{ route('customers.toggle-status', $customer) }}" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                title="Click to switch status to {{ $customer->status === 'active' ? 'Inactive' : 'Active' }}"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold transition-all cursor-pointer {{ $customer->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20 hover:bg-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20 hover:bg-danger/20' }}">
                                            <span class="size-1.5 rounded-full {{ $customer->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                            <span>{{ ucfirst($customer->status) }}</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $customer->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                        <span class="size-1.5 rounded-full {{ $customer->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                                        <span>{{ ucfirst($customer->status) }}</span>
                                    </span>
                                @endcan
                            </td>
                            @can('customers.manage')
                                <td x-show="columns.action" data-col="action" class="py-3.5 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <a href="{{ route('customers.edit', $customer) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="Edit {{ $customer->name }}">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                        <button type="button"
                                                @click.stop="toggleActionMenu($event, @js(['id' => $customer->id, 'name' => $customer->name]))"
                                                class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface p-1.5 text-muted shadow-2xs hover:border-brand-500/40 hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                                                title="More actions for {{ $customer->name }}"
                                                aria-label="More actions">
                                            <x-icon name="dots" class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td :colspan="totalColspan()" class="py-14 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4">
                                        <x-icon name="drivers" class="size-8" />
                                    </div>
                                    <h3 class="text-base font-bold text-ink">No customers found</h3>
                                    <p class="text-xs text-muted mt-1.5 leading-relaxed">
                                        @if($currentSearch !== '' || $currentStatus !== '')
                                            No customer matches your active filters. Try clearing your search or status filter.
                                        @else
                                            Get started by adding your first customer profile or importing a CSV list.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex items-center gap-2.5 flex-wrap justify-center">
                                        @if($currentSearch !== '' || $currentStatus !== '')
                                            <a href="{{ route('customers.index') }}"
                                               class="rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                                Clear Filters
                                            </a>
                                        @endif
                                        @can('create', App\Models\Customer::class)
                                            <a href="{{ route('customers.create') }}"
                                               class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                                + New Customer
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

        {{-- Pagination & Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $customers->links() }}
        </div>
    </div>

    {{-- Floating Teleport Action Dropdown Menu --}}
    @can('customers.manage')
        <template x-teleport="body">
            <div x-show="actionMenuOpen"
                 x-cloak
                 @click.outside="actionMenuOpen = false"
                 @scroll.window="actionMenuOpen = false"
                 @resize.window="actionMenuOpen = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="fixed z-9999 w-48 rounded-2xl bg-surface shadow-card border border-line p-1.5 text-xs text-ink"
                 :style="`top: ${menuTop}; left: ${menuLeft};`">
                <div class="px-2.5 py-1.5 text-[11px] font-semibold text-muted truncate border-b border-line mb-1"
                     x-text="openActionCustomer?.name || 'Customer Actions'"></div>

                <a :href="`/customers/${openActionCustomer?.id}/edit`"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-soft text-ink font-medium transition-colors">
                    <x-svg.pencil class="size-3.5 text-brand-500" />
                    <span>Edit Customer</span>
                </a>

                <div class="my-1 border-t border-line"></div>

                <form :action="`/customers/${openActionCustomer?.id}`" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this customer?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-danger/10 text-danger font-semibold text-left transition-colors cursor-pointer">
                        <x-svg.trash-bin class="size-3.5" />
                        <span>Delete Customer</span>
                    </button>
                </form>
            </div>
        </template>
    @endcan
</div>

<script>
function customersPage() {
    return {
        allSelected: false,
        selectedCustomers: [],
        actionMenuOpen: false,
        openActionCustomer: null,
        menuTop: '0px',
        menuLeft: '0px',
        copied: false,

        columnsDropdownOpen: false,
        columns: {
            customer_id: true,
            name: true,
            mobile: true,
            tel_no: true,
            email: true,
            city: true,
            status: true,
            action: true
        },
        columnList: [
            { key: 'customer_id', label: 'Customer ID' },
            { key: 'name', label: 'Customer Name' },
            { key: 'mobile', label: 'Mobile No.' },
            { key: 'tel_no', label: 'Tel No.' },
            { key: 'email', label: 'Email' },
            { key: 'city', label: 'City' },
            { key: 'status', label: 'Status' },
            @can('customers.manage') { key: 'action', label: 'Action' } @endcan
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
            return `/customers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusFilter
            });
            window.open(`/customers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: this.searchQuery,
                status: this.statusFilter
            });
            window.open(`/customers/print-view?${params.toString()}`, '_blank');
        },

        copyTable() {
            const table = document.getElementById('customers-table');
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
            return 1 + this.activeColumnsCount();
        },

        toggleActionMenu(event, customer) {
            if (this.actionMenuOpen && this.openActionCustomer && this.openActionCustomer.id === customer.id) {
                this.actionMenuOpen = false;
                this.openActionCustomer = null;
                return;
            }
            this.openActionCustomer = customer;
            const rect = event.currentTarget.getBoundingClientRect();
            const menuHeight = 135;
            const spaceBelow = window.innerHeight - rect.bottom;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                this.menuTop = (rect.top - menuHeight) + 'px';
            } else {
                this.menuTop = (rect.bottom + 6) + 'px';
            }
            this.menuLeft = Math.max(12, rect.right - 192) + 'px';
            this.actionMenuOpen = true;
        },

        toggleSelectAll() {
            this.allSelected = !this.allSelected;
            const checkboxes = document.querySelectorAll('#customers-table tbody input[type="checkbox"]');
            this.selectedCustomers = [];
            if (this.allSelected) {
                checkboxes.forEach(cb => {
                    this.selectedCustomers.push(cb.value);
                });
            }
        }
    };
}
</script>
@endsection
