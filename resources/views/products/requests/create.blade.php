@extends('layouts.app')

@section('title', 'New Product Request — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Product Requests')

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
                <span class="font-medium text-ink">New Product Request</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">New Product Request</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning ring-1 ring-warning/20">
                    <x-icon name="check" class="size-3.5" />
                    <span>Needs Super Admin Approval</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Describe the product you need in the catalog. The Super Admin reviews it; nothing is added until it is approved.</p>
        </div>

        <a href="{{ route('products.requests.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0 self-start sm:self-auto">
            <x-svg.arrow-left class="size-4 text-muted" />
            <span>Back to Requests</span>
        </a>
    </div>

    {{-- Validation Alert Banner --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger">
            <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
            <div class="text-xs sm:text-sm">
                <p class="font-bold">Please Enter Valid Data</p>
                <p class="mt-0.5 opacity-90">Check the highlighted fields below and resolve any validation errors before submitting.</p>
            </div>
        </div>
    @endif

    {{-- Main 3-Column Form + Live Preview Layout --}}
    <form method="POST" action="{{ route('products.requests.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3 items-start">
        @csrf

        {{-- Left 2 Columns: Structured Form Cards --}}
        <div class="xl:col-span-2 space-y-6">

            @include('products._form-fields', ['cancelUrl' => route('products.requests.index'), 'submitLabel' => 'Submit Request'])
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
                    <h3 class="text-sm font-bold text-ink">How Requests Work</h3>
                </div>
                <ul class="space-y-2 text-xs text-muted leading-relaxed">
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>A request <strong class="text-ink">does not create a product</strong>. It is stored with these details and sent to the Super Admin.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Approval adds the product to the catalog with the opening quantity shown here. Rejection requires a reason.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Name and barcode / IMEI are checked against the catalog now and again when the request is approved.</span>
                    </li>
                </ul>
            </div>
        </div>
    </form>
</div>

@include('products._form-script')
@endsection
