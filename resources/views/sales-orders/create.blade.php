@extends('layouts.app')

@section('title', 'Create Sales Order — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Orders')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2.5" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Sales</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('sales-orders.index') }}" class="hover:text-brand-500 transition-colors">Sales Orders</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Create Order</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Create Sales Order</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    Auto No (SO-)
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-muted ring-1 ring-line">
                    Draft Stage
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1.5 max-w-2xl leading-relaxed">
                Capture the order with accurate business location stock. Payment confirmation will later issue stock and generate the settled sales invoice.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-orders.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Sales Orders</span>
            </a>
        </div>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ route('sales-orders.store') }}" id="sales-order-form">
        @csrf

        @include('sales-orders._form', [
            'order' => $order ?? new \App\Models\SalesOrder,
            'customers' => $customers,
            'locations' => $locations,
            'rows' => $rows,
            'defaultLocationId' => $defaultLocationId ?? null,
            'cancelUrl' => route('sales-orders.index'),
        ])
    </form>
</div>
@endsection
