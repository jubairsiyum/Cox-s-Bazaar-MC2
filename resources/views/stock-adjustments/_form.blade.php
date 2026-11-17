@php
    $isEdit = $stockAdjustment->exists;
    $dateValue = old('adjustment_date', $isEdit ? $stockAdjustment->adjustment_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $typeValue = old('type', $isEdit ? $stockAdjustment->type->value : '');
@endphp

<div x-data="adjustmentForm({ items: @js($items), type: @js($typeValue) })">

    @if ($errors->any())
        <div class="card mb-4 border-t-4 border-t-red-500 shadow-sm">
            <div class="card-body p-4">
                <p class="text-sm font-semibold text-red-600 dark:text-red-400 mb-2">Please Enter Valid Data</p>
                <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Adjustment Details --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="adjustment_date" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Date<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <input type="datetime-local" id="adjustment_date" name="adjustment_date" value="{{ $dateValue }}" required
                           class="form-control @error('adjustment_date') is-invalid @enderror">
                    @error('adjustment_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="reference_no" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Reference No:
                    </label>
                    <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $isEdit ? $stockAdjustment->reference_no : '') }}"
                           class="form-control @error('reference_no') is-invalid @enderror"
                           placeholder="Leave blank to auto-generate">
                    @error('reference_no')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Adjustment type<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <select id="type" name="type" required x-model="type" class="form-control @error('type') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected($typeValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400" x-text="typeHints[type] || 'Choose how stock should change: add, remove, or set the counted quantity.'"></p>
                    @error('type')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Products --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            {{-- Product Search --}}
            <div class="relative max-w-2xl mx-auto mb-4">
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-500">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input type="text" x-model="searchQuery" @input.debounce.300ms="searchProducts()"
                           placeholder="Search products to adjust"
                           class="form-control rounded-l-none">
                </div>
                <div x-show="searchResults.length" x-cloak
                     class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-auto">
                    <template x-for="product in searchResults" :key="product.id">
                        <button type="button" @click="addProduct(product)"
                                class="w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-left text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                            <span class="font-medium" x-text="product.product_name"></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                  x-text="`${product.unit} · In stock: ${product.quantity}`"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Line Items Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md">
                <table class="data-table w-full text-left">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Product</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">
                                Quantity
                                <span class="block text-[11px] font-normal text-gray-500 dark:text-gray-400"
                                      x-text="type === 'set' ? 'counted stock' : (type === 'remove' ? 'to remove' : 'to add')"></span>
                            </th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Unit Price</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Subtotal</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100 text-center">
                                <svg class="size-4 inline-block text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="p-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.product_name"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="item.unit"></span>
                                        <span> · In stock: <span x-text="item.stock"></span></span>
                                    </div>
                                    <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                </td>
                                <td class="p-3">
                                    <input type="number" :min="type === 'set' ? 0 : 1" step="1"
                                           :name="`items[${index}][quantity]`" x-model="item.quantity"
                                           class="form-control w-24">
                                </td>
                                <td class="p-3">
                                    <input type="number" min="0" step="0.01" :name="`items[${index}][unit_price]`" x-model="item.unit_price"
                                           class="form-control w-32">
                                </td>
                                <td class="p-3 text-sm text-gray-900 dark:text-gray-100"
                                    x-text="(item.quantity * item.unit_price).toFixed(2)"></td>
                                <td class="p-3 text-center">
                                    <button type="button" @click="removeItem(index)"
                                            class="text-red-500 hover:text-red-700 transition cursor-pointer">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-white dark:bg-gray-900">
                            <td colspan="3"></td>
                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                Total Amount: <span x-text="itemsTotal.toFixed(2)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p x-show="items.length === 0" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                No products added yet. Use the search box above to add products to this adjustment.
            </p>

            @error('items')
                <div class="form-error mt-2">{{ $message }}</div>
            @enderror
            @foreach ($errors->get('items.*') as $messages)
                @foreach ($messages as $message)
                    <div class="form-error mt-1">{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- Reason --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-body p-5">
            <div>
                <label for="reason" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                    Reason<span class="text-red-500 font-bold">*</span>:
                </label>
                <textarea id="reason" name="reason" rows="3"
                          class="form-control @error('reason') is-invalid @enderror"
                          placeholder="e.g. damaged in flood, stock take correction, found in back store">{{ old('reason', $isEdit ? $stockAdjustment->reason : '') }}</textarea>
                @error('reason')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Action Buttons --}}
            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-center items-center gap-4">
                <button type="submit"
                    class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                    style="background-color: #00a65a;">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save
                </button>
                <a href="{{ route('stock-adjustments.index') }}"
                    class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                    style="background-color: #f39c12;">
                    Close
                </a>
            </div>
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
        typeHints: @js($typeHints),

        async searchProducts() {
            const query = this.searchQuery.trim();

            if (query.length === 0) {
                this.searchResults = [];
                return;
            }

            try {
                const response = await fetch(@js(route('stock-adjustments.search-units')) + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.searchResults = data.items || [];
            } catch (error) {
                this.searchResults = [];
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
