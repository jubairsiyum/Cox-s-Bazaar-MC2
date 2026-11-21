@extends('layouts.app')

@section('title', $sectionMeta['label'] . ' — ' . $supplier->name . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', $sectionMeta['label'])

@section('content')
@php
    $isSupplierPortal = auth()->user()?->isSupplierPortalUser();
    $canRecordPayments = ! $isSupplierPortal && (auth()->user()?->can('supplier-payments.manage') || auth()->user()?->can('finance.manage'));
@endphp

<div class="space-y-6">

    <div>
        <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
            <a href="{{ route('suppliers.my-profile') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                <x-icon name="dashboard" class="size-3.5" />
                <span>Home</span>
            </a>
            <span class="text-muted/60">/</span>
            <a href="{{ route('suppliers.my-profile.section', 'details') }}" class="hover:text-brand-500 transition-colors">{{ $supplier->name }}</a>
            <span class="text-muted/60">/</span>
            <span class="font-medium text-ink">{{ $sectionMeta['label'] }}</span>
        </nav>

        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $sectionMeta['label'] }}</h1>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                {{ $supplier->supplier_id }}
            </span>
        </div>
        <p class="text-xs sm:text-sm text-muted mt-1">{{ $sectionMeta['description'] }}</p>
    </div>

    @if($section === 'details')
        @include('suppliers.partials.summary')
    @endif

    @include('suppliers.partials.' . $sectionMeta['partial'])

    @if($section === 'details' && $isSupplierPortal)
        @include('suppliers.partials.change-password')
    @endif
</div>
@endsection
