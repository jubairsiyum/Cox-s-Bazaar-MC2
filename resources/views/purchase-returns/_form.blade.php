@php
    $isEdit = $purchaseReturn->exists;
    $dateValue = old('return_date', $isEdit ? $purchaseReturn->return_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $reasonValue = old('reason', $isEdit ? $purchaseReturn->reason : '');
    $reasons = [
        'Defective or Damaged',
        'Wrong Product Delivered',
        'Quality Issues',
        'Overstock / Unwanted',
        'Expired Stock',
        'Other',
    ];
    $purchaseInfo = $parentOrder ? [
        'id' => $parentOrder->id,
        'reference_no' => $parentOrder->reference_no,
        'order_date' => $parentOrder->order_date->format('d/m/Y H:i'),
        'location' => $parentOrder->location?->name ?? '—',
        'grand_total' => (float) $parentOrder->grand_total,
        'paid_amount' => (float) $parentOrder->paid_amount,
        'payment_due' => (float) $parentOrder->payment_due,
    ] : null;
@endphp

<div x-data="purchaseReturnForm({
        supplierId: @js((int) old('supplier_id', $isEdit ? $purchaseReturn->supplier_id : ($parentOrder?->supplier_id ?? 0)) ?: null),
        orderId: @js((int) old('purchase_order_id', $isEdit ? $purchaseReturn->purchase_order_id : ($parentOrder?->id ?? 0)) ?: null),
        purchases: @js($purchases),
        rows: @js($rows),
        purchase: @js($purchaseInfo)
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

    {{-- Return Details --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="supplier_id" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Distributor<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <select id="supplier_id" name="supplier_id" required x-model="supplierId" @change="onSupplierChange()"
                            class="form-control @error('supplier_id') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="purchase_order_id" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Invoice Reference<span class="text-red-500 font-bold">*</span>
                        <span title="Recent purchases of the selected distributor"
                              class="inline-flex items-center justify-center size-4 rounded-full bg-blue-500 text-white text-[10px] font-bold cursor-help align-middle">i</span>:
                    </label>
                    <select id="purchase_order_id" name="purchase_order_id" required x-model="orderId" @change="onPurchaseChange()"
                            class="form-control @error('purchase_order_id') is-invalid @enderror">
                        <option value="">Please Select</option>
                        <template x-for="purchase in purchases" :key="purchase.id">
                            <option :value="purchase.id"
                                    x-text="`${purchase.reference_no} · ${purchase.order_date}`"></option>
                        </template>
                    </select>
                    @error('purchase_order_id')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="reference_no" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Reference No:
                    </label>
                    <input type="text" id="reference_no" name="reference_no" value="{{ old('reference_no', $isEdit ? $purchaseReturn->reference_no : '') }}"
                           class="form-control @error('reference_no') is-invalid @enderror"
                           placeholder="Leave blank to auto-generate">
                    @error('reference_no')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="return_date" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Date<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <input type="datetime-local" id="return_date" name="return_date" value="{{ $dateValue }}" required
                           class="form-control @error('return_date') is-invalid @enderror">
                    @error('return_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="reason" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Reason for Return<span class="text-red-500 font-bold">*</span>:
                    </label>
                    <select id="reason" name="reason" required class="form-control @error('reason') is-invalid @enderror">
                        <option value="">Please Select</option>
                        @foreach($reasons as $reason)
                            <option value="{{ $reason }}" @selected($reasonValue === $reason)>{{ $reason }}</option>
                        @endforeach
                    </select>
                    @error('reason')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="document" class="block text-sm font-bold text-gray-800 dark:text-gray-200 mb-1.5">
                        Attach Document:
                    </label>
                    <input type="file" id="document" name="document"
                           class="form-control @error('document') is-invalid @enderror"
                           accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png">
                    @if($isEdit && $purchaseReturn->document)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Current: <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($purchaseReturn->document) }}" target="_blank" class="text-indigo-600 hover:underline">View document</a>
                        </p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Max File size: 5MB<br>Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png</p>
                    @error('document')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Purchase Details (loaded dynamically) --}}
    <div class="card border-t-4 border-t-indigo-500 shadow-sm mb-4" x-show="purchase" x-cloak>
        <div class="card-body p-5">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Purchase Details</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Invoice Reference</div>
                    <div class="font-medium text-gray-900 dark:text-gray-100" x-text="purchase?.reference_no ?? '—'"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Purchase Date</div>
                    <div class="font-medium text-gray-900 dark:text-gray-100" x-text="purchase?.order_date ?? '—'"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Business Location</div>
                    <div class="font-medium text-gray-900 dark:text-gray-100" x-text="purchase?.location ?? '—'"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Grand Total</div>
                    <div class="font-medium text-gray-900 dark:text-gray-100" x-text="fmt(purchase?.grand_total)"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Amount Paid</div>
                    <div class="font-bold text-green-600 dark:text-green-400" x-text="fmt(purchase?.paid_amount)"></div>
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Amount Due</div>
                    <div class="font-bold text-red-500 dark:text-red-400" x-text="fmt(purchase?.payment_due)"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Products Brought In --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm mb-4">
        <div class="card-body p-5">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">Products Brought In</h3>

            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md">
                <table class="data-table w-full text-left">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Product</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Units Purchased</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Return Quantity</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Unit Price</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        <template x-for="(row, index) in rows" :key="row.unit_id">
                            <tr>
                                <td class="p-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="row.product_name"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="row.unit"></div>
                                    <input type="hidden" :name="`items[${index}][unit_id]`" :value="row.unit_id">
                                </td>
                                <td class="p-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-200"
                                          x-text="row.purchased"></span>
                                </td>
                                <td class="p-3">
                                    <input type="number" min="0" :max="row.purchased" step="1"
                                           :name="`items[${index}][quantity]`" x-model="row.return_qty"
                                           class="form-control w-24">
                                </td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300" x-text="fmt(row.unit_price)"></td>
                                <td class="p-3 text-sm text-gray-900 dark:text-gray-100"
                                    x-text="((parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0)).toFixed(2)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p x-show="rows.length === 0" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                Select a distributor and invoice reference above to load the products brought in.
            </p>

            @error('items')
                <div class="form-error mt-2">{{ $message }}</div>
            @enderror
            @foreach ($errors->get('items.*') as $messages)
                @foreach ($messages as $message)
                    <div class="form-error mt-1">{{ $message }}</div>
                @endforeach
            @endforeach

            <div class="mt-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                Total Amount: <span x-text="totalAmount.toFixed(2)"></span>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-body p-5">
            <div class="flex justify-center items-center gap-4">
                <button type="submit"
                    class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                    style="background-color: #00a65a;">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $isEdit ? 'Save' : 'Apply Return' }}
                </button>
                <a href="{{ route('purchase-returns.index') }}"
                    class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                    style="background-color: #f39c12;">
                    Close
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function purchaseReturnForm(state) {
    return {
        supplierId: state.supplierId || null,
        orderId: state.orderId || null,
        purchases: state.purchases || [],
        rows: state.rows || [],
        purchase: state.purchase || null,

        async onSupplierChange() {
            this.orderId = null;
            this.rows = [];
            this.purchase = null;
            this.purchases = [];

            if (! this.supplierId) {
                return;
            }

            try {
                const response = await fetch(@js(route('purchase-returns.supplier-purchases')) + '?supplier_id=' + encodeURIComponent(this.supplierId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.purchases = data.purchases || [];
            } catch (error) {
                this.purchases = [];
            }
        },

        async onPurchaseChange() {
            this.rows = [];
            this.purchase = null;

            if (! this.orderId) {
                return;
            }

            try {
                const response = await fetch(@js(route('purchase-returns.purchase-details')) + '?purchase_order_id=' + encodeURIComponent(this.orderId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.purchase = data.purchase || null;
                this.rows = (data.items || []).map(item => ({ ...item, return_qty: 0 }));
            } catch (error) {
                this.rows = [];
            }
        },

        fmt(value) {
            return Number(value || 0).toFixed(2);
        },

        get totalAmount() {
            return this.rows.reduce((sum, row) => {
                return sum + (parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0);
            }, 0);
        }
    };
}
</script>
