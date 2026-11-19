@php
    $isEdit = $order->exists;
    $dateValue = old('order_date', $isEdit ? $order->order_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $statusValue = old('purchase_status', $isEdit ? $order->purchase_status->value : \App\Enums\PurchaseStatus::Ordered->value);
    $taxRateValue = (float) old('tax_rate', $isEdit ? $order->tax_rate : 0);
    $paidValue = (float) old('paid_amount', $isEdit ? $order->paid_amount : 0);
@endphp

<div x-data="purchaseOrderForm({
        items: @js($items),
        taxRate: {{ $taxRateValue }},
        paidAmount: {{ $paidValue }}
    })" class="space-y-6">

    {{-- Validation Errors Alert --}}
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

    {{-- Card 1: Order Details --}}
    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Order Details</h2>
                <p class="text-xs text-muted">Assign the supplier, destination location, reference number, and order status.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Supplier<span class="text-danger">*</span>
                </label>
                <select id="supplier_id" name="supplier_id" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('supplier_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $isEdit ? $order->supplier_id : null) == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Business Location<span class="text-danger">*</span>
                </label>
                <select id="location_id" name="location_id" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('location_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $isEdit ? $order->location_id : null) == $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
                @error('location_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reference No
                </label>
                <input type="text" id="reference_no" name="reference_no"
                       value="{{ old('reference_no', $isEdit ? $order->reference_no : '') }}"
                       placeholder="Leave blank to auto-generate"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                @error('reference_no')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="order_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Purchase Date<span class="text-danger">*</span>
                </label>
                <input type="datetime-local" id="order_date" name="order_date" value="{{ $dateValue }}" required
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('order_date') border-danger @enderror">
                @error('order_date')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5 pt-5 border-t border-line">
            <div>
                <label for="purchase_status" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    <span>Purchase Status<span class="text-danger">*</span></span>
                    <span title="Line items are frozen once the order is received."
                          class="inline-flex size-4 items-center justify-center rounded-full bg-brand-500/15 text-brand-500 text-[10px] font-bold cursor-help">i</span>
                </label>
                @if($isEdit)
                    <select id="purchase_status" name="purchase_status" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('purchase_status') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-muted">
                        Stock and supplier payables follow this status: keeping "Received" holds the goods in stock, "Cancelled" reverses them.
                    </p>
                @else
                    <div class="flex items-center gap-2.5 rounded-xl border border-line bg-surface px-3.5 py-2.5">
                        <x-status-badge status="received" label="Received" />
                        <span class="text-xs text-muted">Fixed — purchases take effect immediately.</span>
                    </div>
                    <p class="mt-1.5 text-xs text-muted">
                        Stock is received and the supplier payable registers as soon as the purchase is saved.
                    </p>
                @endif
                @error('purchase_status')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="document" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Attach Document
                </label>
                <input type="file" id="document" name="document"
                       accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png"
                       class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-xs sm:text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-500 hover:file:bg-brand-500/15 shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('document') border-danger @enderror">
                @if($isEdit && $order->document)
                    <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-soft px-3 py-1.5 text-xs border border-line">
                        <x-svg.paper-clip class="size-3.5 text-brand-500" />
                        <span class="text-muted">Current:</span>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->document) }}" target="_blank"
                           class="font-semibold text-brand-500 hover:underline">View document</a>
                    </div>
                @endif
                <p class="mt-1.5 text-xs text-muted">
                    Max 5MB · Allowed: PDF, CSV, ZIP, DOC, DOCX, JPEG, PNG
                </p>
                @error('document')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Interactive Line Items Builder --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-line bg-surface">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-ink">Search Products</h2>
                    <p class="text-xs text-muted">
                        @if($itemsEditable)
                            Search inventory units to add line items and adjust order quantities or unit costs.
                        @else
                            Line items are locked because this purchase order has already been received.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                      x-text="items.length + (items.length === 1 ? ' line item' : ' line items')"></span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums"
                      x-text="totalQuantity + (totalQuantity === 1 ? ' unit' : ' units')"></span>
            </div>
        </div>

        @if($itemsEditable)
            {{-- Unit Autocomplete Search Bar --}}
            <div class="px-5 sm:px-6 py-4 bg-canvas/40 border-b border-line">
                <div class="relative max-w-2xl" @click.outside="searchResults = []">
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text"
                               x-model="searchQuery"
                               @input.debounce.250ms="searchProducts()"
                               @keydown.escape="searchResults = []"
                               placeholder="Search Products by name, SKU, or unit to add to this order..."
                               autocomplete="off"
                               class="w-full rounded-2xl border border-line bg-surface pl-10 pr-10 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                        <div x-show="searching" x-cloak class="absolute inset-y-0 right-0 pr-3.5 flex items-center">
                            <span class="size-4 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></span>
                        </div>
                        <button type="button"
                                x-show="!searching && searchQuery.length > 0" x-cloak
                                @click="searchQuery = ''; searchResults = []"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-muted hover:text-ink cursor-pointer"
                                title="Clear search">
                            <x-svg.x class="size-4" />
                        </button>
                    </div>

                    {{-- Autocomplete Dropdown --}}
                    <div x-show="searchResults.length > 0" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-98 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         class="absolute z-30 mt-2 w-full rounded-2xl border border-line bg-surface p-1.5 shadow-card max-h-72 overflow-y-auto custom-scrollbar">
                        <div class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted border-b border-line mb-1">
                            Matching Inventory Units
                        </div>
                        <template x-for="product in searchResults" :key="product.id">
                            <button type="button" @click="addProduct(product)"
                                    class="w-full flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-soft transition-colors cursor-pointer group">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors truncate"
                                         x-text="product.product_name"></div>
                                    <div class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                        <span class="inline-flex items-center rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-ink border border-line"
                                              x-text="product.unit"></span>
                                        <span x-text="'Stock: ' + product.quantity"></span>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-bold text-ink tabular-nums" x-text="formatMoney(product.purchase_price)"></div>
                                    <div class="text-[11px] font-semibold text-brand-500">+ Add item</div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- Line Items Table --}}
        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5 w-12">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 w-36">Quantity</th>
                        <th class="py-3.5 px-5 w-44">Unit Price (৳)</th>
                        <th class="py-3.5 px-5 text-right w-40">Subtotal</th>
                        @if($itemsEditable)
                            <th class="py-3.5 px-5 text-center w-16">
                                <span class="sr-only">Remove</span>
                                <x-svg.trash-bin class="size-4 inline-block text-muted" />
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @if($itemsEditable)
                        <template x-for="(item, index) in items" :key="item.unit_id + '-' + index">
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                                <td class="py-3.5 px-5">
                                    <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                    <div class="mt-0.5 inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-[11px] font-medium text-muted border border-line"
                                         x-text="item.unit"></div>
                                    <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                </td>
                                <td class="py-3.5 px-5">
                                    <input type="number" min="1" step="1"
                                           :name="`items[${index}][quantity]`"
                                           x-model.number="item.quantity"
                                           class="w-28 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                </td>
                                <td class="py-3.5 px-5">
                                    <div class="relative flex items-center">
                                        <span class="absolute left-3 text-xs font-bold text-muted pointer-events-none">৳</span>
                                        <input type="number" min="0" step="0.01"
                                               :name="`items[${index}][unit_price]`"
                                               x-model.number="item.unit_price"
                                               class="w-36 rounded-xl border border-line bg-surface pl-7 pr-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                    x-text="formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></td>
                                <td class="py-3.5 px-5 text-center">
                                    <button type="button" @click="removeItem(index)"
                                            class="inline-flex size-8 items-center justify-center rounded-xl border border-line bg-surface text-danger hover:bg-danger/10 hover:border-danger/30 transition-colors cursor-pointer"
                                            title="Remove line item">
                                        <x-svg.trash-bin class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    @else
                        <template x-for="(item, index) in items" :key="item.unit_id + '-' + index">
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                                <td class="py-3.5 px-5">
                                    <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                    <div class="mt-0.5 inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-[11px] font-medium text-muted border border-line"
                                         x-text="item.unit"></div>
                                </td>
                                <td class="py-3.5 px-5 text-sm font-semibold text-ink tabular-nums" x-text="item.quantity"></td>
                                <td class="py-3.5 px-5 text-sm font-medium text-ink tabular-nums" x-text="formatMoney(item.unit_price)"></td>
                                <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                    x-text="formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></td>
                            </tr>
                        </template>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Empty State --}}
        <div x-show="items.length === 0" x-cloak class="py-12 px-6 text-center bg-canvas/20">
            <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                    <x-icon name="cart" class="size-7" />
                </div>
                <p class="text-sm font-bold text-ink">No products added yet</p>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    @if($itemsEditable)
                        Use the product search bar above to find and add inventory units to this purchase order.
                    @else
                        No products on this purchase order.
                    @endif
                </p>
            </div>
        </div>

        {{-- Line Item Validation Errors --}}
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

    {{-- Card 3: Financial Summary & Payment --}}
    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                <x-icon name="wallet" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Financial Summary &amp; Payment</h2>
                <p class="text-xs text-muted">Configure applicable purchase tax and record initial or updated supplier payments.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Left: Tax & Payment Inputs --}}
            <div class="lg:col-span-7 space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="tax_rate" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Purchase Tax
                        </label>
                        <select id="tax_rate" name="tax_rate" x-model.number="taxRate"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                            <option value="0">None (0%)</option>
                            <option value="5">5%</option>
                            <option value="7.5">7.5%</option>
                            <option value="10">10%</option>
                            <option value="15">15%</option>
                        </select>
                        <p class="mt-1.5 text-xs text-muted">Applied automatically on the order subtotal.</p>
                    </div>

                    <div>
                        <label for="paid_amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Amount Paid
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-sm font-bold text-muted pointer-events-none">৳</span>
                            <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0"
                                   x-model.number="paidAmount"
                                   value="{{ $paidValue }}"
                                   class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('paid_amount') border-danger @enderror">
                        </div>
                        @error('paid_amount')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-muted">Leave at 0 if nothing has been paid yet.</p>
                    </div>
                </div>

                {{-- Quick Payment Fill Helpers --}}
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <span class="text-xs font-medium text-muted">Quick fill payment:</span>
                    <button type="button" @click="paidAmount = Number(grandTotal.toFixed(2))"
                            class="rounded-full border border-line bg-soft px-3 py-1 text-xs font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                        Pay Full
                    </button>
                    <button type="button" @click="paidAmount = Number((grandTotal / 2).toFixed(2))"
                            class="rounded-full border border-line bg-soft px-3 py-1 text-xs font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                        50% Deposit
                    </button>
                    <button type="button" @click="paidAmount = 0"
                            class="rounded-full border border-line bg-soft px-3 py-1 text-xs font-semibold text-muted hover:text-ink transition-colors cursor-pointer">
                        Clear (Due)
                    </button>
                </div>
            </div>

            {{-- Right: Live Grand Total Highlight Panel --}}
            <div class="lg:col-span-5 rounded-2xl border border-brand-500/20 bg-soft/60 p-5 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-muted">
                    <span>Order Summary</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                          :class="{
                              'bg-success/15 text-success': paymentDue === 0 && grandTotal > 0,
                              'bg-brand-500/15 text-brand-500': paidAmount > 0 && paymentDue > 0,
                              'bg-warning/15 text-warning': paidAmount <= 0 || grandTotal === 0
                          }"
                          x-text="grandTotal === 0 ? 'Awaiting Items' : (paymentDue === 0 ? 'Paid in Full' : (paidAmount > 0 ? 'Partial Payment' : 'Payment Due'))"></span>
                </div>

                <div class="space-y-2 pt-1 text-sm">
                    <div class="flex items-center justify-between text-muted">
                        <span>Subtotal</span>
                        <span class="font-semibold text-ink tabular-nums" x-text="formatMoney(itemsTotal)"></span>
                    </div>
                    <div class="flex items-center justify-between text-muted">
                        <span>Purchase Tax (<span x-text="taxRate"></span>%)</span>
                        <span class="font-semibold text-ink tabular-nums" x-text="formatMoney(taxAmount)"></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-line flex items-baseline justify-between gap-2">
                    <span class="text-sm font-bold text-ink">Grand Total</span>
                    <span class="text-2xl font-extrabold tracking-tight text-brand-500 tabular-nums" x-text="formatMoney(grandTotal)"></span>
                </div>

                <div class="pt-2 border-t border-line/70 space-y-1.5 text-sm">
                    <div class="flex items-center justify-between text-muted">
                        <span>Amount Paid</span>
                        <span class="font-semibold text-success tabular-nums" x-text="formatMoney(paidAmount)"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-ink">Payment Due</span>
                        <span class="font-bold tabular-nums"
                              :class="paymentDue > 0 ? 'text-danger' : 'text-success'"
                              x-text="formatMoney(paymentDue)"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Footer --}}
        <div class="mt-6 pt-5 border-t border-line flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-muted">
                @if($isEdit)
                    Saving updates the purchase invoice record and recalculates supplier balances where applicable.
                @else
                    New purchases take effect immediately: stock and supplier payables update as soon as you save.
                @endif
            </p>
            <div class="flex items-center justify-end gap-2.5">
                <a href="{{ route('purchase-invoices.index') }}"
                   class="btn-scale inline-flex items-center justify-center rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>{{ $isEdit ? 'Save Changes' : 'Save Purchase Order' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function purchaseOrderForm(state) {
    return {
        items: state.items || [],
        searchQuery: '',
        searchResults: [],
        searching: false,
        taxRate: parseFloat(state.taxRate) || 0,
        paidAmount: parseFloat(state.paidAmount) || 0,

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        async searchProducts() {
            const query = this.searchQuery.trim();

            if (query.length === 0) {
                this.searchResults = [];
                this.searching = false;
                return;
            }

            this.searching = true;

            try {
                const response = await fetch(@js(route('purchase-orders.search-units')) + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.searchResults = data.items || [];
            } catch (error) {
                this.searchResults = [];
            } finally {
                this.searching = false;
            }
        },

        addProduct(product) {
            const existing = this.items.find(item => item.unit_id === product.id);

            if (existing) {
                existing.quantity = (parseInt(existing.quantity, 10) || 0) + 1;
            } else {
                this.items.push({
                    unit_id: product.id,
                    product_name: product.product_name,
                    unit: product.unit,
                    quantity: 1,
                    unit_price: parseFloat(product.purchase_price) || 0
                });
            }

            this.searchQuery = '';
            this.searchResults = [];
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        get totalQuantity() {
            return this.items.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
        },

        get itemsTotal() {
            return this.items.reduce((sum, item) => {
                return sum + (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
            }, 0);
        },

        get taxAmount() {
            return this.itemsTotal * (parseFloat(this.taxRate) || 0) / 100;
        },

        get grandTotal() {
            return this.itemsTotal + this.taxAmount;
        },

        get paymentDue() {
            return Math.max(0, this.grandTotal - (parseFloat(this.paidAmount) || 0));
        }
    };
}
</script>
