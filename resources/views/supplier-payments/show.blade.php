@extends('layouts.app')

@section('title', 'Supplier Payment — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Supplier Payments')

@section('content')
@php
    $supplier = $payment->purchaseOrder?->supplier;
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
                <span class="font-medium text-ink">Payment Details</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Supplier Payment</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    {{ \App\Helpers\Money::format($payment->amount) }}
                </span>
                <x-status-badge :status="$payment->status->value" :label="$payment->status->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Payment against purchase invoice {{ $payment->purchaseOrder?->reference_no ?? '—' }} · {{ ($payment->payment_date ?? $payment->created_at)->format('d M Y') }}</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('supplier-payments.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Supplier Payments</span>
            </a>

            @if(auth()->user()?->can('supplier-payments.manage') || auth()->user()?->can('finance.manage'))
                <x-confirm-delete
                    :action="route('supplier-payments.destroy', $payment)"
                    :message="'Delete the payment of ' . \App\Helpers\Money::format($payment->amount) . '? Applied payments restore all due balances.'"
                    confirm-text="Delete Payment"
                    label="Delete Payment" />
            @endif
        </div>
    </div>

    {{-- Payment Details --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
            <div class="flex items-center gap-3 pb-5 mb-5 border-b border-line">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="payments" class="size-5" />
                </div>
                <h2 class="text-base font-bold text-ink">Payment Information</h2>
            </div>

            <dl class="space-y-3.5 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Amount</dt>
                    <dd class="font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($payment->amount) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Payment Date</dt>
                    <dd class="text-ink tabular-nums">{{ ($payment->payment_date ?? $payment->created_at)->format('d M Y') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Paid From</dt>
                    <dd class="text-ink text-end">{{ $payment->account?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Method</dt>
                    <dd class="text-ink">{{ $payment->method?->label() ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Recorded By</dt>
                    <dd class="text-ink text-end">{{ $payment->creator?->name ?? 'Unknown' }}</dd>
                </div>
                @if($payment->approved_by)
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted">Approved By</dt>
                        <dd class="text-ink text-end">
                            {{ $payment->approver?->name ?? 'Unknown' }}
                            @if($payment->approved_at)
                                <span class="block text-xs text-muted tabular-nums">{{ $payment->approved_at->format('d M Y, h:i A') }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if($payment->note)
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-muted">Note</dt>
                        <dd class="text-ink text-end max-w-[60%]">{{ $payment->note }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
            <div class="flex items-center gap-3 pb-5 mb-5 border-b border-line">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="truck" class="size-5" />
                </div>
                <h2 class="text-base font-bold text-ink">Purchase &amp; Supplier</h2>
            </div>

            <dl class="space-y-3.5 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Supplier</dt>
                    <dd class="font-semibold text-ink text-end">{{ $supplier?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Purchase Invoice</dt>
                    <dd class="text-ink tabular-nums">{{ $payment->purchaseOrder?->reference_no ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Invoice Total</dt>
                    <dd class="text-ink tabular-nums">{{ \App\Helpers\Money::format($payment->purchaseOrder?->grand_total ?? 0) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Invoice Paid</dt>
                    <dd class="text-ink tabular-nums">{{ \App\Helpers\Money::format($payment->purchaseOrder?->paid_amount ?? 0) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Invoice Remaining Due</dt>
                    <dd class="font-bold text-danger tabular-nums">{{ \App\Helpers\Money::format($payment->purchaseOrder?->payment_due ?? 0) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-muted">Supplier Purchase Due</dt>
                    <dd class="font-bold text-danger tabular-nums">{{ \App\Helpers\Money::format($supplier?->purchase_due ?? 0) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
