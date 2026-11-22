@php
    $isEdit = $stockTransfer->exists;
    $dateValue = old('transfer_date', $isEdit ? $stockTransfer->transfer_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $statusValue = old('status', $isEdit ? $stockTransfer->status->value : '');
@endphp

<div x-data="transferForm({
        items: @js($items),
        shipping: {{ (float) old('shipping_charges', $isEdit ? $stockTransfer->shipping_charges : 0) }},
        editable: {{ $itemsEditable ? 'true' : 'false' }}
    })" class="space-y-6">

    {{-- Validation Summary --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 flex items-start gap-3">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-danger/15 text-danger">
                <x-icon name="alert-triangle" class="size-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-danger">Please Enter Valid Data</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5 text-xs text-ink/90">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Card 1: Transfer Route & Metadata --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-6">
        <div class="flex items-center gap-3 pb-4 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="truck" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Transfer Route & Details</h2>
                <p class="text-xs text-muted mt-0.5">Stock leaves the origin when dispatched (In Transit) and arrives once approved by the receiving location.</p>
            </div>
        </div>

        {{-- Visual From -> To Location Selector --}}
        <div class="grid grid-cols-1 md:grid-cols-11 gap-4 items-center rounded-2xl border border-line bg-canvas/40 p-4 sm:p-5">
            <div class="md:col-span-5">
                <label for="from_location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Location (From) <span class="text-danger">*</span>
                </label>
                <select id="from_location_id" name="from_location_id" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('from_location_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('from_location_id', $isEdit ? $stockTransfer->from_location_id : null) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('from_location_id')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-1 flex items-center justify-center pt-2 md:pt-5">
                <div class="flex size-9 items-center justify-center rounded-full bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 rotate-90 md:rotate-0">
                    <x-svg.arrow-right class="size-4" />
                </div>
            </div>

            <div class="md:col-span-5">
                <label for="to_location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Location (To) <span class="text-danger">*</span>
                </label>
                <select id="to_location_id" name="to_location_id" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('to_location_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($destinationLocations as $location)
                        <option value="{{ $location->id }}" @selected(old('to_location_id', $isEdit ? $stockTransfer->to_location_id : null) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('to_location_id')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Date, Reference, Status --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="transfer_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Date <span class="text-danger">*</span>
                </label>
                <input type="datetime-local" id="transfer_date" name="transfer_date" value="{{ $dateValue }}" required
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('transfer_date') border-danger @enderror">
                @error('transfer_date')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reference No
                </label>
                <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $isEdit ? $stockTransfer->reference_no : '') }}"
                       placeholder="Leave blank to auto-generate"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                @error('reference_no')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="status" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    <span>Status <span class="text-danger">*</span></span>
                    <span title="Stock leaves the source location when the transfer is dispatched (In Transit) and arrives at the destination once Received."
                          class="inline-flex items-center justify-center size-4 rounded-full bg-brand-500/15 text-brand-500 text-[10px] font-bold cursor-help">i</span>
                </label>
                <select id="status" name="status" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('status') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Products & Line Items --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Products & Line Items</h2>
                    <p class="text-xs text-muted mt-0.5">
                        @if($itemsEditable)
                            Search products by name, code, or barcode to include in this transfer.
                        @else
                            Line items are frozen once stock has been dispatched from the source location.
                        @endif
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums self-start sm:self-auto">
                <span x-text="items.length"></span>
                <span x-text="items.length === 1 ? 'line item' : 'line items'"></span>
            </span>
        </div>

        @if($itemsEditable)
            {{-- Interactive Product Search --}}
            <div class="relative max-w-2xl mx-auto mb-5" @click.outside="searchResults = []">
                <div class="relative flex items-center">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-muted">
                        <x-icon name="search" class="size-4" />
                    </span>
                    <input type="text"
                           x-model="searchQuery"
                           @input.debounce.300ms="searchProducts()"
                           @keydown.escape="searchResults = []"
                           placeholder="Search products to transfer..."
                           class="w-full rounded-2xl border border-line bg-canvas/50 pl-11 pr-10 py-3 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:bg-surface focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                    <span x-show="searching" x-cloak class="absolute inset-y-0 right-0 pr-3.5 flex items-center">
                        <span class="size-4 rounded-full border-2 border-brand-500 border-t-transparent animate-spin"></span>
                    </span>
                </div>

                {{-- Search Results Dropdown --}}
                <div x-show="searchResults.length > 0" x-cloak
                     class="absolute z-30 mt-2 w-full rounded-2xl border border-line bg-surface shadow-card max-h-64 overflow-auto p-1.5">
                    <template x-for="product in searchResults" :key="product.id">
                        <button type="button" @click="addProduct(product)"
                                class="w-full flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 text-left hover:bg-soft transition-colors cursor-pointer">
                            <div class="min-w-0">
                                <span class="block text-sm font-semibold text-ink truncate" x-text="product.product_name"></span>
                                <span class="block text-[11px] text-muted" x-text="`Unit: ${product.unit} · Cost: ৳${formatMoney(product.purchase_price)}`"></span>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums"
                                  :class="product.quantity > 0 ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20'"
                                  x-text="`Stock: ${product.quantity}`"></span>
                        </button>
                    </template>
                </div>
            </div>
        @endif

        {{-- Line Items Table --}}
        <div class="rounded-2xl border border-line overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3.5 px-4">Product</th>
                            <th class="py-3.5 px-4">Quantity</th>
                            <th class="py-3.5 px-4">Unit Price (৳)</th>
                            <th class="py-3.5 px-4 text-end">Subtotal</th>
                            @if($itemsEditable)
                                <th class="py-3.5 px-4 text-center w-14">
                                    <span class="sr-only">Remove</span>
                                    <x-svg.trash-bin class="size-4 inline-block text-muted" />
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @if($itemsEditable)
                            <template x-for="(item, index) in items" :key="`${item.unit_id}-${index}`">
                                <tr class="hover:bg-soft/30 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                            <span class="rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-ink border border-line" x-text="item.unit || 'Unit'"></span>
                                            <template x-if="item.stock !== undefined && item.stock !== null">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums"
                                                      :class="(parseInt(item.quantity, 10) || 0) > item.stock ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'"
                                                      x-text="`Available: ${item.stock}`"></span>
                                            </template>
                                        </div>
                                        <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <input type="number" min="1" step="1"
                                               :max="item.stock ?? null"
                                               :name="`items[${index}][quantity]`"
                                               x-model="item.quantity"
                                               :class="item.error ? 'border-danger' : 'border-line'"
                                               class="w-28 rounded-xl border bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                        <p x-show="item.error" x-cloak x-text="item.error" class="mt-1 max-w-xs text-xs font-medium text-danger"></p>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <input type="number" min="0" step="0.01"
                                               :name="`items[${index}][unit_price]`"
                                               x-model="item.unit_price"
                                               class="w-36 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    </td>
                                    <td class="py-3.5 px-4 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap"
                                        x-text="'৳' + formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button type="button" @click="removeItem(index)"
                                                class="inline-flex size-8 items-center justify-center rounded-xl text-danger hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Remove row">
                                            <x-svg.trash-bin class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        @else
                            <template x-for="(item, index) in items" :key="`${item.unit_id}-${index}`">
                                <tr class="hover:bg-soft/30 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                        <span class="mt-0.5 inline-block rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-muted border border-line" x-text="item.unit || 'Unit'"></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-sm font-bold text-ink tabular-nums" x-text="item.quantity"></td>
                                    <td class="py-3.5 px-4 text-sm text-muted tabular-nums money" x-text="'৳' + formatMoney(item.unit_price)"></td>
                                    <td class="py-3.5 px-4 text-end text-sm font-bold text-ink tabular-nums money"
                                        x-text="'৳' + formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></td>
                                </tr>
                            </template>
                        @endif

                        <tr x-show="items.length === 0">
                            <td colspan="{{ $itemsEditable ? 5 : 4 }}" class="py-10 px-6 text-center text-xs text-muted">
                                @if($itemsEditable)
                                    No products added yet. Use the search box above to add products to this transfer.
                                @else
                                    No products on this transfer.
                                @endif
                            </td>
                        </tr>
                    </tbody>
                    <tfoot x-show="items.length > 0">
                        <tr class="border-t border-line bg-soft/60">
                            <td colspan="3" class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-muted text-end">
                                Items Subtotal
                            </td>
                            <td class="py-3.5 px-4 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap"
                                x-text="'৳' + formatMoney(itemsTotal)"></td>
                            @if($itemsEditable)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @error('items')
            <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
        @enderror
        @foreach ($errors->get('items.*') as $messages)
            @foreach ($messages as $message)
                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
            @endforeach
        @endforeach
    </div>

    {{-- Card 3: Shipping, Notes & Grand Total --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="shipping_charges" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Shipping Charges (৳)
                    </label>
                    <input type="number" id="shipping_charges" name="shipping_charges" step="0.01" min="0"
                           x-model="shippingCharges"
                           value="{{ old('shipping_charges', $isEdit ? $stockTransfer->shipping_charges : 0) }}"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('shipping_charges') border-danger @enderror">
                    @error('shipping_charges')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="additional_notes" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Additional Notes
                    </label>
                    <textarea id="additional_notes" name="additional_notes" rows="3"
                              placeholder="Delivery instructions, courier details, or handling notes (optional)"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('additional_notes') border-danger @enderror">{{ old('additional_notes', $isEdit ? $stockTransfer->additional_notes : '') }}</textarea>
                    @error('additional_notes')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Live Cost Summary Box --}}
            <div class="lg:col-span-5 rounded-2xl border border-line bg-soft/60 p-5 space-y-3 text-sm">
                <div class="flex items-center justify-between text-muted">
                    <span>Items Subtotal</span>
                    <span class="font-semibold text-ink tabular-nums money" x-text="'৳' + formatMoney(itemsTotal)"></span>
                </div>
                <div class="flex items-center justify-between text-muted">
                    <span>Shipping Charges</span>
                    <span class="font-semibold text-ink tabular-nums money" x-text="'৳' + formatMoney(shippingCharges)"></span>
                </div>
                <div class="pt-3 border-t border-line flex items-center justify-between">
                    <span class="font-bold text-ink">Total Amount</span>
                    <span class="text-xl font-extrabold text-brand-500 tabular-nums money" x-text="'৳' + formatMoney(totalAmount)"></span>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="mt-6 pt-5 border-t border-line flex items-center justify-end gap-3">
            <a href="{{ route('stock-transfers.index') }}"
               class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <x-icon name="check" class="size-4" />
                <span>{{ $isEdit ? 'Update Transfer' : 'Save Transfer' }}</span>
            </button>
        </div>
    </div>
</div>

<script>
function transferForm(state) {
    return {
        items: state.items || [],
        editable: state.editable !== false,
        searchQuery: '',
        searchResults: [],
        searching: false,
        shippingCharges: parseFloat(state.shipping) || 0,

        formatMoney(val) {
            const num = parseFloat(val) || 0;
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        async searchProducts() {
            const query = this.searchQuery.trim();

            if (query.length === 0) {
                this.searchResults = [];
                return;
            }

            this.searching = true;
            try {
                const response = await fetch(@js(route('stock-transfers.search-units')) + '?q=' + encodeURIComponent(query), {
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
                    stock: product.quantity,
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

        get itemsTotal() {
            return this.items.reduce((sum, item) => {
                return sum + (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
            }, 0);
        },

        get totalAmount() {
            return this.itemsTotal + (parseFloat(this.shippingCharges) || 0);
        }
    };
}
</script>
