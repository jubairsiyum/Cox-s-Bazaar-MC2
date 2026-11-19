@extends('layouts.app')

@section('title', 'Edit Sales Invoice — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Invoices')

@section('content')
<div class="space-y-6">
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
                <a href="{{ route('sales-invoices.index') }}" class="hover:text-brand-500 transition-colors">Sales Invoices</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit {{ $invoice->invoice_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Sales Invoice</h1>
                <x-status-badge :status="$invoice->status->value" :label="$invoice->status->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Only draft invoices can be edited. Posting issues the stock-out and locks the document.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-invoices.show', $invoice) }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Invoice</span>
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('sales-invoices.update', $invoice) }}">
        @csrf
        @method('PATCH')

        @include('sales-invoices._form', [
            'invoice' => $invoice,
            'customers' => $customers,
            'locations' => $locations,
            'salesOrder' => $salesOrder ?? null,
            'rows' => $rows,
            'cancelUrl' => route('sales-invoices.show', $invoice),
        ])
    </form>
</div>
@endsection
