@extends('layouts.app')

@section('title', 'Suppliers List')
@section('page_title', 'Suppliers List')

@section('content')
<div x-data="suppliersPage()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-ink">Suppliers List</h1>
                <span class="text-sm text-muted">View/Search Suppliers</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1.5 text-xs text-muted">
                <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Home
                </a>
                <span>&rsaquo;</span>
                <span class="text-ink font-medium">Suppliers List</span>
            </div>
            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}"
                   class="px-4 py-2 rounded-md text-on-brand font-medium text-sm transition-all shadow-sm flex items-center gap-1.5 hover:opacity-95"
                   style="background-color: var(--brand-500);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Supplier
                </a>
            @endcan
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-header border-b border-line">
            <h2 class="text-base font-semibold text-ink">Suppliers List</h2>
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('suppliers.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    <label class="text-muted flex items-center gap-1 text-xs sm:text-sm">
                        Show
                        <select name="per_page" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-line bg-surface">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                            <option value="100" @selected($perPage == 100)>100</option>
                        </select>
                        entries
                    </label>
                </form>

                {{-- Middle: Export Buttons --}}
                <div class="flex items-center gap-1 flex-wrap">
                    <button type="button" @click="copyTable()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: var(--brand-500);">
                        Copy
                    </button>
                    <a :href="exportUrl('xlsx')"
                       class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer"
                       style="background-color: var(--brand-500);">
                        Excel
                    </a>
                    <button type="button" @click="openPdf()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: var(--brand-500);">
                        PDF
                    </button>
                    <button type="button" @click="printTable()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: var(--brand-500);">
                        Print
                    </button>
                    <a :href="exportUrl('csv')"
                       class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer"
                       style="background-color: var(--brand-500);">
                        CSV
                    </a>

                    {{-- Columns Visibility Dropdown (matching Image 3) --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer flex items-center gap-1"
                                style="background-color: var(--brand-500);">
                            Columns
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-44 rounded shadow-2xl py-1 z-50 text-left border border-brand-500"
                             style="background-color: var(--brand-500);">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-on-brand transition hover:bg-brand-500 text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('suppliers.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    <label class="text-muted flex items-center gap-1.5 text-xs sm:text-sm">
                        Search:
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Type to search..."
                               class="py-1 px-2.5 text-xs rounded border border-line bg-surface text-ink focus:outline-hidden focus:border-brand-500">
                    </label>
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-line rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="suppliers-table">
                    <thead>
                        <tr style="background-color: var(--brand-500);">
                            <th class="w-10 text-center p-2.5" style="color: var(--on-brand) !important;">
                                <input type="checkbox" @click="toggleSelectAll()" :checked="allSelected"
                                       class="rounded border-line text-brand-500 focus:ring-0">
                            </th>
                            <th x-show="columns.supplier_id" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                SUPPLIER ID <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.name" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                SUPPLIER NAME <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.mobile" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                MOBILE NO. <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.email" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                EMAIL <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.purchase_due" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                PURCHASE DUE <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.purchase_return_due" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                PURCHASE RETURN DUE <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.status" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: var(--on-brand) !important;">
                                STATUS <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.action" class="p-2.5 font-bold text-xs tracking-wider text-right" style="color: var(--on-brand) !important;">
                                ACTION
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($suppliers as $supplier)
                            <tr class="hover:bg-soft/60 transition">
                                <td class="text-center p-2.5">
                                    <input type="checkbox" value="{{ $supplier->id }}" x-model="selectedSuppliers"
                                           class="rounded border-line text-brand-500 focus:ring-0">
                                </td>
                                <td x-show="columns.supplier_id" data-col="supplier_id" class="p-2.5 text-xs font-medium text-ink whitespace-nowrap">
                                    {{ $supplier->supplier_id }}
                                </td>
                                <td x-show="columns.name" data-col="name" class="p-2.5 text-xs text-ink font-semibold">
                                    {{ $supplier->name }}
                                </td>
                                <td x-show="columns.mobile" data-col="mobile" class="p-2.5 text-xs text-muted">
                                    {{ $supplier->mobile ?: '—' }}
                                </td>
                                <td x-show="columns.email" data-col="email" class="p-2.5 text-xs text-muted">
                                    {{ $supplier->email ?: '—' }}
                                </td>
                                <td x-show="columns.purchase_due" data-col="purchase_due" class="p-2.5 text-xs font-medium text-ink">
                                    {{ number_format((float) $supplier->purchase_due, 2) }}
                                </td>
                                <td x-show="columns.purchase_return_due" data-col="purchase_return_due" class="p-2.5 text-xs text-muted">
                                    {{ number_format((float) $supplier->purchase_return_due, 2) }}
                                </td>
                                <td x-show="columns.status" data-col="status" class="p-2.5 text-xs">
                                    @can('update', $supplier)
                                        <form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Click to change status to {{ $supplier->status === 'active' ? 'Inactive' : 'Active' }}"
                                                    class="cursor-pointer inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-on-brand transition hover:opacity-80 active:scale-95 shadow-xs"
                                                    style="background-color: {{ $supplier->status === 'active' ? 'var(--success)' : 'var(--danger)' }};">
                                                {{ ucfirst($supplier->status) }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-on-brand"
                                              style="background-color: {{ $supplier->status === 'active' ? 'var(--success)' : 'var(--danger)' }};">
                                            {{ ucfirst($supplier->status) }}
                                        </span>
                                    @endcan
                                </td>
                                <td x-show="columns.action" data-col="action" class="p-2.5 text-xs text-right whitespace-nowrap">
                                    <button @click.stop="toggleActionMenu($event, {{ json_encode([
                                                'id' => $supplier->id,
                                                'name' => $supplier->name,
                                                'supplier_id' => $supplier->supplier_id,
                                                'mobile' => $supplier->mobile ?: '',
                                                'tel_no' => $supplier->tel_no ?: '',
                                                'email' => $supplier->email ?: '',
                                                'tax_number' => $supplier->tax_number ?: '',
                                                'opening_balance' => (float) $supplier->opening_balance,
                                                'due' => (float) $supplier->purchase_due,
                                                'purchase_due' => (float) $supplier->purchase_due,
                                                'purchase_return_due' => (float) $supplier->purchase_return_due,
                                                'return_due' => (float) $supplier->purchase_return_due
                                            ]) }})"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold text-on-brand rounded shadow-xs hover:opacity-90"
                                            style="background-color: var(--brand-500);">
                                        Action
                                        <svg class="size-3 transition-transform"
                                             :class="{ 'rotate-180': actionMenuOpen && openActionSupplier && openActionSupplier.id === {{ $supplier->id }} }"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="p-8 text-center text-muted text-sm font-medium">
                                    No suppliers found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    {{-- Table Total Footer --}}
                    <tfoot>
                        <tr class="bg-soft text-ink font-bold text-xs border-t-2 border-line">
                            <td :colspan="totalColspan()" class="p-2.5 text-right uppercase tracking-wider">TOTAL</td>
                            <td x-show="columns.purchase_due" class="p-2.5 text-ink font-extrabold">{{ number_format((float) $totalPurchaseDue, 2) }}</td>
                            <td x-show="columns.purchase_return_due" class="p-2.5 text-ink font-extrabold">{{ number_format((float) $totalPurchaseReturnDue, 2) }}</td>
                            <td :colspan="(columns.status ? 1 : 0) + (columns.action ? 1 : 0)" class="p-2.5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Pagination & Entry Info --}}
            <div class="mt-4">
                {{ $suppliers->links() }}
            </div>
        </div>
    </div>

    {{-- Floating Action Dropdown Menu (Outside table to prevent any clipping or scrollbar) --}}
    <div x-show="actionMenuOpen" x-cloak
         @click.outside="actionMenuOpen = false"
         @scroll.window="actionMenuOpen = false"
         @resize.window="actionMenuOpen = false"
         class="fixed z-50 w-44 rounded-md bg-surface shadow-2xl ring-1 ring-line py-1 border border-line text-left"
         :style="{ top: menuTop, left: menuLeft }">
        <template x-if="openActionSupplier">
            <div>
                {{-- Option 1: Edit --}}
                <a :href="'/suppliers/' + openActionSupplier.id + '/edit'"
                   class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-ink hover:bg-soft transition">
                    <svg class="size-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>

                {{-- Option 2: Pay Due Payments --}}
                <button type="button"
                        @click="openPayModal('due', openActionSupplier)"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-ink hover:bg-soft transition text-left">
                    <svg class="size-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Pay Due Payments
                </button>

                {{-- Option 3: Pay Return Due --}}
                <button type="button"
                        @click="openPayModal('return', openActionSupplier)"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-ink hover:bg-soft transition text-left">
                    <svg class="size-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m0 0l-6-6m6 6H3m14 8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                    </svg>
                    Pay Return Due
                </button>

                {{-- Option 4: Delete --}}
                <form :action="'/suppliers/' + openActionSupplier.id" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-danger hover:bg-soft text-left transition">
                        <svg class="size-4 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                    </button>
                </form>
            </div>
        </template>
    </div>

    {{-- Payment Modal (for Pay Due Payments & Pay Return Due) --}}
    <div x-show="modalOpen" x-cloak class="modal-backdrop" @keydown.escape.window="closeModal()">
        <div class="modal rounded-xl shadow-2xl overflow-hidden" style="max-width: 580px;" @click.stop>
            <form :action="modalActionUrl" method="POST">
                @csrf
                {{-- Modal Header: Cyan to Blue Gradient --}}
                <div class="px-5 py-3.5 flex items-center justify-between text-on-brand"
                     style="background: linear-gradient(90deg, var(--brand-500) 0%, var(--brand-600) 100%);">
                    <h3 class="text-base font-bold text-on-brand tracking-wide"
                        x-text="modalType === 'due' ? 'Pay Due Payments' : 'Pay purchase Return Due Payments'">
                    </h3>
                    <button type="button" @click="closeModal()" class="text-on-brand/80 hover:text-on-brand text-xl font-bold leading-none">
                        &times;
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="bg-surface text-ink">
                    {{-- Section 1: Supplier Details --}}
                    <div class="px-5 pt-4 pb-2 text-xs leading-relaxed space-y-0.5">
                        <div class="italic text-muted font-medium">Supplier Details</div>
                        <div class="text-sm font-bold text-ink" x-text="activeSupplier ? activeSupplier.name : ''"></div>
                        <div class="text-ink">
                            Mobile: <span x-text="activeSupplier?.mobile || '—'"></span>
                        </div>
                        <div class="text-ink">
                            Tel No: <span x-text="activeSupplier?.tel_no || '—'"></span>
                        </div>
                        <div class="text-ink">
                            Email: <span x-text="activeSupplier?.email || '—'"></span>
                        </div>
                        <div class="text-ink">
                            TAX Number: <span x-text="activeSupplier?.tax_number || '—'"></span>
                        </div>
                    </div>

                    {{-- Section 2: Summary Table (Light blue background var(--soft)) --}}
                    <div class="px-5 py-2">
                        @php $currencySymbol = \App\Models\Setting::get('currency_symbol', '৳'); @endphp
                        {{-- Due Payments Summary --}}
                        <template x-if="modalType === 'due'">
                            <div class="border border-brand-500 text-xs rounded-sm overflow-hidden" style="background-color: var(--brand-500);">
                                <div class="grid grid-cols-2 divide-x divide-brand-500">
                                    {{-- Left Column --}}
                                    <div class="divide-y divide-brand-500">
                                        <div class="flex justify-between px-3 py-1.5 text-ink">
                                            <span>Opening Balance</span>
                                            <span class="font-semibold" x-text="'{{ $currencySymbol }} ' + Number(activeSupplier?.opening_balance || 0).toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between px-3 py-1.5 text-ink">
                                            <span>Opening Balance Due</span>
                                            <span class="font-semibold" x-text="'{{ $currencySymbol }} ' + Number(activeSupplier?.opening_balance || 0).toFixed(2)"></span>
                                        </div>
                                        <div class="py-1.5 px-3"></div>
                                    </div>
                                    {{-- Right Column --}}
                                    <div class="divide-y divide-brand-500">
                                        <div class="flex justify-between px-3 py-1.5 text-ink">
                                            <span>Total Purchase Amount</span>
                                            <span class="font-semibold" x-text="'{{ $currencySymbol }} ' + Number(activeSupplier?.purchase_due || 0).toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between px-3 py-1.5 text-ink">
                                            <span>Paid Payment</span>
                                            <span class="font-semibold">{{ $currencySymbol }} 0.00</span>
                                        </div>
                                        <div class="flex justify-between px-3 py-1.5 text-ink font-bold">
                                            <span>Purchase Due</span>
                                            <span class="font-bold text-ink" x-text="'{{ $currencySymbol }} ' + Number(activeSupplier?.due || 0).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Return Due Payments Summary --}}
                        <template x-if="modalType === 'return'">
                            <div class="border border-brand-500 text-xs rounded-sm overflow-hidden" style="background-color: var(--brand-500);">
                                <div class="divide-y divide-brand-500">
                                    <div class="flex justify-between px-6 py-1.5 text-ink">
                                        <span class="w-1/2 text-right pr-6">Total Purchase Amount</span>
                                        <span class="w-1/2 font-semibold text-right">{{ $currencySymbol }} 0.00</span>
                                    </div>
                                    <div class="flex justify-between px-6 py-1.5 text-ink">
                                        <span class="w-1/2 text-right pr-6">Paid Payment</span>
                                        <span class="w-1/2 font-semibold text-right">{{ $currencySymbol }} 0.00</span>
                                    </div>
                                    <div class="flex justify-between px-6 py-1.5 text-ink font-bold">
                                        <span class="w-1/2 text-right pr-6">Purchase Due</span>
                                        <span class="w-1/2 font-bold text-ink text-right" x-text="'{{ $currencySymbol }} ' + Number(activeSupplier?.due || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Section 3: Gray Card for Form Inputs --}}
                    <div class="px-5 py-2.5">
                        <div class="p-3.5 rounded-md border border-line space-y-3" style="background-color: var(--brand-500);">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                {{-- Date --}}
                                <div>
                                    <label class="block text-xs font-bold text-ink mb-1">Date</label>
                                    <div class="flex rounded shadow-xs bg-surface border border-line overflow-hidden">
                                        <span class="inline-flex items-center px-2 bg-soft border-r border-line text-muted">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </span>
                                        <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                                               class="w-full text-xs px-2 py-1.5 border-0 focus:ring-0 text-ink bg-surface">
                                    </div>
                                </div>

                                {{-- Amount --}}
                                <div>
                                    <label class="block text-xs font-bold text-ink mb-1">Amount</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" required
                                           :max="activeSupplier ? activeSupplier.due : undefined"
                                           :value="activeSupplier && activeSupplier.due > 0 ? activeSupplier.due.toFixed(2) : ''"
                                           class="w-full text-xs px-2.5 py-1.5 rounded border border-line bg-surface text-ink font-semibold text-right focus:outline-hidden focus:border-brand-500">
                                </div>

                                {{-- Payment Type --}}
                                <div>
                                    <label class="block text-xs font-bold text-ink mb-1">Payment Type</label>
                                    <select name="payment_type" required
                                            class="w-full text-xs px-2.5 py-1.5 rounded border border-line bg-surface text-ink focus:outline-hidden focus:border-brand-500">
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Online">Online</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Payment Note --}}
                            <div>
                                <label class="block text-xs font-bold text-ink mb-1">Payment Note</label>
                                <textarea name="payment_note" rows="3"
                                          class="w-full text-xs p-2 rounded border border-line bg-surface text-ink focus:outline-hidden focus:border-brand-500"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-5 py-3 bg-surface border-t border-line flex justify-end items-center gap-3">
                    <button type="button" @click="closeModal()"
                            class="px-5 py-1.5 text-xs font-medium text-ink bg-canvas hover:bg-soft border border-line rounded shadow-xs transition">
                        Close
                    </button>
                    <button type="submit"
                            class="px-5 py-1.5 text-xs font-bold text-on-brand rounded shadow-xs transition flex items-center gap-1.5"
                            style="background-color: var(--brand-500);">
                        Save
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
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

        actionMenuOpen: false,
        openActionSupplier: null,
        menuTop: '0px',
        menuLeft: '0px',

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
            { key: 'purchase_return_due', label: 'Purchase Return Due' },
            { key: 'status', label: 'Status' },
            { key: 'action', label: 'Action' }
        ],

        toggleColumn(key) {
            this.columns[key] = !this.columns[key];
        },

        activeColumnsQuery() {
            return Object.keys(this.columns)
                .filter(k => this.columns[k] && k !== 'action')
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            return `/suppliers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            window.open(`/suppliers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
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
                    return td ? td.innerText.trim() : '';
                });
                text += rowVals.join('\t') + '\n';
            });

            navigator.clipboard.writeText(text).then(() => {
                alert('Table data copied to clipboard!');
            });
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
            const menuHeight = 160;
            const spaceBelow = window.innerHeight - rect.bottom;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                this.menuTop = (rect.top - menuHeight) + 'px';
            } else {
                this.menuTop = (rect.bottom + 4) + 'px';
            }
            this.menuLeft = Math.max(10, rect.right - 176) + 'px';
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
                    cb.checked = true;
                    this.selectedSuppliers.push(cb.value);
                });
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }
        }
    };
}
</script>
@endsection
