@extends('layouts.app')

@section('title', 'Edit Purchase Order')
@section('page_title', 'Purchase Invoices')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Purchase Invoices</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">Edit Purchase Order {{ $order->reference_no }}</span>
            </div>
        </div>
        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Home
            </a>
            <span>&rsaquo;</span>
            <a href="{{ route('purchase-invoices.index') }}" class="hover:text-brand-500">Purchase Invoices List</a>
            <span>&rsaquo;</span>
            <span class="text-gray-700 dark:text-gray-300 font-medium">Edit Purchase Order</span>
        </div>
    </div>

    @if(! $itemsEditable)
        <div class="card mb-4 border-t-4 border-t-amber-500 shadow-sm">
            <div class="card-body p-4 text-sm text-gray-700 dark:text-gray-300">
                This order has been received, so its line items are frozen. You can still update the
                supplier, status, payment and document details below.
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
@endsection
