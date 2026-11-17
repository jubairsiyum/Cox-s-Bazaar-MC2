@extends('layouts.app')

@section('title', 'Purchase Returns')
@section('page_title', 'Purchase Returns')

@section('content')
@php
    $sortLink = fn (string $col) => route('purchase-returns.index', array_filter([
        'search' => request('search'),
        'supplier_id' => request('supplier_id'),
        'location_id' => request('location_id'),
        'payment_status' => request('payment_status'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc',
    ]));
@endphp

<div x-data="purchaseReturnsPage()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Purchase Returns</h1>
        <span class="text-sm text-gray-500 dark:text-gray-400">Goods returned to your suppliers</span>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-4" x-data="{ filtersOpen: false }">
        <button type="button" @click="filtersOpen = !filtersOpen"
                class="w-full flex items-center justify-between px-4 py-3 cursor-pointer">
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                <span class="inline-flex items-center justify-center size-7 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </span>
                Filters
            </span>
            <svg class="size-4 text-indigo-500 transition-transform" :class="{ 'rotate-180': filtersOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="filtersOpen" x-cloak class="px-4 pb-4 border-t border-gray-100 dark:border-gray-800">
            <form method="GET" action="{{ route('purchase-returns.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">Supplier</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">Business Location</label>
                    <select name="location_id" class="form-control">
                        <option value="">All Locations</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="">All Statuses</option>
                        @foreach(\App\Enums\PaymentStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-5 py-2 rounded-md text-white text-sm font-medium shadow-sm cursor-pointer" style="background-color: #00a65a;">Apply</button>
                    <a href="{{ route('purchase-returns.index') }}" class="px-5 py-2 rounded-md text-white text-sm font-medium shadow-sm cursor-pointer" style="background-color: #f39c12;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">All Purchase Returns</h2>
            @can('create', App\Models\PurchaseReturn::class)
                <a href="{{ route('purchase-returns.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95 cursor-pointer"
                   style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Apply Return
                </a>
            @endcan
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('purchase-returns.index') }}" class="flex items-center gap-2">
                    @foreach(array_filter(['search' => request('search'), 'supplier_id' => request('supplier_id'), 'location_id' => request('location_id'), 'payment_status' => request('payment_status'), 'date_from' => request('date_from'), 'date_to' => request('date_to'), 'sort' => request('sort'), 'direction' => request('direction')]) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5 text-xs sm:text-sm">
                        Show
                        <select name="per_page" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                            <option value="100" @selected($perPage == 100)>100</option>
                        </select>
                        entries
                    </label>
                </form>

                {{-- Middle: Export Buttons --}}
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a :href="exportUrl('csv')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </a>
                    <a :href="exportUrl('xlsx')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Excel
                    </a>
                    <button type="button" @click="printTable()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                            <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            Column visibility
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-48 rounded-lg shadow-2xl py-1 z-50 text-left border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <button type="button" @click="openPdf()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export PDF
                        <svg class="size-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('purchase-returns.index') }}" class="flex items-center gap-2">
                    @foreach(array_filter(['supplier_id' => request('supplier_id'), 'location_id' => request('location_id'), 'payment_status' => request('payment_status'), 'date_from' => request('date_from'), 'date_to' => request('date_to'), 'per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')]) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search ..."
                           class="py-1.5 px-3 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-indigo-500">
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="purchase-returns-table">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th x-show="columns.return_date" class="p-3">
                                <a href="{{ $sortLink('return_date') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Date
                                    <span class="text-xs opacity-70">{{ $sort === 'return_date' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.reference_no" class="p-3">
                                <a href="{{ $sortLink('reference_no') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Reference No
                                    <span class="text-xs opacity-70">{{ $sort === 'reference_no' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.parent_purchase" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Parent Purchase</th>
                            <th x-show="columns.location" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Location</th>
                            <th x-show="columns.supplier" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Supplier</th>
                            <th x-show="columns.payment_status" class="p-3">
                                <a href="{{ $sortLink('payment_status') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Payment Status
                                    <span class="text-xs opacity-70">{{ $sort === 'payment_status' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.grand_total" class="p-3">
                                <a href="{{ $sortLink('grand_total') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Grand Total
                                    <span class="text-xs opacity-70">{{ $sort === 'grand_total' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.payment_due" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">
                                Payment due
                                <span title="Outstanding amount the supplier still owes for this return"
                                      class="inline-flex items-center justify-center size-4 rounded-full bg-blue-500 text-white text-[10px] font-bold cursor-help align-middle">i</span>
                            </th>
                            @can('purchase-returns.manage')
                                <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($returns as $return)
                            <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40 hover:bg-gray-100 dark:hover:bg-gray-800/70 transition">
                                <td x-show="columns.return_date" data-col="return_date" class="p-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ $return->return_date->format('d/m/Y H:i') }}
                                </td>
                                <td x-show="columns.reference_no" data-col="reference_no" class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $return->reference_no }}
                                </td>
                                <td x-show="columns.parent_purchase" data-col="parent_purchase" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $return->purchaseOrder?->reference_no ?? '—' }}
                                </td>
                                <td x-show="columns.location" data-col="location" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $return->location?->name ?? '—' }}
                                </td>
                                <td x-show="columns.supplier" data-col="supplier" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $return->supplier?->name ?? '—' }}
                                </td>
                                <td x-show="columns.payment_status" data-col="payment_status" class="p-3">
                                    <x-status-badge :status="$return->payment_status->value" :label="$return->payment_status->label()" />
                                </td>
                                <td x-show="columns.grand_total" data-col="grand_total" class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ \App\Helpers\Money::format($return->grand_total) }}
                                </td>
                                <td x-show="columns.payment_due" data-col="payment_due" class="p-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    Purchase Return: {{ \App\Helpers\Money::format($return->payment_due) }}
                                </td>
                                @can('purchase-returns.manage')
                                    <td class="p-3 text-xs whitespace-nowrap" x-data="{ open: false }">
                                        <div class="relative inline-block text-left">
                                            <button type="button" @click.stop="open = !open"
                                                    class="inline-flex items-center gap-1 rounded-full border border-indigo-500/60 px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer">
                                                Actions
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            <div x-show="open" x-cloak @click.outside="open = false"
                                                 class="absolute left-0 mt-1 w-44 rounded-lg shadow-2xl py-1 z-40 text-left border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <a href="{{ route('purchase-returns.show', $return) }}"
                                                   class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    View
                                                </a>
                                                <a href="{{ route('purchase-returns.show', $return) }}?print=1" target="_blank"
                                                   class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                    Print
                                                </a>
                                                @can('update', $return)
                                                    <a href="{{ route('purchase-returns.edit', $return) }}"
                                                       class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        Edit
                                                    </a>
                                                @endcan
                                                @can('delete', $return)
                                                    <form method="POST" action="{{ route('purchase-returns.destroy', $return) }}"
                                                          onsubmit="return confirm('Are you sure you want to delete this purchase return?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-red-500 dark:text-red-400 transition hover:bg-red-50 dark:hover:bg-red-500/10 cursor-pointer">
                                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="(document.querySelector('#purchase-returns-table thead tr').children.length)" class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm font-medium">
                                    No purchase returns found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <td class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100" :colspan="5">Total:</td>
                            <td class="p-3 text-xs text-gray-600 dark:text-gray-300">
                                <div>Due - {{ $totals['due'] }}</div>
                                <div>Paid - {{ $totals['paid'] }}</div>
                                <div>Partial - {{ $totals['partial'] }}</div>
                            </td>
                            <td class="p-3 text-sm font-bold text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($totals['grand_total']) }}</td>
                            <td class="p-3 text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                Purchase Return Due - {{ \App\Helpers\Money::format($totals['payment_due']) }}
                            </td>
                            <td class="p-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $returns->links() }}
            </div>
        </div>
    </div>
</div>

<script>
function purchaseReturnsPage() {
    return {
        columnsDropdownOpen: false,
        columns: {
            return_date: true,
            reference_no: true,
            parent_purchase: true,
            location: true,
            supplier: true,
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
            { key: 'payment_status', label: 'Payment Status' },
            { key: 'grand_total', label: 'Grand Total' },
            { key: 'payment_due', label: 'Payment Due' }
        ],

        toggleColumn(key) {
            this.columns[key] = !this.columns[key];
        },

        activeColumnsQuery() {
            return Object.keys(this.columns)
                .filter(k => this.columns[k])
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            return `/purchase-returns/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/purchase-returns/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/purchase-returns/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
