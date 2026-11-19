@extends('layouts.app')

@section('title', 'Add Purchase Order — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Purchase Orders')

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
                <span class="font-medium text-ink">New Purchase Order</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">New Purchase Order</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    Draft Entry
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Create a supplier purchase order, add product units, configure purchase tax, and record any upfront payment.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('purchase-invoices.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Purchase Invoices</span>
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('purchase-orders.store') }}" enctype="multipart/form-data">
        @csrf

        @include('purchase-orders._form', [
            'order' => $order ?? new \App\Models\PurchaseOrder,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'items' => $items,
            'itemsEditable' => true,
        ])
    </form>
</div>
@endsection
