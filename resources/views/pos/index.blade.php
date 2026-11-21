@extends('layouts.app')

@section('title', 'POS Terminal — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'POS Terminal')

@php
    $posUser = auth()->user();
    $isPosAdmin = $posUser?->hasRole('Super Admin') ?? false;
    $posDefaultLoc = (int) ($defaultLocationId ?? 0) ?: (int) ($posUser?->branch_id ?? $posUser?->location_id ?? 0);
    if (!$isPosAdmin && !$posDefaultLoc) {
        $posDefaultLoc = (int) (\App\Models\Location::where('status', 'active')
            ->where('type', '!=', \App\Enums\LocationType::Warehouse->value ?? 'warehouse')
            ->value('id') ?? \App\Models\Location::where('status', 'active')->value('id') ?? 1);
    }
@endphp

@section('content')
<div x-data="posTerminal({
        locationId: @js($posDefaultLoc ?: null)
    })" class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Sales</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">POS Terminal</span>
            </nav>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">POS Terminal</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-3 py-1 text-xs font-semibold text-success ring-1 ring-success/20">
                    <span class="size-1.5 rounded-full bg-success"></span>
                    Quick Sale — Posts Immediately
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Scan or search products, take payment and the invoice posts with the stock-out in one step.
            </p>
        </div>

        <a href="{{ route('sales-invoices.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
            <x-icon name="enquiries" class="size-4" />
            <span>Invoice Register</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-3xl border border-danger/30 bg-danger/10 p-5 shadow-2xs">
            <div class="flex items-start gap-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-danger/15 text-danger ring-1 ring-danger/25">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-danger">Please Enter Valid Data</p>
                    <ul class="mt-2 list-disc list-inside space-y-1 text-xs sm:text-sm text-ink/90">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('pos.store') }}">
        @csrf

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Left: Search + Cart --}}
            <div class="xl:col-span-2 space-y-6">
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Product Search / Barcode
                    </label>
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @input.debounce.200ms="searchUnits()"
                               @keydown.enter.prevent="searchUnits()"
                               placeholder="Type product name or scan barcode, then press Enter..."
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-3 pl-10 text-base text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <x-icon name="search" class="size-5 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                    </div>
                    <div x-show="results.length > 0" x-cloak @click.outside="results = []"
                         class="relative z-20 mt-2 max-h-64 overflow-y-auto rounded-xl border border-line bg-surface shadow-card">
                        <template x-for="unit in results" :key="unit.id">
                            <button type="button" @click="addRow(unit)"
                                    class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left hover:bg-soft transition-colors cursor-pointer">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-ink truncate" x-text="unit.display_name"></div>
                                    <div class="text-[11px] text-muted" x-text="unit.unit + ' · Stock: ' + unit.quantity + (unit.barcode ? ' · ' + unit.barcode : '')"></div>
                                </div>
                                <span class="text-xs font-bold text-brand-500 tabular-nums" x-text="formatMoney(unit.sale_price)"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                    <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-line">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                                <x-icon name="cart" class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-ink">Cart</h3>
                                <p class="text-xs text-muted" x-text="rows.length + (rows.length === 1 ? ' item' : ' items')"></p>
                            </div>
                        </div>
                    </div>

                    <div class="max-w-full overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                                    <th class="py-3 px-5 w-12">#</th>
                                    <th class="py-3 px-5">Product</th>
                                    <th class="py-3 px-5 w-28">Qty</th>
                                    <th class="py-3 px-5 w-32">Rate</th>
                                    <th class="py-3 px-5 w-32">Discount</th>
                                    <th class="py-3 px-5 text-right w-32">Subtotal</th>
                                    <th class="py-3 px-5 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <template x-for="(row, index) in rows" :key="row.unit_id">
                                    <tr class="hover:bg-soft/40 transition-colors">
                                        <td class="py-3 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                                        <td class="py-3 px-5">
                                            <div class="text-sm font-semibold text-ink" x-text="row.product_name"></div>
                                            <input type="hidden" :name="`items[${index}][unit_id]`" :value="row.unit_id">
                                        </td>
                                        <td class="py-3 px-5">
                                            <input type="number" min="1" step="1" :name="`items[${index}][quantity]`"
                                                   x-model.number="row.quantity"
                                                   class="w-20 rounded-xl border border-line bg-surface px-2.5 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        </td>
                                        <td class="py-3 px-5">
                                            <input type="number" min="0" step="0.01" :name="`items[${index}][unit_price]`"
                                                   x-model.number="row.unit_price"
                                                   class="w-24 rounded-xl border border-line bg-surface px-2.5 py-2 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        </td>
                                        <td class="py-3 px-5">
                                            <input type="number" min="0" step="0.01" :name="`items[${index}][discount]`"
                                                   x-model.number="row.discount"
                                                   class="w-24 rounded-xl border border-line bg-surface px-2.5 py-2 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        </td>
                                        <td class="py-3 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                            x-text="formatMoney(lineTotal(row))"></td>
                                        <td class="py-3 px-5">
                                            <button type="button" @click="rows.splice(index, 1)"
                                                    class="rounded-lg border border-line bg-soft px-2 py-1 text-[11px] font-semibold text-danger hover:border-danger transition-colors cursor-pointer">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="rows.length === 0" x-cloak class="py-12 px-6 text-center bg-canvas/20">
                        <div class="max-w-sm mx-auto flex flex-col items-center">
                            <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                <x-icon name="cart" class="size-7" />
                            </div>
                            <p class="text-sm font-bold text-ink">Cart is empty</p>
                            <p class="text-xs text-muted mt-1 leading-relaxed">Search or scan a product above to start the sale.</p>
                        </div>
                    </div>

                    @if($errors->has('items') || $errors->has('items.*'))
                        <div class="px-5 sm:px-6 py-3 border-t border-danger/20 bg-danger/5 space-y-1">
                            @error('items')
                                <p class="text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                            @foreach ($errors->get('items.*') as $messages)
                                @foreach ($messages as $message)
                                    <p class="text-xs font-medium text-danger">{{ $message }}</p>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: Checkout Panel --}}
            <div class="space-y-6">
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Sale Info</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="customer_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Customer (Optional — Walk-in)
                            </label>
                            <select id="customer_id" name="customer_id"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('customer_id') border-danger @enderror">
                                <option value="">Walk-in Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Business Location<span class="text-danger">*</span>
                            </label>
                            @if (!$isPosAdmin && $posDefaultLoc)
                                <div class="w-full rounded-xl border border-line bg-soft/50 px-3.5 py-2.5 text-sm text-ink shadow-2xs flex items-center gap-2">
                                    <x-icon name="store" class="size-4 text-brand-500" />
                                    <span class="font-semibold">{{ $locations->firstWhere('id', $posDefaultLoc)?->name ?? 'Assigned Outlet' }}</span>
                                </div>
                                <input type="hidden" id="location_id" name="location_id" :value="locationId">
                            @else
                                <select id="location_id" name="location_id" required x-model="locationId"
                                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('location_id') border-danger @enderror">
                                    <option value="">Please Select</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('location_id')
                                <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Payment</h2>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Subtotal</dt>
                            <dd class="font-semibold text-ink tabular-nums" x-text="formatMoney(subtotal)"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Discount</dt>
                            <dd class="font-semibold text-ink tabular-nums" x-text="formatMoney(discountTotal)"></dd>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-line">
                            <dt class="font-bold text-ink">Grand Total</dt>
                            <dd class="text-xl font-extrabold text-brand-500 tabular-nums" x-text="formatMoney(grandTotal)"></dd>
                        </div>
                    </dl>

                    <div class="mt-5">
                        <label for="paid_amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Amount Tendered<span class="text-danger">*</span>
                        </label>
                        <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0" required
                               x-model.number="paidAmount"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-3 text-lg font-bold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('paid_amount') border-danger @enderror">
                        @error('paid_amount')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4 rounded-2xl border px-4 py-3"
                         :class="change >= 0 ? 'border-success/30 bg-success/5' : 'border-warning/30 bg-warning/5'">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted" x-text="change >= 0 ? 'Change to Return' : 'Remaining Due'"></span>
                            <span class="text-xl font-extrabold tabular-nums"
                                  :class="change >= 0 ? 'text-success' : 'text-warning'"
                                  x-text="formatMoney(change >= 0 ? change : -change)"></span>
                        </div>
                    </div>

                    <button type="submit" :disabled="rows.length === 0"
                            class="btn-scale mt-5 w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed px-6 py-3.5 text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-5" />
                        <span>Complete Sale</span>
                    </button>
                    <p class="mt-3 text-[11px] text-muted text-center leading-relaxed">
                        The invoice is created and posted immediately — stock leaves with the customer.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function posTerminal(state) {
    return {
        rows: [],
        searchQuery: '',
        results: [],
        paidAmount: 0,
        locationId: state.locationId || null,

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        async searchUnits() {
            if (this.searchQuery.trim().length < 1) {
                this.results = [];
                return;
            }

            try {
                const response = await fetch(@js(route('pos.search-units')) + '?search=' + encodeURIComponent(this.searchQuery), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.results = (data.units || []).filter(unit => !this.rows.some(row => row.unit_id === unit.id));

                // A barcode scan resolves to a single product — add it right away.
                if (this.results.length === 1 && this.searchQuery.trim().length >= 8) {
                    this.addRow(this.results[0]);
                }
            } catch (error) {
                this.results = [];
            }
        },

        addRow(unit) {
            this.rows.push({
                unit_id: unit.id,
                product_name: unit.display_name,
                quantity: 1,
                unit_price: parseFloat(unit.sale_price) || 0,
                discount: 0
            });
            this.results = [];
            this.searchQuery = '';
        },

        lineTotal(row) {
            return Math.max(0, (row.quantity || 0) * (row.unit_price || 0) - (row.discount || 0));
        },

        get subtotal() {
            return this.rows.reduce((sum, row) => sum + this.lineTotal(row), 0);
        },

        get discountTotal() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.discount) || 0), 0);
        },

        get grandTotal() {
            return this.subtotal;
        },

        get change() {
            return (parseFloat(this.paidAmount) || 0) - this.grandTotal;
        }
    };
}
</script>
@endsection
