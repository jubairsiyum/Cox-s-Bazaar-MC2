@extends('layouts.app')

@section('title', 'Edit Supplier — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Suppliers')

@section('content')
@php
    $currencySymbol = \App\Models\Setting::get('currency_symbol', '৳');
    $purchaseDue = (float) $supplier->purchase_due;
    $returnDue = (float) $supplier->purchase_return_due;
@endphp

<div class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Parties</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('suppliers.index') }}" class="hover:text-brand-500 transition-colors">Suppliers List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Supplier</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Suppliers</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Add/Update Suppliers
                </span>
                <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line font-mono">
                    {{ $supplier->supplier_id }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold {{ $supplier->status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                    <span class="size-1.5 rounded-full {{ $supplier->status === 'active' ? 'bg-success' : 'bg-danger' }}"></span>
                    <span>{{ ucfirst($supplier->status) }}</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Manage vendor profile, balance details, and opening payment ledger for <strong class="text-ink">{{ $supplier->name }}</strong>.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('suppliers.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 hover:bg-soft transition-colors shadow-2xs">
                <x-svg.arrow-left class="size-4 text-muted" />
                <span>Back to Suppliers List</span>
            </a>
        </div>
    </div>

    {{-- Supplier Financial Snapshot Strip --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-3xl border border-line bg-surface p-4 sm:p-5 shadow-card flex items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Opening Balance</span>
                <p class="text-xl font-bold text-ink mt-1 tabular-nums">{{ $currencySymbol }}{{ number_format((float) $supplier->opening_balance, 2) }}</p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                <x-icon name="taka" class="size-5" />
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-4 sm:p-5 shadow-card flex items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Purchase Due</span>
                <p class="text-xl font-bold mt-1 tabular-nums {{ $purchaseDue > 0 ? 'text-danger' : 'text-ink' }}">
                    {{ $currencySymbol }}{{ number_format($purchaseDue, 2) }}
                </p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ $purchaseDue > 0 ? 'bg-danger/10 text-danger ring-1 ring-danger/20' : 'bg-success/10 text-success ring-1 ring-success/20' }}">
                <x-icon name="wallet" class="size-5" />
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-4 sm:p-5 shadow-card flex items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Purchase Return Due</span>
                <p class="text-xl font-bold mt-1 tabular-nums {{ $returnDue > 0 ? 'text-warning' : 'text-ink' }}">
                    {{ $currencySymbol }}{{ number_format($returnDue, 2) }}
                </p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                <x-icon name="payments" class="size-5" />
            </div>
        </div>
    </div>

    {{-- Main Form --}}
    <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left Column: Identity, Contact & Tax/Financial --}}
            <div class="lg:col-span-6 space-y-6">
                {{-- Card 1: Identity & Contact --}}
                <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-line">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                            <x-icon name="truck" class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-ink">Identity &amp; Contact</h2>
                            <p class="text-xs text-muted">Primary vendor name and contact channels.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Supplier Name<span class="text-danger">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" required
                                   placeholder="Enter supplier or company name"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                            @error('name')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="mobile" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Mobile No.<span class="text-danger">*</span>
                                </label>
                                <input type="text" id="mobile" name="mobile" value="{{ old('mobile', $supplier->mobile) }}" required
                                       placeholder="e.g. 01700000000"
                                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('mobile') border-danger @enderror">
                                @error('mobile')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tel_no" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Tel No.
                                </label>
                                <input type="text" id="tel_no" name="tel_no" value="{{ old('tel_no', $supplier->tel_no) }}"
                                       placeholder="e.g. 034164200 / Landline"
                                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('tel_no') border-danger @enderror">
                                @error('tel_no')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" value="{{ old('email', $supplier->email) }}"
                                   placeholder="supplier@example.com"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('email') border-danger @enderror">
                            @error('email')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Card 2: Financial & Tax Profile --}}
                <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-line">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                            <x-icon name="wallet" class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-ink">Financial &amp; Tax Profile</h2>
                            <p class="text-xs text-muted">Tax identification and initial payable opening balance.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="tax_number" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                TAX Number
                            </label>
                            <input type="text" id="tax_number" name="tax_number" value="{{ old('tax_number', $supplier->tax_number) }}"
                                   placeholder="TIN / Tax registration no."
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('tax_number') border-danger @enderror">
                            @error('tax_number')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="opening_balance" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Opening Balance ({{ $currencySymbol }})
                            </label>
                            <div class="relative flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-sm font-bold text-muted">
                                    {{ $currencySymbol }}
                                </span>
                                <input type="number" step="0.01" min="0" id="opening_balance" name="opening_balance"
                                       value="{{ old('opening_balance', $supplier->opening_balance) }}"
                                       placeholder="0.00"
                                       class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm font-semibold text-ink text-right tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('opening_balance') border-danger @enderror">
                            </div>
                            @error('opening_balance')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Address & Location --}}
            <div class="lg:col-span-6 rounded-3xl border border-line bg-surface p-6 shadow-card space-y-5 flex flex-col justify-between">
                <div class="space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-line">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                            <x-icon name="store" class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-ink">Address &amp; Location</h2>
                            <p class="text-xs text-muted">Warehouse or office address for purchase orders and returns.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="country" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Country
                                </label>
                                <select id="country" name="country"
                                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('country') border-danger @enderror">
                                    <option value="Bangladesh" @selected(old('country', $supplier->country) === 'Bangladesh')>Bangladesh</option>
                                    <option value="India" @selected(old('country', $supplier->country) === 'India')>India</option>
                                    <option value="China" @selected(old('country', $supplier->country) === 'China')>China</option>
                                    <option value="United Arab Emirates" @selected(old('country', $supplier->country) === 'United Arab Emirates')>United Arab Emirates</option>
                                    <option value="Singapore" @selected(old('country', $supplier->country) === 'Singapore')>Singapore</option>
                                    <option value="United States" @selected(old('country', $supplier->country) === 'United States')>United States</option>
                                </select>
                                @error('country')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="state" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    State / Division
                                </label>
                                <input type="text" id="state" name="state" value="{{ old('state', $supplier->state) }}"
                                       placeholder="State / Division"
                                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('state') border-danger @enderror">
                                @error('state')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="city" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    City
                                </label>
                                <input type="text" id="city" name="city" value="{{ old('city', $supplier->city) }}"
                                       placeholder="City name"
                                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('city') border-danger @enderror">
                                @error('city')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="postcode" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Postcode
                                </label>
                                <input type="text" id="postcode" name="postcode" value="{{ old('postcode', $supplier->postcode) }}"
                                       placeholder="e.g. 4700"
                                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all tabular-nums @error('postcode') border-danger @enderror">
                                @error('postcode')
                                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Full Street Address
                            </label>
                            <textarea id="address" name="address" rows="5"
                                      placeholder="Supplier office, warehouse, road, and area details..."
                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('address') border-danger @enderror">{{ old('address', $supplier->address) }}</textarea>
                            @error('address')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Footer --}}
        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 rounded-3xl border border-line bg-surface px-6 py-4 shadow-card">
            <a href="{{ route('suppliers.index') }}"
               class="btn-scale w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-line bg-surface px-6 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                Close
            </a>
            <button type="submit"
                    class="btn-scale w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-7 py-2.5 text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                <x-icon name="check" class="size-4" />
                <span>Update</span>
            </button>
        </div>
    </form>

    {{-- Opening Balance Payments Ledger Card --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-line flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="payments" class="size-4" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Opening Balance Payments</h2>
                    <p class="text-xs text-muted">Recorded due &amp; return settlement history for this supplier.</p>
                </div>
            </div>

            <span class="inline-flex items-center rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums">
                {{ $openingPayments->count() }} {{ Str::plural('payment', $openingPayments->count()) }}
            </span>
        </div>

        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted uppercase tracking-wider">
                        <th scope="col" class="w-12 py-3 px-5 text-center">#</th>
                        <th scope="col" class="py-3 px-4 font-bold text-ink">Payment Date</th>
                        <th scope="col" class="py-3 px-4 text-end font-bold text-ink">Payment</th>
                        <th scope="col" class="py-3 px-4 font-bold text-ink">Payment Type</th>
                        <th scope="col" class="py-3 px-4 font-bold text-ink">Payment Note</th>
                        <th scope="col" class="py-3 px-5 text-end font-bold text-ink">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface text-xs sm:text-sm">
                    @forelse($openingPayments as $index => $payment)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5 text-center text-muted tabular-nums">{{ $index + 1 }}</td>
                            <td class="py-3.5 px-4 font-medium text-ink whitespace-nowrap tabular-nums">
                                {{ $payment->payment_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-end font-bold text-success whitespace-nowrap tabular-nums">
                                {{ $currencySymbol }}{{ number_format((float) $payment->amount, 2) }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-500 ring-1 ring-brand-500/20">
                                    {{ $payment->payment_type }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-muted">
                                {{ $payment->payment_note ?: '—' }}
                            </td>
                            <td class="py-3.5 px-5 text-end whitespace-nowrap">
                                <form action="{{ route('suppliers.payments.destroy', $payment) }}" method="POST"
                                      onsubmit="return confirm('Are you sure you want to delete this payment record? This will revert the supplier due balance.');"
                                      class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger ring-1 ring-danger/20 hover:bg-danger hover:text-on-brand transition-colors cursor-pointer">
                                        <x-svg.trash-bin class="size-3.5" />
                                        <span>Delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 px-6 text-center text-xs text-muted font-medium">
                                No Previous Stock Entry Found!!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
