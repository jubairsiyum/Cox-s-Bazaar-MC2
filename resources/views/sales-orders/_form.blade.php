@php
    $isEdit = $order->exists;
    $dateValue = old('order_date', $isEdit ? $order->order_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $expectedValue = old('expected_date', $isEdit ? ($order->expected_date?->format('Y-m-d') ?? '') : '');
    $taxRateValue = old('tax_rate', $isEdit ? $order->tax_rate : 0);
@endphp

<div x-data="orderForm({
        rows: @js($rows),
        taxRate: @js((float) $taxRateValue)
    })" class="space-y-6">

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
                <p class="text-xs text-muted">Customer, expected delivery and business location of this order.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label for="customer_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Customer<span class="text-danger">*</span>
                </label>
                <select id="customer_id" name="customer_id" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('customer_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $order->customer_id) === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="order_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Order Date<span class="text-danger">*</span>
                </label>
                <input type="datetime-local" id="order_date" name="order_date" value="{{ $dateValue }}" required
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('order_date') border-danger @enderror">
                @error('order_date')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="expected_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Expected Date
                </label>
                <input type="date" id="expected_date" name="expected_date" value="{{ $expectedValue }}"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('expected_date') border-danger @enderror">
                @error('expected_date')
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
                        <option value="{{ $location->id }}" @selected((int) old('location_id', $defaultLocationId ?? $order->location_id) === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('location_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5 pt-5 border-t border-line">
            <div>
                <label for="tax_rate" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Tax / VAT (%)
                </label>
                <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100"
                       x-model.number="taxRate"
                       value="{{ $taxRateValue }}"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('tax_rate') border-danger @enderror">
                @error('tax_rate')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Note
                </label>
                <textarea id="note" name="note" rows="2"
                          class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('note') border-danger @enderror">{{ old('note', $order->note) }}</textarea>
                @error('note')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Products --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-line">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-ink">Products</h3>
                    <p class="text-xs text-muted">Search by product name or barcode, then set quantity, rate and discount.</p>
                </div>
            </div>

            <div class="relative w-full sm:w-80">
                <input type="text" x-model="searchQuery" @input.debounce.250ms="searchUnits()"
                       @keydown.enter.prevent="searchUnits()"
                       placeholder="Search product or scan barcode..."
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 pl-9 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                <x-icon name="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <div x-show="results.length > 0" x-cloak @click.outside="results = []"
                     class="absolute z-20 mt-1 w-full max-h-64 overflow-y-auto rounded-xl border border-line bg-surface shadow-card">
                    <template x-for="unit in results" :key="unit.id">
                        <button type="button" @click="addRow(unit)"
                                class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left hover:bg-soft transition-colors cursor-pointer">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-ink truncate" x-text="unit.display_name"></div>
                                <div class="text-[11px] text-muted" x-text="unit.unit + ' · Stock: ' + unit.quantity"></div>
                            </div>
                            <span class="text-xs font-bold text-brand-500 tabular-nums" x-text="formatMoney(unit.sale_price)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5 w-12">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 w-32">Quantity</th>
                        <th class="py-3.5 px-5 w-36">Rate</th>
                        <th class="py-3.5 px-5 w-36">Discount</th>
                        <th class="py-3.5 px-5 text-right w-36">Subtotal</th>
                        <th class="py-3.5 px-5 w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
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
                                       class="w-24 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            </td>
                            <td class="py-3 px-5">
                                <input type="number" min="0" step="0.01" :name="`items[${index}][unit_price]`"
                                       x-model.number="row.unit_price"
                                       class="w-28 rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            </td>
                            <td class="py-3 px-5">
                                <input type="number" min="0" step="0.01" :name="`items[${index}][discount]`"
                                       x-model.number="row.discount"
                                       class="w-28 rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            </td>
                            <td class="py-3 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                x-text="formatMoney(Math.max(0, (row.quantity || 0) * (row.unit_price || 0) - (row.discount || 0)))"></td>
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
                <p class="text-sm font-bold text-ink">No products added yet</p>
                <p class="text-xs text-muted mt-1 leading-relaxed">Use the product search above to build the order.</p>
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

        <div class="border-t border-line bg-canvas/30 px-5 sm:px-6 py-5">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 text-sm">
                    <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Subtotal</div>
                        <div class="mt-1 font-bold text-ink tabular-nums" x-text="formatMoney(subtotal)"></div>
                    </div>
                    <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Discount</div>
                        <div class="mt-1 font-bold text-ink tabular-nums" x-text="formatMoney(discountTotal)"></div>
                    </div>
                    <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Tax</div>
                        <div class="mt-1 font-bold text-ink tabular-nums" x-text="formatMoney(taxAmount)"></div>
                    </div>
                    <div class="rounded-2xl bg-brand-500/10 border border-brand-500/20 p-3.5">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-brand-500">Grand Total</div>
                        <div class="mt-1 text-xl font-extrabold text-brand-500 tabular-nums" x-text="formatMoney(grandTotal)"></div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <a href="{{ $cancelUrl }}"
                       class="btn-scale inline-flex items-center justify-center rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>{{ $isEdit ? 'Save Changes' : 'Create Order' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function orderForm(state) {
    return {
        rows: state.rows || [],
        taxRate: parseFloat(state.taxRate) || 0,
        searchQuery: '',
        results: [],

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
                const response = await fetch(@js(route('sales-orders.search-units')) + '?search=' + encodeURIComponent(this.searchQuery), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.results = (data.units || []).filter(unit => !this.rows.some(row => row.unit_id === unit.id));
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

        get subtotal() {
            return this.rows.reduce((sum, row) => sum + Math.max(0, (row.quantity || 0) * (row.unit_price || 0) - (row.discount || 0)), 0);
        },

        get discountTotal() {
            return this.rows.reduce((sum, row) => sum + (parseFloat(row.discount) || 0), 0);
        },

        get taxAmount() {
            return this.subtotal * (parseFloat(this.taxRate) || 0) / 100;
        },

        get grandTotal() {
            return this.subtotal + this.taxAmount;
        }
    };
}
</script>
