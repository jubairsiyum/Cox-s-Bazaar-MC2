@extends('layouts.app')

@section('title', 'Record Supplier Payment — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Supplier Payments')

@section('content')
@php
    $selectedSupplier = $suppliers->firstWhere('id', $selectedSupplierId);
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
                <span class="text-muted">Finance</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('supplier-payments.index') }}" class="hover:text-brand-500 transition-colors">Supplier Payments</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Record Payment</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Record Supplier Payment</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Purchase Due Settlement
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Settle part of a supplier's outstanding purchase due, paid from one of your cash or bank accounts.</p>
        </div>

        <a href="{{ route('supplier-payments.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Supplier Payments</span>
        </a>
    </div>

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

    {{-- Step 1: Supplier Picker --}}
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8 shadow-card">
        <div class="flex items-center gap-3 pb-5 mb-6 border-b border-line">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-icon name="truck" class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Step 1 — Choose Supplier</h2>
                <p class="text-xs text-muted mt-0.5">Outstanding purchase invoices of the selected supplier load automatically.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('supplier-payments.create') }}" class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
            <div class="md:col-span-2">
                <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Supplier <span class="text-danger">*</span>
                </label>
                <select id="supplier_id" name="supplier_id" onchange="this.form.submit()" required
                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer">
                    <option value="">Please Select</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($selectedSupplierId === $supplier->id)>
                            {{ $supplier->name }} ({{ $supplier->supplier_id }}) — due {{ \App\Helpers\Money::format($supplier->purchase_due) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit"
                        class="btn-scale w-full inline-flex items-center justify-center gap-2 rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                    <x-icon name="search" class="size-4" />
                    <span>Load Invoices</span>
                </button>
            </div>
        </form>
    </div>

    @if($selectedSupplier)
        {{-- Step 2: Payment Form --}}
        <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8 shadow-card">
            <div class="flex items-center gap-3 pb-5 mb-6 border-b border-line">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="payments" class="size-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-ink">Step 2 — Payment Details</h2>
                    <p class="text-xs text-muted mt-0.5 tabular-nums">
                        {{ $selectedSupplier->name }} — purchase due {{ \App\Helpers\Money::format($selectedSupplier->purchase_due) }}
                    </p>
                </div>
            </div>

            @if($orders->isEmpty())
                <x-empty-state message="This supplier has no outstanding purchase invoices to settle." />
            @else
                <form method="POST" action="{{ route('supplier-payments.store') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- Purchase Invoice --}}
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Purchase Invoice <span class="text-danger">*</span>
                            </label>
                            <div class="space-y-2">
                                @foreach($orders as $order)
                                    <label class="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3 cursor-pointer hover:border-brand-500/40 transition-colors @error('purchase_order_id') border-danger @enderror">
                                        <input type="radio" name="purchase_order_id" value="{{ $order->id }}"
                                               @checked(old('purchase_order_id') == $order->id || (! old('purchase_order_id') && $loop->first))
                                               class="size-4 text-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-semibold text-ink tabular-nums">{{ $order->reference_no }}</span>
                                            <span class="block text-xs text-muted">
                                                {{ $order->order_date->format('d M Y') }}
                                                @if($order->location) · {{ $order->location->name }} @endif
                                                · Total {{ \App\Helpers\Money::format($order->grand_total) }}
                                            </span>
                                        </span>
                                        <span class="text-end shrink-0">
                                            <span class="block text-[11px] font-semibold text-muted uppercase tracking-wider">Due</span>
                                            <span class="block text-sm font-bold text-danger tabular-nums">{{ \App\Helpers\Money::format($order->payment_due) }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('purchase_order_id')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Amount --}}
                        <div>
                            <label for="amount" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Amount ({{ \App\Models\Setting::get('currency_code', 'BDT') }}) <span class="text-danger">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('amount') border-danger @enderror">
                            @error('amount')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Payment Date --}}
                        <div>
                            <label for="payment_date" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Payment Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required
                                   class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('payment_date') border-danger @enderror">
                            @error('payment_date')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Paying Account --}}
                        <div>
                            <label for="account_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Paying Account <span class="text-danger">*</span>
                            </label>
                            <select id="account_id" name="account_id" required
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('account_id') border-danger @enderror">
                                <option value="">Please Select</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                        {{ $account->name }} ({{ $account->type->label() }} — {{ \App\Helpers\Money::format($account->current_balance) }})
                                    </option>
                                @endforeach
                            </select>
                            @error('account_id')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Method --}}
                        <div>
                            <label for="method" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Method
                            </label>
                            <select id="method" name="method"
                                    class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('method') border-danger @enderror">
                                <option value="">Please Select</option>
                                @foreach(\App\Enums\PaymentMethod::options() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('method')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Note --}}
                        <div class="md:col-span-2">
                            <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                Note
                            </label>
                            <textarea id="note" name="note" rows="3"
                                      placeholder="Cheque no., transaction id, or remarks (optional)"
                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Form Footer Actions --}}
                    <div class="pt-5 border-t border-line flex items-center justify-end gap-3">
                        <a href="{{ route('supplier-payments.index') }}"
                           class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                            Cancel
                        </a>
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="check" class="size-4" />
                            <span>Record Payment</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
