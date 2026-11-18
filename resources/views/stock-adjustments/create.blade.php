@extends('layouts.app')

@section('title', 'Add Stock Adjustment — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Adjustments')

@section('content')
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
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('stock-adjustments.index') }}" class="hover:text-brand-500 transition-colors">Stock Adjustments</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Add Stock Adjustment</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Add Stock Adjustment</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    New Correction
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Adjust stock quantities for damages, found units, or physical count reconciliations.</p>
        </div>

        <a href="{{ route('stock-adjustments.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Adjustments</span>
        </a>
    </div>

    <form method="POST" action="{{ route('stock-adjustments.store') }}">
        @csrf

        @include('stock-adjustments._form', [
            'stockAdjustment' => $stockAdjustment ?? new \App\Models\StockAdjustment,
            'types' => $types,
            'typeHints' => $typeHints,
            'locations' => $locations,
            'items' => $items,
        ])
    </form>

</div>
@endsection
