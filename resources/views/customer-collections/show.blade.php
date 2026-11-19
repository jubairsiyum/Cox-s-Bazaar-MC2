@extends('layouts.app')

@section('title', $collection->collection_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Collections')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Sales</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('customer-collections.index') }}" class="hover:text-brand-500 transition-colors">Collections</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $collection->collection_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $collection->collection_no }}</h1>
                <x-status-badge :status="'active'" :label="$collection->method->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                {{ $collection->collection_date->format('d/m/Y H:i') }} · {{ $collection->location?->name ?? '—' }} · Recorded by {{ $collection->creator?->name ?? '—' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('customer-collections.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>
            <a href="{{ route('customer-collections.receipt', $collection) }}" target="_blank"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-icon name="enquiries" class="size-4" />
                <span>Print Receipt</span>
            </a>
            @can('delete', $collection)
                <x-confirm-delete :action="route('customer-collections.destroy', $collection)" label="Delete" confirm-text="Delete Collection" message="Deleting this collection reverses the payments on its invoices. Continue?" />
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Allocations --}}
        <div class="xl:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base sm:text-lg font-bold text-ink">Invoice Allocations</h2>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-5 w-12">#</th>
                            <th class="py-3 px-5">Invoice</th>
                            <th class="py-3 px-5">Invoice Date</th>
                            <th class="py-3 px-5 text-right w-32">Invoice Total</th>
                            <th class="py-3 px-5 text-right w-36">Allocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($collection->allocations as $index => $allocation)
                            <tr>
                                <td class="py-3 px-5 text-xs font-semibold text-muted tabular-nums">{{ $index + 1 }}</td>
                                <td class="py-3 px-5">
                                    @can('view', $allocation->salesInvoice)
                                        <a href="{{ route('sales-invoices.show', $allocation->salesInvoice) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">{{ $allocation->salesInvoice?->invoice_no ?? '—' }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $allocation->salesInvoice?->invoice_no ?? '—' }}</span>
                                    @endcan
                                </td>
                                <td class="py-3 px-5 text-sm text-ink tabular-nums">{{ $allocation->salesInvoice?->invoice_date?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="py-3 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($allocation->salesInvoice?->grand_total ?? 0) }}</td>
                                <td class="py-3 px-5 text-right text-sm font-bold text-success tabular-nums">{{ \App\Helpers\Money::format($allocation->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($collection->note)
                <div class="px-5 sm:px-6 py-4 border-t border-line">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Note</p>
                    <p class="text-sm text-ink">{{ $collection->note }}</p>
                </div>
            @endif
        </div>

        {{-- Summary --}}
        <div class="space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Payment Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Amount</dt>
                        <dd class="text-lg font-extrabold text-success tabular-nums">{{ \App\Helpers\Money::format($collection->amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Method</dt>
                        <dd class="font-semibold text-ink">{{ $collection->method->label() }}</dd>
                    </div>
                    @if($collection->reference_no)
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Reference</dt>
                            <dd class="font-semibold text-ink tabular-nums">{{ $collection->reference_no }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Date</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ $collection->collection_date->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Customer</h2>
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 font-bold">
                        {{ strtoupper(substr($collection->customer?->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-bold text-ink">{{ $collection->customer?->name ?? '—' }}</p>
                        <p class="text-xs text-muted">{{ $collection->customer?->mobile ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
