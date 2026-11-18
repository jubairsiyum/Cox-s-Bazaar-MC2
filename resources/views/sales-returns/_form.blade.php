@php
    $isEdit = $salesReturn->exists;
    $dateValue = old('return_date', $isEdit ? $salesReturn->return_date->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
    $reasonValue = old('reason', $isEdit ? $salesReturn->reason : '');
    $reasons = [
        'Defective or Damaged',
        'Wrong Product Delivered',
        'Quality Issues',
        'Customer Changed Mind',
        'Overstock / Unwanted',
        'Other',
    ];
    $invoiceInfo = $parentInvoice ? [
        'id' => $parentInvoice->id,
        'invoice_no' => $parentInvoice->invoice_no,
        'invoice_date' => $parentInvoice->invoice_date->format('d/m/Y H:i'),
        'location' => $parentInvoice->location?->name ?? '—',
        'grand_total' => (float) $parentInvoice->grand_total,
        'paid_amount' => (float) $parentInvoice->paid_amount,
        'payment_due' => (float) $parentInvoice->payment_due,
    ] : null;
@endphp

<div x-data="salesReturnForm({
        customerId: @js((int) old('customer_id', $isEdit ? $salesReturn->customer_id : ($parentInvoice?->customer_id ?? 0)) ?: null),
        invoiceId: @js((int) old('sales_invoice_id', $isEdit ? $salesReturn->sales_invoice_id : ($parentInvoice?->id ?? 0)) ?: null),
        invoices: @js($invoices),
        rows: @js($rows),
        invoice: @js($invoiceInfo)
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

    {{-- Card 1: Return Details & Cascading Invoice Selector --}}
    <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
        <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="enquiries" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Return Authorization Details</h2>
                <p class="text-xs text-muted">Choose the customer and link the original sales invoice.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label for="customer_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Customer<span class="text-danger">*</span>
                </label>
                <select id="customer_id" name="customer_id" required x-model="customerId" @change="onCustomerChange()"
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('customer_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')
                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="sales_invoice_id" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    <span>Invoice Reference<span class="text-danger">*</span></span>
                    <span title="Recent posted invoices of the selected customer"
                          class="inline-flex size-4 items-center justify-center rounded-full bg-brand-500/15 text-brand-500 text-[10px] font-bold cursor-help">i</span>
                    <span x-show="loadingInvoices" x-cloak class="ml-auto size-3.5 rounded-full border-2 border-brand-500/30 border-t-brand-500 animate-spin"></span>
                </label>
                <select id="sales_invoice_id" name="sales_invoice_id" required x-model="invoiceId" @change="onInvoiceChange()"
                        :disabled="loadingInvoices"
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all disabled:opacity-60 @error('sales_invoice_id') border-danger @enderror">
                    <option value="">Please Select</option>
                    <template x-for="invoiceItem in invoices" :key="invoiceItem.id">
                        <option :value="invoiceItem.id"
                                x-text="`${invoiceItem.invoice_no} · ${invoiceItem.invoice_date}`"></option>
                    </template>
                </select>
                @error('sales_invoice_id')
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

            <div>
                <label for="reason" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Reason for Return
                </label>
                <select id="reason" name="reason"
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
        </div>
    </div>

    {{-- Card 2: Invoice Snapshot (Dynamically Loaded) --}}
    <div class="rounded-3xl border border-brand-500/20 bg-surface p-5 sm:p-6 shadow-card" x-show="invoice" x-cloak>
        <div class="flex items-center justify-between gap-3 pb-4 mb-4 border-b border-line">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="store" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-ink">Invoice Details</h3>
                    <p class="text-xs text-muted">Financial snapshot of the linked sales invoice</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                  x-text="invoice?.invoice_no ?? '—'"></span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 text-sm">
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Invoice No</div>
                <div class="mt-1 font-bold text-ink tabular-nums truncate" x-text="invoice?.invoice_no ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Invoice Date</div>
                <div class="mt-1 font-semibold text-ink tabular-nums" x-text="invoice?.invoice_date ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Business Location</div>
                <div class="mt-1 font-semibold text-ink truncate" x-text="invoice?.location ?? '—'"></div>
            </div>
            <div class="rounded-2xl bg-soft/60 border border-line/70 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Grand Total</div>
                <div class="mt-1 font-bold text-ink tabular-nums" x-text="formatMoney(invoice?.grand_total)"></div>
            </div>
            <div class="rounded-2xl bg-success/5 border border-success/20 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Amount Paid</div>
                <div class="mt-1 font-bold text-success tabular-nums" x-text="formatMoney(invoice?.paid_amount)"></div>
            </div>
            <div class="rounded-2xl bg-danger/5 border border-danger/20 p-3.5">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-muted">Amount Due</div>
                <div class="mt-1 font-bold text-danger tabular-nums" x-text="formatMoney(invoice?.payment_due)"></div>
            </div>
        </div>
    </div>

    {{-- Card 3: Returned Products (Interactive Grid) --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-line bg-surface">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-ink">Products Returned</h3>
                    <p class="text-xs text-muted">Enter the quantity to return per product (≤ invoiced), its condition and whether to restock.</p>
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
                      x-text="formatMoney(refundTotal)"></span>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5 w-12">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 text-center w-32">Invoiced</th>
                        <th class="py-3.5 px-5 w-44">Qty to Return</th>
                        <th class="py-3.5 px-5 w-32">Condition</th>
                        <th class="py-3.5 px-5 text-center w-28">Restock</th>
                        <th class="py-3.5 px-5 w-48">Reason</th>
                        <th class="py-3.5 px-5 text-right w-32">Unit Price</th>
                        <th class="py-3.5 px-5 text-right w-36">Refund</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    <template x-for="(row, index) in rows" :key="row.unit_id">
                        <tr :class="(parseInt(row.return_qty, 10) || 0) > 0 ? 'bg-brand-500/5' : 'hover:bg-soft/40'" class="transition-colors">
                            <td class="py-3.5 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                            <td class="py-3.5 px-5">
                                <div class="text-sm font-semibold text-ink" x-text="row.product_name"></div>
                                <input type="hidden" :name="`items[${index}][unit_id]`" :value="row.unit_id">
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                <span class="inline-flex items-center rounded-full bg-soft px-3 py-1 text-xs font-bold text-ink border border-line tabular-nums"
                                      x-text="row.invoiced + ' max'"></span>
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" :max="row.invoiced" step="1"
                                           :name="`items[${index}][quantity]`"
                                           x-model.number="row.return_qty"
                                           class="w-24 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    <button type="button" @click="row.return_qty = row.invoiced"
                                            class="rounded-lg border border-line bg-soft px-2 py-1 text-[11px] font-semibold text-muted hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer"
                                            title="Return all invoiced units">
                                        Max
                                    </button>
                                </div>
                            </td>
                            <td class="py-3.5 px-5">
                                <select :name="`items[${index}][condition]`" x-model="row.condition"
                                        class="w-full rounded-xl border border-line bg-surface px-2.5 py-2 text-xs text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                </select>
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                <input type="hidden" :name="`items[${index}][restock]`" :value="row.restock ? 1 : 0">
                                <input type="checkbox" x-model="row.restock"
                                       class="size-4 rounded border-line text-brand-500 focus:ring-brand-500/20 cursor-pointer">
                            </td>
                            <td class="py-3.5 px-5">
                                <input type="text" :name="`items[${index}][reason]`" x-model="row.reason" maxlength="255"
                                       placeholder="Optional"
                                       class="w-full rounded-xl border border-line bg-surface px-2.5 py-2 text-xs text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            </td>
                            <td class="py-3.5 px-5 text-right text-sm text-muted tabular-nums whitespace-nowrap" x-text="formatMoney(row.unit_price)"></td>
                            <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums whitespace-nowrap"
                                x-text="formatMoney((parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0))"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="rows.length === 0" x-cloak class="py-12 px-6 text-center bg-canvas/20">
            <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                    <x-icon name="enquiries" class="size-7" />
                </div>
                <p class="text-sm font-bold text-ink">No invoice selected yet</p>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Select a customer and invoice reference above to load the purchased products.
                </p>
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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="rounded-2xl border border-brand-500/20 bg-surface px-4 py-3 shadow-2xs">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted">Total Refund / Credit</span>
                        <span class="block text-2xl font-extrabold text-brand-500 tabular-nums mt-0.5" x-text="formatMoney(refundTotal)"></span>
                    </div>
                    <p class="text-xs text-muted max-w-xs hidden md:block">
                        The invoice is credited and restockable lines return to stock once the branch manager approves this request.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <a href="{{ $cancelUrl }}"
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
function salesReturnForm(state) {
    return {
        customerId: state.customerId || null,
        invoiceId: state.invoiceId || null,
        invoices: state.invoices || [],
        rows: state.rows || [],
        invoice: state.invoice || null,
        loadingInvoices: false,
        loadingDetails: false,

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        async onCustomerChange() {
            this.invoiceId = null;
            this.rows = [];
            this.invoice = null;
            this.invoices = [];

            if (! this.customerId) {
                return;
            }

            this.loadingInvoices = true;

            try {
                const response = await fetch(@js(route('sales-returns.customer-invoices')) + '?customer_id=' + encodeURIComponent(this.customerId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.invoices = data.invoices || [];
            } catch (error) {
                this.invoices = [];
            } finally {
                this.loadingInvoices = false;
            }
        },

        async onInvoiceChange() {
            this.rows = [];
            this.invoice = null;

            if (! this.invoiceId) {
                return;
            }

            this.loadingDetails = true;

            try {
                const response = await fetch(@js(route('sales-returns.invoice-details')) + '?sales_invoice_id=' + encodeURIComponent(this.invoiceId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.invoice = data.invoice || null;
                this.rows = (data.items || []).map(item => ({ ...item, return_qty: 0, condition: 'good', restock: true, reason: '' }));
            } catch (error) {
                this.rows = [];
            } finally {
                this.loadingDetails = false;
            }
        },

        get returningUnitsCount() {
            return this.rows.reduce((sum, row) => sum + (parseInt(row.return_qty, 10) || 0), 0);
        },

        get refundTotal() {
            return this.rows.reduce((sum, row) => {
                return sum + (parseFloat(row.return_qty) || 0) * (parseFloat(row.unit_price) || 0);
            }, 0);
        }
    };
}
</script>
