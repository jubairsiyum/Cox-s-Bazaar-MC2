@php
    $isEdit = $order->exists;
    $dateValue = old('order_date', $isEdit ? $order->order_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $expectedValue = old('expected_date', $isEdit ? ($order->expected_date?->format('Y-m-d') ?? '') : '');
    $taxRateValue = old('tax_rate', $isEdit ? $order->tax_rate : 0);
    $selectedLocationId = (int) old('location_id', $defaultLocationId ?? ($isEdit ? $order->location_id : 0));
    $customerDirectory = $customers
        ->map(fn ($customer) => ['id' => (int) $customer->id, 'name' => $customer->name, 'mobile' => $customer->mobile, 'tel_no' => $customer->tel_no, 'email' => $customer->email])
        ->values()
        ->all();
@endphp

<div x-data="orderForm({
        rows: @js($rows),
        taxRate: @js((float) $taxRateValue),
        initialLocationId: @js($selectedLocationId),
        locationsMap: @js($locations->pluck('name', 'id'))
    })"
    x-init="init()"
    class="space-y-6">

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="rounded-3xl border border-danger/30 bg-danger/10 p-5 shadow-2xs">
            <div class="flex items-start gap-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-danger/15 text-danger ring-1 ring-danger/25">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-danger">Please Correct the Errors Below</p>
                    <ul class="mt-2 list-disc list-inside space-y-1 text-xs sm:text-sm text-ink/90">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Main Layout: 2 Columns on Large Screens --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        {{-- Left Column (8 cols): Order Information + Products Table --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Card 1: Order Information & Location Context --}}
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card transition-all">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-5 border-b border-line">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                            <x-icon name="enquiries" class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-ink">Order Information</h2>
                            <p class="text-xs text-muted">Customer details, delivery timeline, and fulfillment location.</p>
                        </div>
                    </div>

                    {{-- Dynamic Location Badge indicator --}}
                    <div x-show="locationId" x-cloak class="inline-flex items-center gap-2 rounded-2xl bg-soft border border-line px-3 py-1.5 text-xs">
                        <span class="size-2 rounded-full bg-success animate-pulse"></span>
                        <span class="text-muted">Stock scoped to:</span>
                        <span class="font-bold text-ink" x-text="locationName"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    {{-- Customer Picker with datalist --}}
                    <div>
                        <label for="customer_name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Customer<span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="customer_name" name="customer_name" list="customer_options" autocomplete="off"
                                   value="{{ old('customer_name', $isEdit ? $order->customer?->name : '') }}"
                                   placeholder="Search an existing customer or type a new name..."
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('customer_name') border-danger @enderror">
                            <datalist id="customer_options">
                                @foreach($customerDirectory as $customer)
                                    <option value="{{ $customer['name'] }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <input type="hidden" id="customer_id" name="customer_id" value="{{ old('customer_id', $isEdit ? $order->customer_id : '') }}">
                        <p class="mt-1.5 text-xs text-muted">Search an existing customer or type a new name to add one.</p>
                        @error('customer_id')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                        @error('customer_name')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Customer Phone / Mobile Number --}}
                    <div>
                        <label for="mobile_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Mobile No.<span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="mobile_no" name="mobile_no" required
                                   value="{{ old('mobile_no', $isEdit ? ($order->customer?->mobile ?? $order->customer?->tel_no) : '') }}"
                                   placeholder="e.g. 018XXXXXXXX"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 pl-10 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('mobile_no') border-danger @enderror">
                            <x-icon name="phone" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        </div>
                        <p class="mt-1.5 text-xs text-muted">Auto-filled for existing customers. Saved back to their profile.</p>
                        @error('mobile_no')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Customer Email (Optional) --}}
                    <div>
                        <label for="customer_email" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Email
                        </label>
                        <div class="relative">
                            <input type="email" id="customer_email" name="customer_email"
                                   value="{{ old('customer_email', $isEdit ? $order->customer?->email : '') }}"
                                   placeholder="customer@example.com"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 pl-10 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('customer_email') border-danger @enderror">
                            <x-icon name="mail" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        </div>
                        <p class="mt-1.5 text-xs text-muted">Optional. Auto-filled for existing customers.</p>
                        @error('customer_email')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Business Location --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                                Business Location<span class="text-danger">*</span>
                            </label>
                            <span class="text-[11px] font-medium text-brand-500">Determines Stock</span>
                        </div>
                        <div class="relative">
                            <select id="location_id" name="location_id" required
                                    x-model="locationId"
                                    @change="onLocationChange($event)"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 pl-10 text-sm text-ink font-medium shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('location_id') border-danger @enderror">
                                <option value="">— Please Select Business Location —</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) old('location_id', $defaultLocationId ?? ($isEdit ? $order->location_id : 0)) === $location->id)>
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-icon name="warehouse" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                        </div>
                        <p class="mt-1.5 text-xs text-muted">Product stock is strictly scoped to this selected branch/outlet.</p>
                        @error('location_id')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Order Date --}}
                    <div>
                        <label for="order_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Order Date<span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" id="order_date" name="order_date" value="{{ $dateValue }}" required
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('order_date') border-danger @enderror">
                        @error('order_date')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Expected Delivery Date --}}
                    <div>
                        <label for="expected_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Expected Delivery Date
                        </label>
                        <input type="date" id="expected_date" name="expected_date" value="{{ $expectedValue }}"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('expected_date') border-danger @enderror">
                        @error('expected_date')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tax / VAT Rate --}}
                    <div>
                        <label for="tax_rate" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Tax / VAT (%)
                        </label>
                        <div class="relative">
                            <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100"
                                   x-model.number="taxRate"
                                   value="{{ $taxRateValue }}"
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 pr-8 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('tax_rate') border-danger @enderror">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted">%</span>
                        </div>
                        @error('tax_rate')
                            <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Order Note --}}
                <div class="mt-5 pt-4 border-t border-line">
                    <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Internal Note / Instructions
                    </label>
                    <textarea id="note" name="note" rows="2"
                              placeholder="Add any specific instructions or delivery details for this order..."
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('note') border-danger @enderror">{{ old('note', $order->note) }}</textarea>
                    @error('note')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Card 2: Products & Order Items --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                
                {{-- Card Header & Product Search Bar --}}
                <div class="p-5 sm:p-6 border-b border-line bg-canvas/20 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                                <x-icon name="box" class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-ink">Products &amp; Line Items</h3>
                                <p class="text-xs text-muted">Search products by name or barcode to add them with current stock limits.</p>
                            </div>
                        </div>

                        {{-- Items counter pill --}}
                        <div class="inline-flex items-center gap-2 rounded-xl bg-surface border border-line px-3 py-1.5 text-xs shadow-2xs">
                            <span class="text-muted">Total items:</span>
                            <span class="font-bold text-ink tabular-nums" x-text="rows.length"></span>
                        </div>
                    </div>

                    {{-- Search Input with Dropdown --}}
                    <div class="relative">
                        <div class="relative">
                            <input type="text"
                                   x-ref="searchInput"
                                   x-model="searchQuery"
                                   @focus="searchUnits()"
                                   @input.debounce.250ms="searchUnits()"
                                   @keydown.enter.prevent="searchUnits()"
                                   @keydown.escape="results = []"
                                   placeholder="Search product or scan barcode..."
                                   :placeholder="locationId ? 'Search product or scan barcode...' : '⚠️ Please select a Business Location above first...'"
                                   :disabled="!locationId"
                                   class="w-full rounded-2xl border border-line bg-surface px-4 py-3 pl-11 pr-24 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 disabled:bg-soft disabled:cursor-not-allowed transition-all">
                            
                            {{-- Search / Spinner Icon --}}
                            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-muted pointer-events-none">
                                <template x-if="!isSearching">
                                    <x-icon name="search" class="size-4.5" />
                                </template>
                                <template x-if="isSearching">
                                    <svg class="animate-spin size-4.5 text-brand-500" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                            </div>

                            {{-- Right Action Buttons in Search --}}
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                                <button type="button" x-show="searchQuery.length > 0" @click="searchQuery = ''; results = []"
                                        class="p-1 rounded-lg text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer" title="Clear">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <span class="hidden sm:inline-flex items-center rounded-lg border border-line bg-soft px-2 py-0.5 text-[11px] font-semibold text-muted">
                                    Barcode / Text
                                </span>
                            </div>
                        </div>

                        {{-- Search Results Dropdown --}}
                        <div x-show="results.length > 0" x-cloak
                             @click.outside="results = []"
                             class="absolute z-30 mt-2 w-full max-h-80 overflow-y-auto rounded-2xl border border-line bg-surface p-2 shadow-2xl divide-y divide-line/60">
                            <template x-for="unit in results" :key="unit.id">
                                <div class="flex items-center justify-between gap-3 p-3 rounded-xl hover:bg-soft transition-colors"
                                     :class="{'opacity-50 cursor-not-allowed': unit.stock <= 0}">
                                    
                                    {{-- Left: Product info --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-bold text-ink" x-text="unit.display_name"></span>
                                            <template x-if="unit.barcode">
                                                <span class="inline-flex items-center rounded-md bg-soft px-1.5 py-0.5 text-[10px] font-semibold text-muted ring-1 ring-line" x-text="unit.barcode"></span>
                                            </template>
                                        </div>
                                        
                                        <div class="mt-1 flex items-center gap-2 flex-wrap text-xs">
                                            <span class="text-muted" x-text="'Unit: ' + unit.unit"></span>
                                            <span class="text-muted/60">•</span>
                                            
                                            {{-- Stock Badge in Dropdown --}}
                                            <template x-if="unit.stock > 5">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-bold text-success ring-1 ring-success/20">
                                                    <span class="size-1.5 rounded-full bg-success"></span>
                                                    <span x-text="'In Stock: ' + unit.stock + ' ' + unit.unit"></span>
                                                </span>
                                            </template>
                                            <template x-if="unit.stock > 0 && unit.stock <= 5">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-[11px] font-bold text-warning ring-1 ring-warning/20">
                                                    <span class="size-1.5 rounded-full bg-warning"></span>
                                                    <span x-text="'Low Stock: ' + unit.stock + ' ' + unit.unit"></span>
                                                </span>
                                            </template>
                                            <template x-if="unit.stock <= 0">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-bold text-danger ring-1 ring-danger/20">
                                                    <span class="size-1.5 rounded-full bg-danger"></span>
                                                    <span>Out of Stock (0)</span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Right: Price & Add button --}}
                                    <div class="flex items-center gap-3 shrink-0">
                                        <div class="text-right">
                                            <span class="text-xs text-muted block">Sale Price</span>
                                            <span class="text-sm font-extrabold text-ink tabular-nums" x-text="formatMoney(unit.sale_price)"></span>
                                        </div>
                                        
                                        <button type="button"
                                                @click="addRow(unit)"
                                                :disabled="unit.stock <= 0"
                                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold shadow-2xs transition-all cursor-pointer"
                                                :class="unit.stock > 0 ? 'bg-brand-500 hover:bg-brand-600 text-on-brand' : 'bg-soft text-muted cursor-not-allowed border border-line'">
                                            <x-icon name="plus" class="size-3.5" />
                                            <span x-text="unit.stock > 0 ? 'Add' : 'Unavailable'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Products Table --}}
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="border-b border-line bg-canvas/40 text-[11px] font-semibold uppercase tracking-wider text-muted">
                                <th class="py-3 px-4 w-10 text-center">#</th>
                                <th class="py-3 px-4">Product Details</th>
                                <th class="py-3 px-4 w-36 text-center">Stock Available</th>
                                <th class="py-3 px-4 w-40">Quantity</th>
                                <th class="py-3 px-4 w-32">Rate (৳)</th>
                                <th class="py-3 px-4 w-28">Discount (৳)</th>
                                <th class="py-3 px-4 text-right w-36">Subtotal</th>
                                <th class="py-3 px-4 w-14 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface">
                            <template x-for="(row, index) in rows" :key="row.unit_id">
                                <tr class="hover:bg-soft/40 transition-colors"
                                    :class="{'bg-danger/5': (parseFloat(row.quantity) || 0) > (parseFloat(row.stock) || 0)}">
                                    
                                    {{-- Row Index --}}
                                    <td class="py-3.5 px-4 text-xs font-semibold text-muted text-center tabular-nums" x-text="index + 1"></td>
                                    
                                    {{-- Product Name & Hidden Input --}}
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-sm text-ink" x-text="row.product_name"></div>
                                        <div class="text-[11px] text-muted mt-0.5" x-text="'Unit: ' + (row.unit || 'Pcs')"></div>
                                        <input type="hidden" :name="`items[${index}][unit_id]`" :value="row.unit_id">
                                    </td>

                                    {{-- Stock Available at this Location --}}
                                    <td class="py-3.5 px-4 text-center">
                                        <template x-if="typeof row.stock !== 'undefined'">
                                            <div>
                                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums"
                                                      :class="row.stock > 5 ? 'bg-success/10 text-success ring-1 ring-success/20' : (row.stock > 0 ? 'bg-warning/10 text-warning ring-1 ring-warning/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20')">
                                                    <span class="size-1.5 rounded-full" :class="row.stock > 5 ? 'bg-success' : (row.stock > 0 ? 'bg-warning' : 'bg-danger')"></span>
                                                    <span x-text="row.stock + ' ' + (row.unit || 'Pcs')"></span>
                                                </span>
                                            </div>
                                        </template>
                                        <template x-if="typeof row.stock === 'undefined'">
                                            <span class="text-xs text-muted">Checking...</span>
                                        </template>
                                    </td>

                                    {{-- Quantity with Stepper and Max Constraint --}}
                                    <td class="py-3.5 px-4">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1">
                                                {{-- Decrement (-) --}}
                                                <button type="button"
                                                        @click="decrementQty(row)"
                                                        :disabled="row.quantity <= 1"
                                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-line bg-soft text-ink hover:bg-surface disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                                    <span class="text-sm font-bold">−</span>
                                                </button>

                                                {{-- Input --}}
                                                <input type="number"
                                                       min="1"
                                                       :max="row.stock"
                                                       step="1"
                                                       :name="`items[${index}][quantity]`"
                                                       x-model.number="row.quantity"
                                                       @input="validateStock(row)"
                                                       class="w-16 text-center rounded-lg border px-2 py-1.5 text-sm font-bold text-ink tabular-nums shadow-2xs focus:outline-hidden transition-all"
                                                       :class="(parseFloat(row.quantity) || 0) > (parseFloat(row.stock) || 0) ? 'border-danger bg-danger/10 text-danger focus:ring-2 focus:ring-danger' : 'border-line bg-surface focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20'">

                                                {{-- Increment (+) --}}
                                                <button type="button"
                                                        @click="incrementQty(row)"
                                                        :disabled="row.quantity >= row.stock"
                                                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-line bg-soft text-ink hover:bg-surface disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
                                                        :title="row.quantity >= row.stock ? 'Max available stock reached' : 'Add 1'">
                                                    <span class="text-sm font-bold">+</span>
                                                </button>
                                            </div>

                                            {{-- Danger Stock Alert below input --}}
                                            <template x-if="(parseFloat(row.quantity) || 0) > (parseFloat(row.stock) || 0)">
                                                <div class="text-[11px] font-bold text-danger flex items-center gap-1">
                                                    <x-icon name="alert-triangle" class="size-3" />
                                                    <span x-text="'Max stock: ' + row.stock"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Unit Rate (Editable) --}}
                                    <td class="py-3.5 px-4">
                                        <div class="relative">
                                            <input type="number"
                                                   min="0"
                                                   step="0.01"
                                                   :name="`items[${index}][unit_price]`"
                                                   x-model.number="row.unit_price"
                                                   class="w-full rounded-xl border border-line bg-surface px-2.5 py-1.5 text-sm font-medium text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        </div>
                                    </td>

                                    {{-- Line Discount --}}
                                    <td class="py-3.5 px-4">
                                        <input type="number"
                                               min="0"
                                               step="0.01"
                                               :name="`items[${index}][discount]`"
                                               x-model.number="row.discount"
                                               class="w-full rounded-xl border border-line bg-surface px-2.5 py-1.5 text-sm font-medium text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    </td>

                                    {{-- Line Subtotal --}}
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="text-sm font-extrabold text-ink tabular-nums whitespace-nowrap"
                                             x-text="formatMoney(Math.max(0, ((parseFloat(row.quantity) || 0) * (parseFloat(row.unit_price) || 0)) - (parseFloat(row.discount) || 0)))"></div>
                                    </td>

                                    {{-- Remove Button --}}
                                    <td class="py-3.5 px-4 text-center">
                                        <button type="button"
                                                @click="rows.splice(index, 1)"
                                                class="p-1.5 rounded-xl text-muted hover:text-danger hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Remove item">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Empty Table State --}}
                <div x-show="rows.length === 0" x-cloak class="py-14 px-6 text-center bg-canvas/10">
                    <div class="max-w-sm mx-auto flex flex-col items-center">
                        <div class="flex size-16 items-center justify-center rounded-3xl bg-soft text-brand-500 ring-1 ring-line mb-4 shadow-2xs">
                            <x-icon name="cart" class="size-8" />
                        </div>
                        <h4 class="text-base font-bold text-ink">No products added yet</h4>
                        <p class="text-xs text-muted mt-1.5 leading-relaxed">
                            Search by product name, model or barcode above to add items. Only in-stock items can be ordered.
                        </p>
                    </div>
                </div>

                {{-- Stock Conflict Alert Banner --}}
                <div x-show="hasStockErrors" x-cloak class="p-4 border-t border-danger/30 bg-danger/10">
                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-danger/20 text-danger">
                            <x-icon name="alert-triangle" class="size-4.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h5 class="text-sm font-bold text-danger">Stock Limit Exceeded!</h5>
                            <p class="text-xs text-danger/90 mt-0.5">
                                One or more items in this order exceed the available stock at <span class="font-bold underline" x-text="locationName"></span>. 
                                Please reduce the requested quantities to the available limits before submitting.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Backend Server Validation Errors for Items --}}
                @if($errors->has('items') || $errors->has('items.*'))
                    <div class="px-5 sm:px-6 py-3 border-t border-danger/20 bg-danger/5 space-y-1">
                        @error('items')
                            <p class="text-xs font-semibold text-danger">{{ $message }}</p>
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

        {{-- Right Column (4 cols): Sticky Order Summary & Submit Panel --}}
        <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-6">
            
            {{-- Summary Card --}}
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card space-y-5">
                <div class="flex items-center gap-3 pb-4 border-b border-line">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="payments" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Order Summary</h3>
                        <p class="text-xs text-muted">Real-time total calculations</p>
                    </div>
                </div>

                {{-- Calculation rows --}}
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between text-muted">
                        <span>Total Items</span>
                        <span class="font-bold text-ink tabular-nums" x-text="rows.length + ' item(s)'"></span>
                    </div>

                    <div class="flex items-center justify-between text-muted">
                        <span>Total Units</span>
                        <span class="font-bold text-ink tabular-nums" x-text="totalUnits"></span>
                    </div>

                    <div class="flex items-center justify-between text-muted">
                        <span>Subtotal</span>
                        <span class="font-bold text-ink tabular-nums" x-text="formatMoney(subtotal)"></span>
                    </div>

                    <div class="flex items-center justify-between text-muted">
                        <span>Total Discount</span>
                        <span class="font-bold text-danger tabular-nums" x-text="'- ' + formatMoney(discountTotal)"></span>
                    </div>

                    <div class="flex items-center justify-between text-muted">
                        <span class="flex items-center gap-1.5">
                            <span>Tax / VAT</span>
                            <template x-if="taxRate > 0">
                                <span class="rounded-md bg-soft px-1.5 py-0.5 text-[10px] font-bold text-muted" x-text="taxRate + '%'"></span>
                            </template>
                        </span>
                        <span class="font-bold text-ink tabular-nums" x-text="'+ ' + formatMoney(taxAmount)"></span>
                    </div>

                    {{-- Grand Total --}}
                    <div class="pt-3 border-t border-line flex items-baseline justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted block">Grand Total</span>
                            <span class="text-[11px] text-muted">Inclusive of discounts &amp; tax</span>
                        </div>
                        <span class="text-2xl font-extrabold text-brand-500 tabular-nums" x-text="formatMoney(grandTotal)"></span>
                    </div>
                </div>

                {{-- Notice Box --}}
                <div class="rounded-2xl border border-line bg-canvas/30 p-3.5 text-xs text-muted space-y-1.5">
                    <div class="flex items-center gap-1.5 font-bold text-ink">
                        <x-icon name="alert-triangle" class="size-3.5 text-brand-500" />
                        <span>Fulfillment Policy</span>
                    </div>
                    <p class="leading-relaxed">
                        Sales orders start as <strong>Draft</strong>. Stock is issued and the settled invoice is generated once payment is confirmed.
                    </p>
                </div>

                {{-- Submit Actions --}}
                <div class="space-y-2.5 pt-2">
                    <button type="submit"
                            @click="validateBeforeSubmit($event)"
                            :disabled="rows.length === 0 || hasStockErrors"
                            class="btn-scale w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed px-6 py-3 text-sm font-bold text-on-brand shadow-sm transition-all cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>{{ $isEdit ? 'Save Changes' : 'Create Sales Order' }}</span>
                    </button>

                    <a href="{{ $cancelUrl ?? route('sales-orders.index') }}"
                       class="btn-scale w-full inline-flex items-center justify-center rounded-2xl border border-line bg-surface hover:bg-soft px-6 py-2.5 text-xs font-semibold text-ink transition-colors">
                        Cancel
                    </a>
                </div>

                {{-- Validation Warning Hint below button --}}
                <template x-if="hasStockErrors">
                    <p class="text-center text-xs font-bold text-danger animate-pulse">
                        ⚠️ Cannot submit: adjust quantities exceeding stock.
                    </p>
                </template>
                <template x-if="rows.length === 0">
                    <p class="text-center text-xs text-muted">
                        Add at least one product to enable order placement.
                    </p>
                </template>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="customer-contact-directory">@json($customerDirectory)</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.getElementById('customer_name');
    var idInput = document.getElementById('customer_id');
    var mobileInput = document.getElementById('mobile_no');
    var emailInput = document.getElementById('customer_email');
    var directoryBlock = document.getElementById('customer-contact-directory');

    if (!nameInput || !idInput || !mobileInput || !emailInput || !directoryBlock) {
        return;
    }

    var directory = [];
    try {
        directory = JSON.parse(directoryBlock.textContent) || [];
    } catch (error) {
        directory = [];
    }

    function normalizeName(value) {
        return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    function findCustomerByName(value) {
        var key = normalizeName(value);
        if (key === '') {
            return null;
        }

        for (var i = 0; i < directory.length; i++) {
            if (normalizeName(directory[i].name) === key) {
                return directory[i];
            }
        }

        return null;
    }

    var mobileAutofilled = false;

    function syncCustomerFields() {
        var customer = findCustomerByName(nameInput.value);

        if (customer) {
            idInput.value = String(customer.id);
            mobileInput.value = customer.mobile || customer.tel_no || '';
            emailInput.value = customer.email || '';
            mobileAutofilled = true;
            return;
        }

        idInput.value = '';

        if (mobileAutofilled) {
            mobileInput.value = '';
            emailInput.value = '';
            mobileAutofilled = false;
        }
    }

    nameInput.addEventListener('input', syncCustomerFields);
    nameInput.addEventListener('change', syncCustomerFields);
});
</script>

