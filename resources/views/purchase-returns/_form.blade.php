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

    {{-- Card 1: Return Details & Cascading Invoice Selector --}}
    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Return Authorization Details</h2>
                <p class="text-xs text-muted">Choose the supplier distributor and link the original purchase invoice.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Distributor<span class="text-danger">*</span>
                </label>
                <select id="supplier_id" name="supplier_id" required x-model="supplierId" @change="onSupplierChange()"
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('supplier_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="purchase_order_id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    <span>Invoice Reference<span class="text-danger">*</span></span>
                    <span title="Recent purchases of the selected distributor"
                          class="inline-flex size-4 items-center justify-center rounded-full bg-brand-500/15 text-brand-500 text-[10px] font-bold cursor-help">i</span>
                    <span x-show="loadingPurchases" x-cloak class="ml-auto size-3.5 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></span>
                </label>
                <select id="purchase_order_id" name="purchase_order_id" required x-model="orderId" @change="onPurchaseChange()"
                        :disabled="loadingPurchases"
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all disabled:opacity-60 @error('purchase_order_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    <template x-for="purchase in purchases" :key="purchase.id">
                        <option :value="purchase.id"
                                x-text="`${purchase.reference_no} · ${purchase.order_date}`"></option>
                    </template>
                </select>
                @error('purchase_order_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reference No
                </label>
                <input type="text" id="reference_no" name="reference_no"
                       value="{{ old('reference_no', $isEdit ? $purchaseReturn->reference_no : '') }}"
                       placeholder="Leave blank to auto-generate"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                @error('reference_no')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="return_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Date<span class="text-danger">*</span>
                </label>
                <input type="datetime-local" id="return_date" name="return_date" value="{{ $dateValue }}" required
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('return_date') border-danger @enderror">
                @error('return_date')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5 pt-5 border-t border-line">
            <div>
                <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reason for Return<span class="text-danger">*</span>
                </label>
                <select id="reason" name="reason" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reason') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($reasons as $reason)
                        <option value="{{ $reason }}" @selected($reasonValue === $reason)>{{ $reason }}</option>
                    @endforeach
                </select>
                @error('reason')
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
                @if($isEdit && $purchaseReturn->document)
                    <div class="mt-2 inline-flex items-center gap-2 rounded-xl bg-soft px-3 py-1.5 text-xs border border-line">
                        <x-svg.paper-clip class="size-3.5 text-brand-500" />
                        <span class="text-muted">Current:</span>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($purchaseReturn->document) }}" target="_blank"
                           class="font-semibold text-brand-500 hover:underline">View document</a>
                    </div>
                @endif
                <p class="mt-1.5 text-xs text-muted">Max 5MB · Allowed: PDF, CSV, ZIP, DOC, DOCX, JPEG, PNG</p>
                @error('document')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Card 2: Purchase Details Snapshot (Dynamically Loaded) --}}
    <div class="rounded-3xl border border-brand-500/20 bg-surface p-5 sm:p-6 shadow-card" x-show="purchase" x-cloak>
        <div class="flex items-center justify-between gap-3 pb-4 mb-4 border-b border-line">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="store" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-ink">Purchase Details</h3>
                    <p class="text-xs text-muted">Financial snapshot of the linked original purchase invoice</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                  x-text="purchase?.reference_no ?? '—'"></span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 text-sm">
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Invoice Reference</div>
                <div class="mt-1 font-bold text-ink tabular-nums truncate" x-text="purchase?.reference_no ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Purchase Date</div>
                <div class="mt-1 font-semibold text-ink tabular-nums" x-text="purchase?.order_date ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Business Location</div>
                <div class="mt-1 font-semibold text-ink truncate" x-text="purchase?.location ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Grand Total</div>
                <div class="mt-1 font-bold text-ink tabular-nums" x-text="formatMoney(purchase?.grand_total)"></div>
            </div>
            <div class="rounded-2xl bg-success/5 border border-success/20 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Amount Paid</div>
                <div class="mt-1 font-bold text-success tabular-nums" x-text="formatMoney(purchase?.paid_amount)"></div>
            </div>
            <div class="rounded-2xl bg-danger/5 border border-danger/20 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Amount Due</div>
                <div class="mt-1 font-bold text-danger tabular-nums" x-text="formatMoney(purchase?.payment_due)"></div>
            </div>
        </div>
    </div>

    {{-- Card 3: Products Brought In (Interactive Return Grid) --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-line bg-surface">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-ink">Products Brought In</h3>
                    <p class="text-xs text-muted">Enter the quantity to return for each eligible item (cannot exceed units purchased).</p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <span x-show="loadingDetails" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-medium text-muted border border-line">
                    <span class="size-3 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></span>
                    Loading items...
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums"
                      x-text="returningUnitsCount + (returningUnitsCount === 1 ? ' unit returning' : ' units returning')"></span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                      x-text="formatMoney(totalAmount)"></span>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5 w-12">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 text-center w-40">Units Purchased</th>
                        <th class="py-3.5 px-5 w-52">Return Quantity</th>
                        <th class="py-3.5 px-5 text-right w-36">Unit Price</th>
                        <th class="py-3.5 px-5 text-right w-40">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    <template x-for="(row, index) in rows" :key="row.unit_id">
                        <tr :class="(parseInt(row.return_qty, 10) || 0) > 0 ? 'bg-brand-500/5' : 'hover:bg-soft/40'" class="transition-colors">
                            <td class="py-3.5 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                            <td class="py-3.5 px-5">
                                <div class="text-sm font-semibold text-ink" x-text="row.product_name"></div>
                                <div class="mt-0.5 inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-[11px] font-medium text-muted border border-line"
                                     x-text="row.unit"></div>
                                <input type="hidden" :name="`items[${index}][unit_id]`" :value="row.unit_id">
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                <span class="inline-flex items-center rounded-full bg-soft px-3 py-1 text-xs font-bold text-ink border border-line tabular-nums"
                                      x-text="row.purchased + ' max'"></span>
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" :max="row.purchased" step="1"
                                           :name="`items[${index}][quantity]`"
                                           x-model.number="row.return_qty"
                                           class="w-24 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    <button type="button" @click="row.return_qty = row.purchased"
                                            class="rounded-lg border border-line bg-soft px-2 py-1 text-[11px] font-semibold text-muted hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer"
                                            title="Return all purchased units">
                                        Max
                                    </button>
                                </div>
                            </td>
                            <td class="py-3.5 px-5 text-right text-sm text-muted tabular-nums whitespace-nowrap" x-text="formatMoney(row.unit_price)"></td>
                            <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                x-text="formatMoney((parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0))"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Empty State --}}
        <div x-show="rows.length === 0" x-cloak class="py-12 px-6 text-center bg-canvas/20">
            <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                    <x-icon name="enquiries" class="size-7" />
                </div>
                <p class="text-sm font-bold text-ink">No invoice selected yet</p>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Select a distributor and invoice reference above to load the products brought in.
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

        {{-- Return Total & Action Footer --}}
        <div class="border-t border-line bg-canvas/30 px-5 sm:px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="rounded-2xl border border-brand-500/20 bg-surface px-4 py-3 shadow-2xs">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted">Total Return Amount</span>
                        <span class="block text-2xl font-extrabold text-brand-500 tabular-nums mt-0.5" x-text="formatMoney(totalAmount)"></span>
                    </div>
                    <p class="text-xs text-muted max-w-xs hidden md:block">
                        Stock and supplier return due credits update once an authorized manager approves this request.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <a href="{{ route('purchase-returns.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>{{ $isEdit ? 'Save Changes' : 'Apply Return' }}</span>
                    </button>
                </div>
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
        loadingPurchases: false,
        loadingDetails: false,

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        async onSupplierChange() {
            this.orderId = null;
            this.rows = [];
            this.purchase = null;
            this.purchases = [];

            if (! this.supplierId) {
                return;
            }

            this.loadingPurchases = true;

            try {
                const response = await fetch(@js(route('purchase-returns.supplier-purchases')) + '?supplier_id=' + encodeURIComponent(this.supplierId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.purchases = data.purchases || [];
            } catch (error) {
                this.purchases = [];
            } finally {
                this.loadingPurchases = false;
            }
        },

        async onPurchaseChange() {
            this.rows = [];
            this.purchase = null;

            if (! this.orderId) {
                return;
            }

            this.loadingDetails = true;

            try {
                const response = await fetch(@js(route('purchase-returns.purchase-details')) + '?purchase_order_id=' + encodeURIComponent(this.orderId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.purchase = data.purchase || null;
                this.rows = (data.items || []).map(item => ({ ...item, return_qty: 0 }));
            } catch (error) {
                this.rows = [];
            } finally {
                this.loadingDetails = false;
            }
        },

        fmt(value) {
            return Number(value || 0).toFixed(2);
        },

        get returningUnitsCount() {
            return this.rows.reduce((sum, row) => sum + (parseInt(row.return_qty, 10) || 0), 0);
        },

        get totalAmount() {
            return this.rows.reduce((sum, row) => {
                return sum + (parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0);
            }, 0);
        }
    };
}
</script>
