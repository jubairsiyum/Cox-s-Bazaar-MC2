@extends('layouts.app')

@section('title', 'Add Product — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Product')

@section('content')
@php
    $previewState = [
        'name' => (string) old('name', ''),
        'storage' => (string) old('storage', ''),
        'color' => (string) old('color', ''),
        'model' => (string) old('model', ''),
        'purchasePrice' => (string) old('purchase_price', ''),
        'salePrice' => (string) old('sale_price', ''),
        'quantity' => (string) old('quantity', '0'),
        'unit' => (string) old('unit', 'Pcs'),
        'status' => (string) old('status', 'active'),
    ];
@endphp

<div x-data="productFormPreview(@js($previewState))" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('products.index') }}" class="hover:text-brand-500 transition-colors">Products List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Add Product</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Add Product</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="plus" class="size-3.5" />
                    <span>New Catalog SKU</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Register a new device or accessory variant with pricing, barcode/IMEI, and opening stock.</p>
        </div>

        <a href="{{ route('products.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0 self-start sm:self-auto">
            <x-svg.arrow-left class="size-4 text-muted" />
            <span>Back to Products</span>
        </a>
    </div>

    {{-- Validation Alert Banner --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger">
            <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
            <div class="text-xs sm:text-sm">
                <p class="font-bold">Please Enter Valid Data</p>
                <p class="mt-0.5 opacity-90">Check the highlighted fields below and resolve any validation errors before saving.</p>
            </div>
        </div>
    @endif

    {{-- Main 3-Column Form + Live Preview Layout --}}
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3 items-start">
        @csrf

        {{-- Left 2 Columns: Structured Form Cards --}}
        <div class="xl:col-span-2 space-y-6">

            @include('products._form-fields', ['cancelUrl' => route('products.index'), 'submitLabel' => 'Save Product'])
        </div>

        {{-- Right Column: Live Margin & Variant Preview --}}
        <div class="space-y-5 xl:sticky xl:top-6">
            @include('products._form-preview')

            {{-- Guidance Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="box" class="size-4" />
                    </div>
                    <h3 class="text-sm font-bold text-ink">Variant &amp; Stock Sync</h3>
                </div>
                <ul class="space-y-2 text-xs text-muted leading-relaxed">
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Each <strong class="text-ink">(Name, Storage, Color)</strong> combination is tracked as its own unique stock row in Units.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Setting an opening quantity automatically records an <strong class="text-ink">Opening</strong> movement in the stock ledger.</span>
                    </li>
                </ul>
            </div>
        </div>
    </form>
</div>

@include('products._form-script')
@endsection