<script>
function orderForm(state) {
    return {
        rows: (state.rows || []).map(r => ({
            unit_id: r.unit_id,
            product_name: r.product_name,
            unit: r.unit || 'Pcs',
            stock: typeof r.stock !== 'undefined' ? parseFloat(r.stock) : (parseFloat(r.quantity) || 1),
            quantity: parseFloat(r.quantity) || 1,
            unit_price: parseFloat(r.unit_price) || 0,
            discount: parseFloat(r.discount) || 0
        })),
        taxRate: parseFloat(state.taxRate) || 0,
        locationId: state.initialLocationId || '',
        locationsMap: state.locationsMap || {},
        searchQuery: '',
        results: [],
        isSearching: false,

        init() {
            if (this.locationId) {
                this.syncStockForCurrentRows();
            }
        },

        get locationName() {
            return this.locationsMap[this.locationId] || 'Selected Location';
        },

        get totalUnits() {
            return this.rows.reduce((sum, r) => sum + (parseFloat(r.quantity) || 0), 0);
        },

        get subtotal() {
            return this.rows.reduce((sum, row) => {
                const q = parseFloat(row.quantity) || 0;
                const p = parseFloat(row.unit_price) || 0;
                const d = parseFloat(row.discount) || 0;
                return sum + Math.max(0, (q * p) - d);
            }, 0);
        },

        get discountTotal() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.discount) || 0), 0);
        },

        get taxAmount() {
            return (this.subtotal * (parseFloat(this.taxRate) || 0)) / 100;
        },

        get grandTotal() {
            return Math.max(0, this.subtotal + this.taxAmount);
        },

        get hasStockErrors() {
            return this.rows.some(r => (parseFloat(r.quantity) || 0) > (parseFloat(r.stock) || 0));
        },

        formatMoney(amount) {
            return '৳ ' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        onLocationChange(event) {
            this.syncStockForCurrentRows();
            this.searchUnits();
        },

        async searchUnits() {
            const query = this.searchQuery.trim();

            this.isSearching = true;
            try {
                const url = @js(route('sales-orders.search-units')) + '?search=' + encodeURIComponent(query) + (this.locationId ? '&location_id=' + encodeURIComponent(this.locationId) : '');
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.results = (data.units || []).map(unit => ({
                    id: unit.id,
                    display_name: unit.display_name,
                    unit: unit.unit || 'Pcs',
                    stock: parseFloat(unit.quantity) || 0,
                    sale_price: parseFloat(unit.sale_price) || 0,
                    barcode: unit.barcode || ''
                }));
            } catch (error) {
                this.results = [];
            } finally {
                this.isSearching = false;
            }
        },

        async syncStockForCurrentRows() {
            if (!this.locationId || this.rows.length === 0) return;

            try {
                const params = new URLSearchParams({ location_id: this.locationId });
                this.rows.forEach(row => params.append('unit_ids[]', row.unit_id));

                const res = await fetch(@js(route('sales-orders.search-units')) + '?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const stockByUnit = new Map((data.units || []).map(unit => [Number(unit.id), parseFloat(unit.quantity) || 0]));

                // A product that is not stocked at the newly selected location has none there.
                this.rows.forEach(row => {
                    row.stock = stockByUnit.get(Number(row.unit_id)) ?? 0;
                });
            } catch (e) {
                // Ignore sync errors gracefully
            }
        },

        addRow(unit) {
            const stock = parseFloat(unit.stock) || 0;
            if (stock <= 0) {
                alert(`"${unit.display_name}" is out of stock at ${this.locationName}. It cannot be added.`);
                return;
            }

            const existing = this.rows.find(row => row.unit_id === unit.id);
            if (existing) {
                if (existing.quantity < existing.stock) {
                    existing.quantity++;
                } else {
                    alert(`Cannot add more: "${existing.product_name}" has only ${existing.stock} ${existing.unit} available at ${this.locationName}.`);
                }
            } else {
                this.rows.push({
                    unit_id: unit.id,
                    product_name: unit.display_name,
                    unit: unit.unit,
                    stock: stock,
                    quantity: 1,
                    unit_price: parseFloat(unit.sale_price) || 0,
                    discount: 0
                });
            }

            this.results = [];
            this.searchQuery = '';
        },

        incrementQty(row) {
            if (row.quantity < row.stock) {
                row.quantity++;
            }
        },

        decrementQty(row) {
            if (row.quantity > 1) {
                row.quantity--;
            }
        },

        validateStock(row) {
            if (isNaN(row.quantity) || row.quantity < 1) {
                row.quantity = 1;
            }
        },

        validateBeforeSubmit(event) {
            if (!this.locationId) {
                event.preventDefault();
                alert('Please select a Business Location before submitting the order.');
                document.getElementById('location_id')?.focus();
                return false;
            }

            if (this.rows.length === 0) {
                event.preventDefault();
                alert('Please add at least one product to the order.');
                this.$refs.searchInput?.focus();
                return false;
            }

            if (this.hasStockErrors) {
                event.preventDefault();
                const overItems = this.rows.filter(r => (parseFloat(r.quantity) || 0) > (parseFloat(r.stock) || 0));
                const list = overItems.map(i => `• ${i.product_name} (Requested: ${i.quantity}, Available: ${i.stock})`).join('\n');
                alert(`Cannot place order! The following product(s) exceed available stock at ${this.locationName}:\n\n${list}\n\nPlease reduce the quantities to available limits.`);
                return false;
            }

            return true;
        }
    };
}
</script>
