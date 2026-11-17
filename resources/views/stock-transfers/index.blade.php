@extends('layouts.app')

@section('title', 'Stock Transfers')
@section('page_title', 'Stock Transfers')

@section('content')
@php
    $sortLink = fn (string $col) => route('stock-transfers.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'desc' ? 'asc' : 'desc',
    ]));
@endphp

<div x-data="transfersPage()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Stock Transfers</h1>
        <span class="text-sm text-gray-500 dark:text-gray-400">Move stock between your locations</span>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">All Stock Transfers</h2>
            @can('create', App\Models\StockTransfer::class)
                <a href="{{ route('stock-transfers.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95 cursor-pointer"
                   style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </a>
            @endcan
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('stock-transfers.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
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
                <form method="GET" action="{{ route('stock-transfers.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search ..."
                           class="py-1.5 px-3 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-indigo-500">
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="stock-transfers-table">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th x-show="columns.transfer_date" class="p-3">
                                <a href="{{ $sortLink('transfer_date') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Date
                                    <span class="text-xs opacity-70">{{ $sort === 'transfer_date' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.reference_no" class="p-3">
                                <a href="{{ $sortLink('reference_no') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Reference No
                                    <span class="text-xs opacity-70">{{ $sort === 'reference_no' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.from_location" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Location (From)</th>
                            <th x-show="columns.to_location" class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Location (To)</th>
                            <th x-show="columns.status" class="p-3">
                                <a href="{{ $sortLink('status') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Status
                                    <span class="text-xs opacity-70">{{ $sort === 'status' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.shipping_charges" class="p-3">
                                <a href="{{ $sortLink('shipping_charges') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Shipping Charges
                                    <span class="text-xs opacity-70">{{ $sort === 'shipping_charges' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.total_amount" class="p-3">
                                <a href="{{ $sortLink('total_amount') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Total Amount
                                    <span class="text-xs opacity-70">{{ $sort === 'total_amount' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.additional_notes" class="p-3">
                                <a href="{{ $sortLink('additional_notes') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Additional Notes
                                    <span class="text-xs opacity-70">{{ $sort === 'additional_notes' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            @can('stock-transfers.manage')
                                <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($transfers as $transfer)
                            <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40 hover:bg-gray-100 dark:hover:bg-gray-800/70 transition">
                                <td x-show="columns.transfer_date" data-col="transfer_date" class="p-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    {{ $transfer->transfer_date->format('d/m/Y H:i') }}
                                </td>
                                <td x-show="columns.reference_no" data-col="reference_no" class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $transfer->reference_no }}
                                </td>
                                <td x-show="columns.from_location" data-col="from_location" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $transfer->fromLocation?->name ?? '—' }}
                                </td>
                                <td x-show="columns.to_location" data-col="to_location" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $transfer->toLocation?->name ?? '—' }}
                                </td>
                                <td x-show="columns.status" data-col="status" class="p-3">
                                    @php($transferStatus = $transfer->displayStatusFor(auth()->user()))
                                    <x-status-badge :status="$transferStatus['value']" :label="$transferStatus['label']" />
                                </td>
                                <td x-show="columns.shipping_charges" data-col="shipping_charges" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ \App\Helpers\Money::format($transfer->shipping_charges) }}
                                </td>
                                <td x-show="columns.total_amount" data-col="total_amount" class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ \App\Helpers\Money::format($transfer->total_amount) }}
                                </td>
                                <td x-show="columns.additional_notes" data-col="additional_notes" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $transfer->additional_notes ?: '—' }}
                                </td>
                                @can('stock-transfers.manage')
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
                                                 class="absolute left-0 mt-1 w-48 rounded-lg shadow-2xl py-1 z-40 text-left border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <a href="{{ route('stock-transfers.show', $transfer) }}"
                                                   class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                    View Invoice
                                                </a>
                                                <a href="{{ route('stock-transfers.show', $transfer) }}?print=1" target="_blank"
                                                   class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                    Print Invoice
                                                </a>
                                                @can('approve', $transfer)
                                                    @if($transfer->status === \App\Enums\StockTransferStatus::InTransit)
                                                        <form method="POST" action="{{ route('stock-transfers.approve', $transfer) }}"
                                                              onsubmit="return confirm('Approve and receive this transfer?');">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit"
                                                                    class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-green-600 dark:text-green-400 transition hover:bg-green-50 dark:hover:bg-green-500/10 cursor-pointer">
                                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                Approve / Receive
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endcan
                                                @can('reject', $transfer)
                                                    @if(in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit], true))
                                                        <form method="POST" action="{{ route('stock-transfers.reject', $transfer) }}"
                                                              onsubmit="return confirm('Reject this transfer? Dispatched stock returns to the sender.');">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit"
                                                                    class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-red-500 dark:text-red-400 transition hover:bg-red-50 dark:hover:bg-red-500/10 cursor-pointer">
                                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                Reject
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endcan
                                                @can('update', $transfer)
                                                    <a href="{{ route('stock-transfers.edit', $transfer) }}"
                                                       class="w-full flex items-center gap-2 px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        Edit
                                                    </a>
                                                @endcan
                                                @can('delete', $transfer)
                                                    <form method="POST" action="{{ route('stock-transfers.destroy', $transfer) }}"
                                                          onsubmit="return confirm('Are you sure you want to delete this stock transfer?');">
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
                                <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm font-medium">
                                    No stock transfers found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $transfers->links() }}
            </div>
        </div>
    </div>
</div>

<script>
function transfersPage() {
    return {
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
            return `/stock-transfers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/stock-transfers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/stock-transfers/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
