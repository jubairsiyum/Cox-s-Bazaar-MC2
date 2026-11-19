@extends('layouts.app')

@section('title', 'Edit Stock Transfer — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Transfers')

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
                <a href="{{ route('stock-transfers.index') }}" class="hover:text-brand-500 transition-colors">Stock Transfers</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Stock Transfer</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Stock Transfer</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    {{ $stockTransfer->reference_no }}
                </span>
                @php($editStatus = $stockTransfer->displayStatusFor(auth()->user()))
                <x-status-badge :status="$editStatus['value']" :label="$editStatus['label']" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                @if($itemsEditable)
                    Update transfer route, status, or line items before dispatch.
                @else
                    Line items are locked after dispatch; you may still update status, shipping charges, or notes.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('stock-transfers.show', $stockTransfer) }}"
               class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.eye class="size-4 text-muted" />
                <span>View Invoice</span>
            </a>
            <a href="{{ route('stock-transfers.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('stock-transfers.update', $stockTransfer) }}">
        @csrf
        @method('PATCH')

        @include('stock-transfers._form', [
            'stockTransfer' => $stockTransfer,
            'locations' => $locations,
            'destinationLocations' => $destinationLocations,
            'statuses' => $statuses,
            'items' => $items,
            'itemsEditable' => $itemsEditable,
        ])
    </form>

</div>
@endsection
