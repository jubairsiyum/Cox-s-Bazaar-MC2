@extends('layouts.app')

@section('title', 'Supplier Profile — ' . $supplier->name . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Supplier Profile')

@section('sidebar_sections')
    @include('suppliers.partials.profile-sidebar-sections')
@endsection

@section('content')
@php
    $isSupplierPortal = auth()->user()?->isSupplierPortalUser();
    $canRecordPayments = ! $isSupplierPortal && (auth()->user()?->can('supplier-payments.manage') || auth()->user()?->can('finance.manage'));
@endphp

<div class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                @unless($isSupplierPortal)
                    <span class="text-muted/60">/</span>
                    <a href="{{ route('suppliers.index') }}" class="hover:text-brand-500 transition-colors">Parties / Suppliers</a>
                    <span class="text-muted/60">/</span>
                @endunless
                <span class="font-medium text-ink">{{ $supplier->name }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $supplier->name }}</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    {{ $supplier->supplier_id }}
                </span>
                <x-status-badge :status="$supplier->status" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Supplier profile — dues, purchase orders, payments, returns and payment requests.
                @if($supplier->mobile) · {{ $supplier->mobile }} @endif
                @if($supplier->email) · {{ $supplier->email }} @endif
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @unless($isSupplierPortal)
                <a href="{{ route('suppliers.index') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                    <x-svg.arrow-left class="size-4" />
                    <span>Back to Suppliers</span>
                </a>
            @endunless
            @if($canRecordPayments)
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="payments" class="size-4" />
                    <span>Record Payment</span>
                </a>
            @endif
        </div>
    </div>

    @include('suppliers.partials.summary')

    <div id="details" class="scroll-mt-24">
        @include('suppliers.partials.details')
    </div>

    <div id="dues" class="scroll-mt-24">
        @include('suppliers.partials.dues')
    </div>

    <div id="orders" class="scroll-mt-24">
        @include('suppliers.partials.orders')
    </div>

    <div id="payments" class="scroll-mt-24">
        @include('suppliers.partials.payments')
    </div>

    <div id="returns" class="scroll-mt-24">
        @include('suppliers.partials.returns')
    </div>

    <div id="requests" class="scroll-mt-24">
        @include('suppliers.partials.payment-requests')
    </div>

    <div id="activity" class="scroll-mt-24">
        @include('suppliers.partials.feed')
    </div>
</div>
@endsection
