@php
    $isEdit = $stockAdjustment->exists;
    $dateValue = old('adjustment_date', $isEdit ? $stockAdjustment->adjustment_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $typeValue = old('type', $isEdit ? $stockAdjustment->type->value : '');
@endphp

<div x-data="adjustmentForm({ items: @js($items), type: @js($typeValue) })" class="space-y-6">

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

    {{-- Card 1: Adjustment Metadata --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Adjustment Details</h2>
                <p class="text-xs text-muted mt-0.5">Specify date, optional reference number, and how stock quantities should change.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label for="adjustment_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Date <span class="text-danger">*</span>
                </label>
                <input type="datetime-local" id="adjustment_date" name="adjustment_date" value="{{ $dateValue }}" required
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('adjustment_date') border-danger @enderror">
                @error('adjustment_date')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reference No
                </label>
                <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $isEdit ? $stockAdjustment->reference_no : '') }}"
                       placeholder="Leave blank to auto-generate"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                @error('reference_no')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="type" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Adjustment type <span class="text-danger">*</span>
                </label>
                <select id="type" name="type" required x-model="type"
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('type') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected($typeValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-muted leading-snug"
                   x-text="typeHints[type] || 'Choose how stock should change: add, remove, or set the counted quantity.'"></p>
                @error('type')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Product Search & Line Items Builder --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Products & Line Items</h2>
                    <p class="text-xs text-muted mt-0.5">Search products by name, code, or barcode to add to this adjustment.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums self-start sm:self-auto">
                <span x-text="items.length"></span>
                <span x-text="items.length === 1 ? 'line item' : 'line items'"></span>
            </span>
        </div>

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
                       placeholder="Search products to adjust"
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
                              x-text="`In stock: ${product.quantity}`"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Line Items Table --}}
        <div class="rounded-2xl border border-line overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3.5 px-4">Product</th>
                            <th class="py-3.5 px-4">
                                <span>Quantity</span>
                                <span class="block text-[10px] font-medium normal-case text-brand-500 mt-0.5"
                                      x-text="type === 'set' ? 'counted stock' : (type === 'remove' ? 'units to remove' : 'units to add')"></span>
                            </th>
                            <th class="py-3.5 px-4">Unit Price (৳)</th>
                            <th class="py-3.5 px-4 text-end">Subtotal</th>
                            <th class="py-3.5 px-4 text-center w-14">
                                <span class="sr-only">Remove</span>
                                <x-svg.trash-bin class="size-4 inline-block text-muted" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        <template x-for="(item, index) in items" :key="`${item.unit_id}-${index}`">
                            <tr class="hover:bg-soft/30 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                    <div class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                        <span class="rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-ink border border-line" x-text="item.unit || 'Unit'"></span>
                                        <span x-show="item.stock !== undefined && item.stock !== null"
                                              class="tabular-nums"
                                              x-text="`Current stock: ${item.stock}`"></span>
                                    </div>
                                    <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                </td>
                                <td class="py-3.5 px-4">
                                    <input type="number"
                                           :min="type === 'set' ? 0 : 1"
                                           step="1"
                                           :name="`items[${index}][quantity]`"
                                           x-model="item.quantity"
                                           class="w-28 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                </td>
                                <td class="py-3.5 px-4">
                                    <input type="number"
                                           min="0"
                                           step="0.01"
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
                        <tr x-show="items.length === 0">
                            <td colspan="5" class="py-10 px-6 text-center text-xs text-muted">
                                No products added yet. Use the search box above to add products to this adjustment.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot x-show="items.length > 0">
                        <tr class="border-t border-line bg-soft/60">
                            <td colspan="3" class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-muted text-end">
                                Total Adjusted Amount
                            </td>
                            <td class="py-3.5 px-4 text-end text-base font-extrabold text-brand-500 tabular-nums money whitespace-nowrap"
                                x-text="'৳' + formatMoney(itemsTotal)"></td>
                            <td></td>
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

    {{-- Card 3: Reason & Submission --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
        <div>
            <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                Reason <span class="text-danger">*</span>
            </label>
            <textarea id="reason" name="reason" rows="3" required
                      placeholder="e.g. damaged in flood, stock take correction, found in back store"
                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('reason') border-danger @enderror">{{ old('reason', $isEdit ? $stockAdjustment->reason : '') }}</textarea>
            @error('reason')
                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6 pt-5 border-t border-line flex items-center justify-end gap-3">
            <a href="{{ route('stock-adjustments.index') }}"
               class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <x-icon name="check" class="size-4" />
                <span>{{ $isEdit ? 'Update Adjustment' : 'Save Adjustment' }}</span>
            </button>
        </div>
    </div>
</div>

<script>
function adjustmentForm(state) {
    return {
        items: state.items || [],
        type: state.type || '',
        searchQuery: '',
        searchResults: [],
        searching: false,
        typeHints: @js($typeHints),

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
                const response = await fetch(@js(route('stock-adjustments.search-units')) + '?q=' + encodeURIComponent(query), {
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
        }
    };
}
</script>
