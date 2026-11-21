@extends('layouts.app')

@section('title', 'Record Collection — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Collections')

@section('content')
<div class="space-y-6" x-data="collectionForm({
        customerId: @js((int) old('customer_id', request('customer')) ?: null),
        invoices: @js($invoices),
        allocations: @js(collect(old('allocations', []))->map(fn ($row) => ['sales_invoice_id' => (int) ($row['sales_invoice_id'] ?? 0), 'amount' => (float) ($row['amount'] ?? 0)])->values()->all())
    })">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Sales</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('customer-collections.index') }}" class="hover:text-brand-500 transition-colors">Collections</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Record Payment</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Record Customer Payment</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    Auto Receipt No (COL-)
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Select a customer, allocate the payment across open invoices and print the receipt.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('customer-collections.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Collections</span>
            </a>
        </div>
    </div>

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

    <form method="POST" action="{{ route('customer-collections.store') }}">
        @csrf

        {{-- Card 1: Payment Details --}}
        <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
            <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="wallet" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-ink">Payment Details</h2>
                    <p class="text-xs text-muted">Who paid, how much and through which channel.</p>
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
                    <label for="collection_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Collection Date<span class="text-danger">*</span>
                    </label>
                    <input type="datetime-local" id="collection_date" name="collection_date"
                           value="{{ old('collection_date', now()->format('Y-m-d\TH:i')) }}" required
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('collection_date') border-danger @enderror">
                    @error('collection_date')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="method" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Payment Method<span class="text-danger">*</span>
                    </label>
                    <select id="method" name="method" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('method') border-danger @enderror">
                        @foreach(\App\Enums\PaymentMethod::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('method')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $isCustCollAdmin = auth()->user()?->hasRole('Super Admin') ?? false;
                    $userAssignedLocId = (int) (auth()->user()?->branch_id ?? auth()->user()?->location_id ?? $defaultLocationId ?? 0);
                    if (!$isCustCollAdmin && !$userAssignedLocId) {
                        $userAssignedLocId = (int) (\App\Models\Location::where('status', 'active')
                            ->where('type', '!=', \App\Enums\LocationType::Warehouse->value ?? 'warehouse')
                            ->value('id') ?? \App\Models\Location::where('status', 'active')->value('id') ?? 1);
                    }
                @endphp

                <div>
                    <label for="location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Business Location<span class="text-danger">*</span>
                    </label>
                    @if (!$isCustCollAdmin && $userAssignedLocId)
                        <div class="w-full rounded-xl border border-line bg-soft/50 px-3.5 py-2.5 text-sm text-ink shadow-2xs flex items-center gap-2">
                            <x-icon name="store" class="size-4 text-brand-500" />
                            <span class="font-semibold">{{ $locations->firstWhere('id', $userAssignedLocId)?->name ?? 'Assigned Outlet' }}</span>
                        </div>
                        <input type="hidden" id="location_id" name="location_id" value="{{ $userAssignedLocId }}">
                    @else
                        <select id="location_id" name="location_id" required
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('location_id') border-danger @enderror">
                            <option value="">Please Select</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) old('location_id', $defaultLocationId ?? null) === $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('location_id')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-5 pt-5 border-t border-line">
                <div>
                    <label for="amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Amount<span class="text-danger">*</span>
                    </label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required
                           x-model.number="amount"
                           value="{{ old('amount') }}"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('amount') border-danger @enderror">
                    <p class="mt-1.5 text-xs text-muted">Must equal the total allocated below (<span class="font-semibold tabular-nums" x-text="formatMoney(allocatedTotal)"></span>).</p>
                    @error('amount')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="reference_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Reference No (Cheque / TrxID)
                    </label>
                    <input type="text" id="reference_no" name="reference_no" maxlength="100"
                           value="{{ old('reference_no') }}"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('reference_no') border-danger @enderror">
                    @error('reference_no')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Note
                    </label>
                    <textarea id="note" name="note" rows="2"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('note') border-danger @enderror">{{ old('note') }}</textarea>
                    @error('note')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Card 2: Invoice Allocations --}}
        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-5 sm:px-6 py-4 sm:py-5 border-b border-line">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                        <x-icon name="enquiries" class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink">Allocate to Open Invoices</h3>
                        <p class="text-xs text-muted">Pick the invoices this payment settles. Partial allocations are fine.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums"
                      x-text="'Allocated: ' + formatMoney(allocatedTotal)"></span>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3.5 px-5 w-12">#</th>
                            <th class="py-3.5 px-5">Invoice</th>
                            <th class="py-3.5 px-5 text-right w-32">Total</th>
                            <th class="py-3.5 px-5 text-right w-32">Due</th>
                            <th class="py-3.5 px-5 w-44">Allocate Amount</th>
                            <th class="py-3.5 px-5 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <template x-for="(row, index) in allocations" :key="row.sales_invoice_id">
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3 px-5 text-xs font-semibold text-muted tabular-nums" x-text="index + 1"></td>
                                <td class="py-3 px-5">
                                    <div class="text-sm font-bold text-ink tabular-nums" x-text="invoiceById(row.sales_invoice_id)?.invoice_no ?? '—'"></div>
                                    <div class="text-[11px] text-muted" x-text="invoiceById(row.sales_invoice_id)?.invoice_date ?? ''"></div>
                                    <input type="hidden" :name="`allocations[${index}][sales_invoice_id]`" :value="row.sales_invoice_id">
                                </td>
                                <td class="py-3 px-5 text-right text-sm text-ink tabular-nums" x-text="formatMoney(invoiceById(row.sales_invoice_id)?.grand_total)"></td>
                                <td class="py-3 px-5 text-right text-sm font-semibold text-danger tabular-nums" x-text="formatMoney(invoiceById(row.sales_invoice_id)?.payment_due)"></td>
                                <td class="py-3 px-5">
                                    <input type="number" min="0.01" step="0.01" :max="invoiceById(row.sales_invoice_id)?.payment_due"
                                           :name="`allocations[${index}][amount]`"
                                           x-model.number="row.amount"
                                           class="w-36 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                </td>
                                <td class="py-3 px-5">
                                    <button type="button" @click="row.amount = invoiceById(row.sales_invoice_id)?.payment_due || 0"
                                            class="rounded-lg border border-line bg-soft px-2 py-1 text-[11px] font-semibold text-muted hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                                        Full Due
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="allocations.length === 0" x-cloak class="py-12 px-6 text-center bg-canvas/20">
                <div class="max-w-sm mx-auto flex flex-col items-center">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                        <x-icon name="wallet" class="size-7" />
                    </div>
                    <p class="text-sm font-bold text-ink">No open invoices selected</p>
                    <p class="text-xs text-muted mt-1 leading-relaxed">
                        Choose a customer to load their open invoices, then click one to allocate.
                    </p>
                </div>
            </div>

            <div class="px-5 sm:px-6 py-4 border-t border-line" x-show="unallocatedInvoices.length > 0" x-cloak>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-2">Add Another Invoice</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="invoice in unallocatedInvoices" :key="invoice.id">
                        <button type="button" @click="addAllocation(invoice)"
                                class="inline-flex items-center gap-2 rounded-xl border border-line bg-soft px-3 py-1.5 text-xs font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                            <span class="tabular-nums" x-text="invoice.invoice_no"></span>
                            <span class="text-danger tabular-nums" x-text="'Due ' + formatMoney(invoice.payment_due)"></span>
                        </button>
                    </template>
                </div>
            </div>

            @if($errors->has('allocations') || $errors->has('allocations.*'))
                <div class="px-5 sm:px-6 py-3 border-t border-danger/20 bg-danger/5 space-y-1">
                    @error('allocations')
                        <p class="text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                    @foreach ($errors->get('allocations.*') as $messages)
                        @foreach ($messages as $message)
                            <p class="text-xs font-medium text-danger">{{ $message }}</p>
                        @endforeach
                    @endforeach
                </div>
            @endif

            <div class="border-t border-line bg-canvas/30 px-5 sm:px-6 py-5">
                <div class="flex items-center justify-end gap-2.5">
                    <a href="{{ route('customer-collections.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Record Payment</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function collectionForm(state) {
    return {
        customerId: state.customerId || null,
        invoices: state.invoices || [],
        allocations: state.allocations || [],
        amount: 0,

        formatMoney(value) {
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(value);
            }
            return '৳' + (parseFloat(value) || 0).toFixed(2);
        },

        invoiceById(id) {
            return this.invoices.find(invoice => invoice.id === id) || null;
        },

        async onCustomerChange() {
            this.allocations = [];

            if (! this.customerId) {
                this.invoices = [];
                return;
            }

            try {
                const response = await fetch(@js(route('customer-collections.customer-invoices')) + '?customer_id=' + encodeURIComponent(this.customerId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.invoices = data.invoices || [];
                // Prefill the first open invoice for a quick allocation.
                if (this.invoices.length > 0 && this.allocations.length === 0) {
                    this.allocations.push({ sales_invoice_id: this.invoices[0].id, amount: 0 });
                }
            } catch (error) {
                this.invoices = [];
            }
        },

        addAllocation(invoice) {
            if (! this.allocations.some(row => row.sales_invoice_id === invoice.id)) {
                this.allocations.push({ sales_invoice_id: invoice.id, amount: invoice.payment_due });
                this.amount = this.allocatedTotal;
            }
        },

        get unallocatedInvoices() {
            return this.invoices.filter(invoice => !this.allocations.some(row => row.sales_invoice_id === invoice.id));
        },

        get allocatedTotal() {
            return this.allocations.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
        }
    };
}
</script>
@endsection
