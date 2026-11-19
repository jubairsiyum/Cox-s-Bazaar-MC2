@extends('layouts.app')

@section('title', 'Apply Return — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Returns')

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
                <a href="{{ route('sales-returns.index') }}" class="hover:text-brand-500 transition-colors">Sales Returns</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Apply Return</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Apply Sales Return</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    New Return Request
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Select a customer and original invoice, specify returned quantities and submit for branch manager approval.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-returns.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Sales Returns</span>
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('sales-returns.store') }}">
        @csrf

        @include('sales-returns._form', [
            'salesReturn' => $salesReturn ?? new \App\Models\SalesReturn,
            'customers' => $customers,
            'parentInvoice' => $parentInvoice,
            'invoices' => $invoices,
            'rows' => $rows,
            'cancelUrl' => route('sales-returns.index'),
        ])
    </form>
</div>
@endsection
