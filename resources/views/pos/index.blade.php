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
        locationId: @js($restoreLocationId ?: null),
        customers: @js($customers->map(fn ($customer) => ['id' => (int) $customer->id, 'name' => $customer->name, 'mobile' => $customer->mobile])),
        customerName: @js(old('customer_name', '')),
        customerId: @js(old('customer_id', '')),
        customerPhone: @js(old('mobile_no', '')),
        rows: @js($cartRows),
        paidAmount: @js($oldPaidAmount),
        discountMode: @js($hasOldCartDiscount ? 'amount' : 'percent'),
        discountValue: @js($oldCartDiscount)
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

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
            {{-- Left: Search + Product grid (expanded) --}}
            <div class="xl:col-span-3 space-y-6">
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Product Search / Barcode
                    </label>
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @input.debounce.200ms="searchUnits()"
                               @keydown.enter.prevent="searchUnits()"
                               placeholder="Type product name or scan barcode to filter the products below..."
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-3 pl-10 text-base text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <x-icon name="search" class="size-5 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                    </div>

                    {{-- Stocked products, branch-scoped: always visible, filtered live by the search bar --}}
                    <div class="mt-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-ink">In-Stock Products</h3>
                            <p class="text-xs text-muted" x-text="results.length + (results.length === 1 ? ' product' : ' products')"></p>
                        </div>

                        <div x-show="!loaded" x-cloak class="py-8 text-center rounded-2xl border border-dashed border-line bg-canvas/20">
                            <p class="text-xs text-muted">Loading products…</p>
                        </div>

                        <div x-show="loaded && results.length > 0" x-cloak
                             class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3 max-h-[60vh] overflow-y-auto pr-1">
                            <template x-for="unit in results" :key="unit.id">
                                <div class="flex flex-col rounded-2xl border border-line bg-surface p-3.5 text-left shadow-2xs transition-colors"
                                     :class="unit.in_stock ? '' : 'opacity-50'">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="relative inline-flex size-12 shrink-0">
                                            <span class="relative inline-flex size-12 items-center justify-center overflow-hidden rounded-xl border border-line bg-soft">
                                                <span class="text-xs font-bold text-muted" x-text="initials(unit.brand_name || unit.display_name)"></span>
                                                {{-- Product photo when there is one, otherwise the brand logo --}}
                                                <template x-if="unit.photo || unit.brand_logo">
                                                    <img :src="unit.photo || unit.brand_logo" :alt="unit.display_name"
                                                         class="absolute inset-0 size-full bg-surface object-contain p-0.5"
                                                         x-on:error="$el.remove()">
                                                </template>
                                            </span>
                                            {{-- Brand logo badge on top of a product photo --}}
                                            <template x-if="unit.photo && unit.brand_logo">
                                                <span class="absolute -bottom-1.5 -right-1.5 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border border-line bg-surface shadow-2xs">
                                                    <img :src="unit.brand_logo" :alt="(unit.brand_name || 'Brand') + ' logo'"
                                                         class="absolute inset-0 size-full object-contain p-0.5"
                                                         x-on:error="$el.remove()">
                                                </span>
                                            </template>
                                        </span>
                                        <span class="text-[11px] font-semibold text-muted truncate" x-text="unit.brand_name || '—'"></span>
                                    </div>
                                    <div class="mt-2.5 text-sm font-semibold text-ink line-clamp-2 min-h-10" x-text="unit.display_name"></div>
                                    <div class="mt-1 text-[11px] text-muted truncate" x-text="unit.unit + (unit.barcode ? ' · ' + unit.barcode : '')"></div>
                                    <div x-show="unit.branches && unit.branches.length" class="mt-0.5 text-[11px] text-brand-500 truncate"
                                         x-text="unit.branches ? unit.branches.map(b => b.name + ': ' + b.quantity).join(' · ') : ''"></div>
                                    <div class="mt-2.5 flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-brand-500 tabular-nums" x-text="formatMoney(unit.sale_price)"></span>
                                        <span class="text-[11px] font-semibold tabular-nums"
                                              :class="unit.in_stock ? 'text-success' : 'text-danger'"
                                              x-text="'Stock: ' + unit.quantity"></span>
                                    </div>
                                    <div class="mt-auto pt-3">
                                        <button type="button" @click="addRow(unit)"
                                                :disabled="!unit.in_stock"
                                                class="btn-scale inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition-colors"
                                                :class="unit.in_stock ? 'cursor-pointer bg-brand-500 text-on-brand hover:bg-brand-600' : 'cursor-not-allowed bg-soft text-muted'">
                                            <x-icon name="cart" class="size-3.5" />
                                            <span x-text="unit.in_stock ? 'Add to Cart' : 'Insufficient Stock'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="loaded && results.length === 0" x-cloak
                             class="py-10 text-center rounded-2xl border border-dashed border-line bg-canvas/20">
                            <div class="max-w-xs mx-auto flex flex-col items-center">
                                <div class="flex size-12 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3">
                                    <x-icon name="box" class="size-6" />
                                </div>
                                <p class="text-sm font-bold text-ink">No products found</p>
                                <p class="text-xs text-muted mt-1 leading-relaxed">No stocked products match your search at this branch.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Cart + Checkout --}}
            <div class="xl:col-span-2 space-y-6">
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
                                    <th class="py-3 px-5 w-20">Stock</th>
                                    <th class="py-3 px-5 w-44">Branch</th>
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
                                            <div class="text-sm font-semibold text-ink tabular-nums" x-text="row.stock"></div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <div class="text-xs text-muted" x-text="row.branches && row.branches.length ? row.branches.map(b => b.name + ': ' + b.quantity).join(' · ') : '—'"></div>
                                        </td>
                                        <td class="py-3 px-5">
                                            <input type="number" min="1" step="1" :max="row.stock" :name="`items[${index}][quantity]`"
                                                   x-model.number="row.quantity"
                                                   class="w-20 rounded-xl border border-line bg-surface px-2.5 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        </td>
                                        <td class="py-3 px-5">
                                            <input type="number" min="0" step="0.01" :name="`items[${index}][unit_price]`"
                                                   x-model.number="row.unit_price"
                                                   @if(!$isPosAdmin) readonly @endif
                                                   class="w-24 rounded-xl border border-line {{ $isPosAdmin ? 'bg-surface' : 'bg-soft/60 cursor-not-allowed' }} px-2.5 py-2 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
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

                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Sale Info</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="customer_name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Customer (Optional — Walk-in)
                            </label>
                            <input type="text" id="customer_name" name="customer_name" list="pos_customer_options" autocomplete="off"
                                   x-model="customerName" @input="syncCustomer()" @change="syncCustomer()"
                                   placeholder="Search an existing customer or type a new name..."
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('customer_name') border-danger @enderror">
                            <datalist id="pos_customer_options">
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->name }}"></option>
                                @endforeach
                            </datalist>
                            <input type="hidden" name="customer_id" :value="customerId">
                            <p class="mt-1.5 text-xs text-muted">Search an existing customer or type a new name to add one.</p>
                            @error('customer_id')
                                <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                            @error('customer_name')
                                <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Mobile number for a NEW customer: appears as soon as the
                             typed name does not match an existing customer, and it
                             is mandatory then. Recorded in the customer list under
                             the `mobile` column --}}
                        <div x-show="customerName.trim() !== '' && !matchedCustomer" x-cloak>
                            <label for="mobile_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Mobile No<span class="text-danger">*</span>
                            </label>
                            <input type="text" id="mobile_no" name="mobile_no" x-model="customerPhone" autocomplete="off"
                                   :required="customerName.trim() !== '' && !matchedCustomer"
                                   placeholder="01XXXXXXXXX"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-all @error('mobile_no') border-danger @enderror">
                            @error('mobile_no')
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
                                <select id="location_id" name="location_id" required x-model="locationId" @change="searchUnits()"
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
                            <dd class="font-semibold text-ink tabular-nums" x-text="formatMoney(totalDiscount)"></dd>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-line">
                            <dt class="font-bold text-ink">Grand Total</dt>
                            <dd class="text-xl font-extrabold text-brand-500 tabular-nums" x-text="formatMoney(grandTotal)"></dd>
                        </div>
                    </dl>

                    <div class="mt-5">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">Cart Discount</span>

                        {{-- Percentage / Amount toggle --}}
                        <div class="flex rounded-xl border border-line bg-soft/50 p-1 mb-3">
                            <button type="button" @click="discountMode = 'percent'"
                                    class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                                    :class="discountMode === 'percent' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink'">
                                Percentage (%)
                            </button>
                            <button type="button" @click="discountMode = 'amount'"
                                    class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                                    :class="discountMode === 'amount' ? 'bg-brand-500 text-on-brand shadow-xs' : 'text-muted hover:text-ink'">
                                Amount (৳)
                            </button>
                        </div>

                        {{-- Quick percentage presets --}}
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            <template x-for="pct in [5, 10, 15, 20]" :key="pct">
                                <button type="button" @click="applyPercent(pct)"
                                        class="rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 transition-colors cursor-pointer"
                                        x-text="pct + '%'"></button>
                            </template>
                            <button type="button" @click="clearDiscount()"
                                    class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer">
                                Clear
                            </button>
                        </div>

                        <label for="discount_value" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Manual Discount <span x-text="discountMode === 'percent' ? '(%)' : '(৳)'"></span>
                        </label>
                        <input type="number" id="discount_value" step="0.01" min="0"
                               x-model.number="discountValue"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                        <input type="hidden" name="cart_discount" :value="cartDiscount">
                    </div>

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

                    <button type="submit" :disabled="rows.length === 0 || !(paidAmount > 0)"
                            class="btn-scale mt-5 w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed px-6 py-3.5 text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-5" />
                        <span>Complete Sale</span>
                    </button>
                    <p x-show="rows.length > 0 && !(paidAmount > 0)" x-cloak
                       class="mt-3 text-[11px] font-semibold text-warning text-center leading-relaxed">
                        Enter the amount tendered to complete the sale.
                    </p>
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
        rows: Array.isArray(state.rows) ? state.rows : [],
        searchQuery: '',
        results: [],
        loaded: false,
        paidAmount: parseFloat(state.paidAmount) || 0,
        discountMode: state.discountMode === 'amount' ? 'amount' : 'percent',
        discountValue: parseFloat(state.discountValue) || 0,
        locationId: state.locationId || null,
        customerName: state.customerName || '',
        customerId: state.customerId ? String(state.customerId) : '',
        customerPhone: state.customerPhone || '',
        customerDirectory: (state.customers || []).map(c => ({ id: c.id, name: c.name, mobile: c.mobile })),

        async init() {
            await this.searchUnits();
        },

        get matchedCustomer() {
            const key = String(this.customerName || '').trim().replace(/\s+/g, ' ').toLowerCase();

            if (key === '') {
                return null;
            }

            return this.customerDirectory.find(c => String(c.name || '').trim().replace(/\s+/g, ' ').toLowerCase() === key) || null;
        },

        syncCustomer() {
            const customer = this.matchedCustomer;

            if (customer) {
                this.customerId = String(customer.id);
                this.customerPhone = customer.mobile || '';
                return;
            }

            // Leaving an existing customer, or clearing the name, must not carry
            // that customer's number over to the new name being typed.
            if (this.customerId !== '' || String(this.customerName || '').trim() === '') {
                this.customerPhone = '';
            }

            this.customerId = '';
        },

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        initials(label) {
            const parts = String(label || '').trim().toUpperCase().split(/\s+/).filter(Boolean);

            if (parts.length === 0) {
                return '?';
            }

            return parts.slice(0, 2).map(part => part.charAt(0)).join('');
        },

        async searchUnits() {
            try {
                const params = new URLSearchParams({ search: this.searchQuery.trim() });

                if (this.locationId) {
                    params.set('location_id', this.locationId);
                }

                const response = await fetch(@js(route('pos.search-units')) + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.results = data.units || [];

                // A barcode scan resolves to a single product — add it right away.
                if (this.results.length === 1 && this.searchQuery.trim().length >= 8) {
                    this.addRow(this.results[0]);
                }
            } catch (error) {
                this.results = [];
            } finally {
                this.loaded = true;
            }
        },

        addRow(unit) {
            if (!unit.in_stock) {
                return;
            }

            // Clicking a product already in the cart just bumps its quantity,
            // capped at the available stock.
            const existing = this.rows.find(row => row.unit_id === unit.id);
            if (existing) {
                existing.quantity = Math.min((existing.quantity || 0) + 1, unit.quantity || 1);
            } else {
                this.rows.push({
                    unit_id: unit.id,
                    product_name: unit.display_name,
                    quantity: 1,
                    unit_price: parseFloat(unit.sale_price) || 0,
                    discount: 0,
                    stock: unit.quantity || 0,
                    branches: unit.sell_location
                        ? [{ name: unit.sell_location, quantity: unit.quantity || 0 }]
                        : (unit.branches || [])
                });
            }

            this.searchQuery = '';
            this.searchUnits();
        },

        lineTotal(row) {
            return Math.max(0, (row.quantity || 0) * (row.unit_price || 0) - (row.discount || 0));
        },

        applyPercent(pct) {
            this.discountMode = 'percent';
            this.discountValue = pct;
        },

        clearDiscount() {
            this.discountValue = 0;
        },

        get cartDiscount() {
            const value = parseFloat(this.discountValue) || 0;
            const amount = this.discountMode === 'percent' ? this.subtotal * value / 100 : value;
            return Math.min(this.subtotal, Math.round(amount * 100) / 100);
        },

        get subtotal() {
            return this.rows.reduce((sum, row) => sum + this.lineTotal(row), 0);
        },

        get discountTotal() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.discount) || 0), 0);
        },

        get totalDiscount() {
            return this.discountTotal + this.cartDiscount;
        },

        get grandTotal() {
            return Math.max(0, this.subtotal - this.cartDiscount);
        },

        get change() {
            return (parseFloat(this.paidAmount) || 0) - this.grandTotal;
        }
    };
}
</script>
@endsection
