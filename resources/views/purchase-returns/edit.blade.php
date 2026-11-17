@extends('layouts.app')

@section('title', 'Edit Purchase Return')
@section('page_title', 'Purchase Returns')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Purchase Returns</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">Edit Purchase Return {{ $purchaseReturn->reference_no }}</span>
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
            <a href="{{ route('purchase-returns.index') }}" class="hover:text-brand-500">Purchase Returns List</a>
            <span>&rsaquo;</span>
            <span class="text-gray-700 dark:text-gray-300 font-medium">Edit Purchase Return</span>
        </div>
    </div>

    <form method="POST" action="{{ route('purchase-returns.update', $purchaseReturn) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        @include('purchase-returns._form', [
            'purchaseReturn' => $purchaseReturn,
            'suppliers' => $suppliers,
            'parentOrder' => $parentOrder,
            'purchases' => $purchases,
            'rows' => $rows,
        ])
    </form>
@endsection
