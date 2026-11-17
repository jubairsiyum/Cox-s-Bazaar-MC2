@php
    $isEdit = $stockTransfer->exists;
    $dateValue = old('transfer_date', $isEdit ? $stockTransfer->transfer_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $statusValue = old('status', $isEdit ? $stockTransfer->status->value : '');
@endphp

<div x-data="transferForm({
        items: @js($items),
        shipping: {{ (float) old('shipping_charges', $isEdit ? $stockTransfer->shipping_charges : 0) }},
        editable: {{ $itemsEditable ? 'true' : 'false' }}
    })">

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

    {{-- Transfer Details --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="transfer_date" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Date<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <input type="datetime-local" id="transfer_date" name="transfer_date" value="{{ $dateValue }}" required
                           class="form-control @error('transfer_date') is-invalid @enderror">
                    @error('transfer_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="reference_no" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Reference No:
                    </label>
                    <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $isEdit ? $stockTransfer->reference_no : '') }}"
                           class="form-control @error('reference_no') is-invalid @enderror"
                           placeholder="Leave blank to auto-generate">
                    @error('reference_no')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Status<span class="text-red-500 font-bold">*</span>
                        <span title="Stock leaves the source location when the transfer is dispatched (In Transit) and arrives at the destination once Received."
                              class="inline-flex items-center justify-center size-4 rounded-full bg-blue-500 text-white text-[10px] font-bold cursor-help align-middle">i</span>:
                    </label>
                    <select id="status" name="status" required class="form-control @error('status') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="from_location_id" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Location (From)<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <select id="from_location_id" name="from_location_id" required class="form-control @error('from_location_id') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('from_location_id', $isEdit ? $stockTransfer->from_location_id : null) == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('from_location_id')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="to_location_id" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Location (To)<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <select id="to_location_id" name="to_location_id" required class="form-control @error('to_location_id') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($destinationLocations as $location)
                            <option value="{{ $location->id }}" @selected(old('to_location_id', $isEdit ? $stockTransfer->to_location_id : null) == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('to_location_id')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Products --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            @if($itemsEditable)
                {{-- Product Search --}}
                <div class="relative max-w-2xl mx-auto mb-4">
                    <div class="flex">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-500">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                            </svg>
                        </span>
                        <input type="text" x-model="searchQuery" @input.debounce.300ms="searchProducts()"
                               placeholder="Search products to transfer..."
                               class="form-control rounded-l-none">
                    </div>
                    <div x-show="searchResults.length" x-cloak
                         class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-auto">
                        <template x-for="product in searchResults" :key="product.id">
                            <button type="button" @click="addProduct(product)"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-2 text-sm text-left text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                                <span class="font-medium" x-text="product.product_name"></span>
                                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                      x-text="`${product.unit} · Stock: ${product.quantity}`"></span>
                            </button>
                        </template>
                    </div>
                </div>
            @endif

            {{-- Line Items Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md">
                <table class="data-table w-full text-left">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Product</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Quantity</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Unit Price</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Subtotal</th>
                            @if($itemsEditable)
                                <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100 text-center">
                                    <svg class="size-4 inline-block text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @if($itemsEditable)
                            <template x-for="(item, index) in items" :key="index">
                                <tr>
                                    <td class="p-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.product_name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="item.unit"></div>
                                        <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                    </td>
                                    <td class="p-3">
                                        <input type="number" min="1" step="1" :name="`items[${index}][quantity]`" x-model="item.quantity"
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
                        @else
                            <template x-for="(item, index) in items" :key="index">
                                <tr>
                                    <td class="p-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.product_name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-text="item.unit"></div>
                                    </td>
                                    <td class="p-3 text-sm text-gray-900 dark:text-gray-100" x-text="item.quantity"></td>
                                    <td class="p-3 text-sm text-gray-900 dark:text-gray-100" x-text="Number(item.unit_price).toFixed(2)"></td>
                                    <td class="p-3 text-sm text-gray-900 dark:text-gray-100"
                                        x-text="(item.quantity * item.unit_price).toFixed(2)"></td>
                                </tr>
                            </template>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="bg-white dark:bg-gray-900">
                            <td colspan="3"></td>
                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                Total: <span x-text="itemsTotal.toFixed(2)"></span>
                            </td>
                            @if($itemsEditable)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p x-show="items.length === 0" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                @if($itemsEditable)
                    No products added yet. Use the search box above to add products to this transfer.
                @else
                    No products on this transfer.
                @endif
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

    {{-- Shipping & Notes --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-body p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="shipping_charges" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Shipping Charges:
                    </label>
                    <input type="number" id="shipping_charges" name="shipping_charges" step="0.01" min="0"
                           x-model="shippingCharges"
                           value="{{ old('shipping_charges', $isEdit ? $stockTransfer->shipping_charges : 0) }}"
                           class="form-control @error('shipping_charges') is-invalid @enderror">
                    @error('shipping_charges')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="additional_notes" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Additional Notes
                    </label>
                    <textarea id="additional_notes" name="additional_notes" rows="3"
                              class="form-control @error('additional_notes') is-invalid @enderror"
                              placeholder="Notes (optional)">{{ old('additional_notes', $isEdit ? $stockTransfer->additional_notes : '') }}</textarea>
                    @error('additional_notes')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-6 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                Total Amount: <span x-text="totalAmount.toFixed(2)"></span>
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
                <a href="{{ route('stock-transfers.index') }}"
                    class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                    style="background-color: #f39c12;">
                    Close
                </a>
            </div>
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
        shippingCharges: parseFloat(state.shipping) || 0,

        async searchProducts() {
            const query = this.searchQuery.trim();

            if (query.length === 0) {
                this.searchResults = [];
                return;
            }

            try {
                const response = await fetch(@js(route('stock-transfers.search-units')) + '?q=' + encodeURIComponent(query), {
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
