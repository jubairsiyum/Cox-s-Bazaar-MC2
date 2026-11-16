@extends('layouts.app')

@section('title', 'Customers List')
@section('page_title', 'Customers List')

@section('content')
<div x-data="customersPage()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-ink">Customers List</h1>
                <span class="text-sm text-muted">View/Search Customers</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1.5 text-xs text-muted">
                <a href="{{ route('dashboard') }}" class="hover:border-line flex items-center gap-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Home
                </a>
                <span>&rsaquo;</span>
                <span class="text-ink font-medium">Customers List</span>
            </div>
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}"
                   class="px-4 py-2 rounded-md text-on-brand font-medium text-sm transition-all shadow-sm flex items-center gap-1.5 hover:opacity-95"
                   style="background-color: var(--brand-500);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Customer
                </a>
            @endcan
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card border-line border-t-cyan-500 shadow-sm">
        <div class="card-header border-b border-line">
            <h2 class="text-base font-semibold text-ink">Customers List</h2>
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2">
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

                    {{-- Columns Visibility Dropdown --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="px-3 py-1.5 rounded text-xs font-semibold text-on-brand shadow-xs transition hover:opacity-90 cursor-pointer flex items-center gap-1"
                                style="background-color: var(--brand-500);">
                            Columns
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-44 rounded shadow-2xl py-1 z-50 text-left border border-line"
                             style="background-color: var(--brand-500);">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-on-brand transition hover:border-line text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    <label class="text-muted flex items-center gap-1.5 text-xs sm:text-sm">
                        Search:
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="Search customers..."
                               class="form-control py-1 px-2.5 text-xs rounded border-line bg-surface w-40 sm:w-48">
                    </label>
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-line rounded-md overflow-x-auto">
                <table id="customers-table" class="data-table w-full text-left text-xs sm:text-sm">
                    <thead>
                        <tr class="text-on-brand uppercase font-bold text-xs" style="background-color: var(--brand-500);">
                            <th class="w-10 p-2.5 text-center">
                                <input type="checkbox" @change="toggleSelectAll()" :checked="allSelected"
                                       class="rounded border-line border-line focus:border-line size-3.5">
                            </th>
                            <th x-show="columns.customer_id" class="p-2.5 whitespace-nowrap">Customer ID</th>
                            <th x-show="columns.name" class="p-2.5 whitespace-nowrap">Customer Name</th>
                            <th x-show="columns.mobile" class="p-2.5 whitespace-nowrap">Mobile No.</th>
                            <th x-show="columns.tel_no" class="p-2.5 whitespace-nowrap">Tel No.</th>
                            <th x-show="columns.email" class="p-2.5 whitespace-nowrap">Email</th>
                            <th x-show="columns.city" class="p-2.5 whitespace-nowrap">City</th>
                            <th x-show="columns.status" class="p-2.5 text-center whitespace-nowrap">Status</th>
                            @can('customers.manage')
                                <th x-show="columns.action" class="p-2.5 text-center whitespace-nowrap">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($customers as $customer)
                            <tr class="hover:bg-soft/50 transition">
                                <td class="p-2.5 text-center">
                                    <input type="checkbox" value="{{ $customer->id }}"
                                           class="rounded border-line border-line focus:border-line size-3.5">
                                </td>
                                <td x-show="columns.customer_id" data-col="customer_id" class="p-2.5 font-medium text-ink whitespace-nowrap">
                                    {{ $customer->customer_id }}
                                </td>
                                <td x-show="columns.name" data-col="name" class="p-2.5 font-medium text-ink whitespace-nowrap">
                                    {{ $customer->name }}
                                </td>
                                <td x-show="columns.mobile" data-col="mobile" class="p-2.5 text-muted whitespace-nowrap">
                                    {{ $customer->mobile ?: '—' }}
                                </td>
                                <td x-show="columns.tel_no" data-col="tel_no" class="p-2.5 text-muted whitespace-nowrap">
                                    {{ $customer->tel_no ?: '—' }}
                                </td>
                                <td x-show="columns.email" data-col="email" class="p-2.5 text-muted whitespace-nowrap">
                                    {{ $customer->email ?: '—' }}
                                </td>
                                <td x-show="columns.city" data-col="city" class="p-2.5 text-muted whitespace-nowrap">
                                    {{ $customer->city ?: '—' }}
                                </td>
                                <td x-show="columns.status" data-col="status" class="p-2.5 text-center whitespace-nowrap">
                                    @can('update', $customer)
                                        <form method="POST" action="{{ route('customers.toggle-status', $customer) }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Click to toggle status"
                                                    class="px-2.5 py-0.5 rounded text-[11px] font-bold text-on-brand transition-opacity hover:opacity-80 cursor-pointer shadow-xs"
                                                    style="background-color: {{ $customer->status === 'active' ? 'var(--success)' : 'var(--danger)' }};">
                                                {{ ucfirst($customer->status) }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded text-[11px] font-bold text-on-brand shadow-xs"
                                              style="background-color: {{ $customer->status === 'active' ? 'var(--success)' : 'var(--danger)' }};">
                                            {{ ucfirst($customer->status) }}
                                        </span>
                                    @endcan
                                </td>
                                @can('customers.manage')
                                    <td x-show="columns.action" data-col="action" class="p-2.5 text-center whitespace-nowrap">
                                        <div class="relative inline-block text-left">
                                            <button type="button"
                                                    @click.stop="toggleActionMenu($event, {{ json_encode(['id' => $customer->id, 'name' => $customer->name]) }})"
                                                    class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold text-on-brand rounded shadow-xs hover:opacity-90 transition cursor-pointer"
                                                    style="background-color: var(--brand-500);">
                                                Action
                                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="totalColspan()" class="p-8 text-center text-muted">
                                    No customers found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Floating Teleport Action Dropdown Menu --}}
            <template x-teleport="body">
                <div x-show="actionMenuOpen"
                     x-cloak
                     @click.outside="actionMenuOpen = false"
                     class="fixed z-9999 w-44 rounded-md bg-surface shadow-xl border border-line py-1 text-xs text-ink"
                     :style="`top: ${menuTop}; left: ${menuLeft};`">
                    @can('customers.manage')
                        <a :href="`/customers/${openActionCustomer?.id}/edit`"
                           class="flex items-center gap-2 px-3 py-1.5 hover:bg-soft border-line font-medium transition">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                    @endcan

                    @can('customers.manage')
                        <form :action="`/customers/${openActionCustomer?.id}`" method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this customer?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full flex items-center gap-2 px-3 py-1.5 hover:bg-soft text-danger font-medium text-left transition">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete
                            </button>
                        </form>
                    @endcan
                </div>
            </template>

            {{-- Footer: Pagination & Counter --}}
            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        </div>
    </div>
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
            return `/customers/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            window.open(`/customers/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
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
            if (this.columns.customer_id) count++;
            if (this.columns.name) count++;
            if (this.columns.mobile) count++;
            if (this.columns.tel_no) count++;
            if (this.columns.email) count++;
            if (this.columns.city) count++;
            if (this.columns.status) count++;
            if (this.columns.action) count++;
            return count;
        },

        toggleActionMenu(event, customer) {
            if (this.actionMenuOpen && this.openActionCustomer && this.openActionCustomer.id === customer.id) {
                this.actionMenuOpen = false;
                this.openActionCustomer = null;
                return;
            }
            this.openActionCustomer = customer;
            const rect = event.currentTarget.getBoundingClientRect();
            const menuHeight = 100;
            const spaceBelow = window.innerHeight - rect.bottom;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                this.menuTop = (rect.top - menuHeight) + 'px';
            } else {
                this.menuTop = (rect.bottom + 4) + 'px';
            }
            this.menuLeft = Math.max(10, rect.right - 176) + 'px';
            this.actionMenuOpen = true;
        },

        toggleSelectAll() {
            this.allSelected = !this.allSelected;
            const checkboxes = document.querySelectorAll('#customers-table tbody input[type="checkbox"]');
            this.selectedCustomers = [];
            if (this.allSelected) {
                checkboxes.forEach(cb => {
                    cb.checked = true;
                    this.selectedCustomers.push(cb.value);
                });
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }
        }
    };
}
</script>
@endsection
