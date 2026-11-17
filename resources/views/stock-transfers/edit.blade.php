@extends('layouts.app')

@section('title', 'Edit Stock Transfer')
@section('page_title', 'Stock Transfers')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Stock Transfers</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">Edit Stock Transfer — {{ $stockTransfer->reference_no }}</span>
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
            <a href="{{ route('stock-transfers.index') }}" class="hover:text-brand-500">Stock Transfers List</a>
            <span>&rsaquo;</span>
            <span class="text-gray-700 dark:text-gray-300 font-medium">Edit Stock Transfer</span>
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
@endsection
