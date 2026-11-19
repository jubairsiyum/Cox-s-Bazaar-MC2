@extends('layouts.app')

@section('title', 'Edit Purchase Order ' . $order->reference_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Purchase Invoices')

@section('content')
<div class="space-y-6">
    {{-- Executive Breadcrumb Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Purchasing</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('purchase-invoices.index') }}" class="hover:text-brand-500 transition-colors">Purchase Invoices</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit {{ $order->reference_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Purchase Invoice</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    {{ $order->reference_no }}
                </span>
                @include('purchase-invoices.partials.consolidated-status', ['order' => $order])
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Update order details, payment status, or attached documents. Line items are frozen once a purchase has taken effect.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('purchase-invoices.show', $order) }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.eye class="size-4" />
                <span>View Invoice</span>
            </a>
            <a href="{{ route('purchase-invoices.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted shadow-2xs hover:bg-soft hover:text-ink transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Purchase Invoices</span>
            </a>
        </div>
    </div>

    @if(! $itemsEditable)
        <div class="rounded-3xl border border-warning/30 bg-warning/10 p-4 sm:p-5 flex items-start gap-3.5 shadow-2xs">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/15 text-warning ring-1 ring-warning/25">
                <x-icon name="alert-triangle" class="size-5" />
            </div>
            <div>
                <h2 class="text-sm font-bold text-ink">Line Items Frozen (Order Received)</h2>
                <p class="text-xs sm:text-sm text-muted mt-0.5 leading-relaxed">
                    This order has been received, so its line items are frozen. You can still update the supplier, status, payment, and document details below.
                </p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('purchase-invoices.update', $order) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        @include('purchase-orders._form', [
            'order' => $order,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'statuses' => $statuses,
            'items' => $items,
            'itemsEditable' => $itemsEditable,
        ])
    </form>
</div>
@endsection
