@extends('layouts.app')

@section('title', $location->name . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Branches')

@section('content')
@php
    $summary = $data['summary'];
    $typeIsWarehouse = $location->type === \App\Enums\LocationType::Warehouse;
    $typeIsBrandShop = $location->type === \App\Enums\LocationType::BrandShop;
    $typeIcon = $typeIsWarehouse ? 'warehouse' : ($typeIsBrandShop ? 'store' : 'drivers');
    $typeBadgeClass = $typeIsWarehouse
        ? 'bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20'
        : ($typeIsBrandShop
            ? 'bg-warning/10 text-warning ring-1 ring-warning/20'
            : 'bg-success/10 text-success ring-1 ring-success/20');
@endphp

<div class="space-y-6">

    {{-- Header & Action Bar --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('branches.index') }}" class="hover:text-brand-500 transition-colors">Branches</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $location->name }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $location->name }}</h1>
                @if($location->code)
                    <span class="inline-flex items-center rounded-lg bg-soft px-2.5 py-1 text-xs font-bold text-ink border border-line tabular-nums">
                        {{ $location->code }}
                    </span>
                @endif
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeBadgeClass }}">
                    <x-icon :name="$typeIcon" class="size-3.5 mr-1" />
                    {{ $location->type->label() }}
                </span>
                <x-status-badge :status="$location->status" />
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs sm:text-sm text-muted">
                @if($location->address)
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="warehouse" class="size-3.5 text-brand-500" />
                        {{ $location->address }}
                    </span>
                @endif
                @if($location->phone)
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="phone" class="size-3.5 text-brand-500" />
                        {{ $location->phone }}
                    </span>
                @endif
                @if($location->note)
                    <span class="text-muted/80">{{ $location->note }}</span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(auth()->user()?->isSuperAdmin())
                <a href="{{ route('stock-transfers.create', ['to_location_id' => $location->id]) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Products to Branch</span>
                </a>
            @endif
            @can('update', $location)
                <a href="{{ route('branches.edit', $location) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-svg.pencil class="size-4" />
                    <span>Edit</span>
                </a>
            @endcan
            <a href="{{ route('branches.index') }}"
               class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs">
                <x-svg.arrow-left class="size-4" />
                <span>Back to Branches</span>
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Products</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($summary['product_count']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Distinct products with stock here</p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Quantity on Hand</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($summary['total_quantity']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="cart" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Total units in stock</p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Incoming Transfers</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($summary['incoming_transfers']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="truck" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Stock received at this branch</p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Outgoing Transfers</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($summary['outgoing_transfers']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="trips" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Stock dispatched from this branch</p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Total Sales</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ \App\Helpers\Money::format($summary['total_sales']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">Posted invoice value</p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Sales Returns</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($summary['sales_returns_count']) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-svg.x class="size-5" />
                </div>
            </div>
            <p class="mt-2 text-xs text-muted">{{ \App\Helpers\Money::format($summary['sales_returns_value']) }} refunded</p>
        </div>
    </div>

    {{-- Stock / Products --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line">
            <h2 class="text-base sm:text-lg font-bold text-ink">Stock at this Branch</h2>
            <p class="text-xs text-muted mt-0.5">{{ $data['stock']->count() }} {{ Str::plural('product', $data['stock']->count()) }} with stock on hand</p>
        </div>
        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5">Variant</th>
                        <th class="py-3.5 px-5">Unit</th>
                        <th class="py-3.5 px-5 text-end">Quantity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($data['stock'] as $row)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-4 px-5 text-sm font-semibold text-ink">{{ $row['product_name'] }}</td>
                            <td class="py-4 px-5 text-sm text-muted">{{ $row['variant'] ?: '—' }}</td>
                            <td class="py-4 px-5">
                                <span class="inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-xs font-medium text-muted border border-line">
                                    {{ $row['unit_of_measure'] }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ number_format($row['quantity']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 px-6 text-center text-sm font-medium text-muted">No stock is currently held at this branch.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Transfers --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Incoming Transfers</h2>
                <p class="text-xs text-muted mt-0.5">Stock received at this branch</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Reference</th>
                            <th class="py-3 px-5">From</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['incomingTransfers'] as $transfer)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @can('view', $transfer)
                                        <a href="{{ route('stock-transfers.show', $transfer) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $transfer->reference_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $transfer->reference_no }}</span>
                                    @endcan
                                </td>
                                <td class="py-3.5 px-5 text-sm text-muted">{{ $transfer->fromLocation?->name ?? '—' }}</td>
                                <td class="py-3.5 px-5"><x-status-badge :status="$transfer->status" /></td>
                                <td class="py-3.5 px-5 text-end text-sm text-muted tabular-nums whitespace-nowrap">{{ $transfer->transfer_date->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 px-6 text-center text-sm font-medium text-muted">No incoming transfers yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Outgoing Transfers</h2>
                <p class="text-xs text-muted mt-0.5">Stock dispatched from this branch</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Reference</th>
                            <th class="py-3 px-5">To</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['outgoingTransfers'] as $transfer)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @can('view', $transfer)
                                        <a href="{{ route('stock-transfers.show', $transfer) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $transfer->reference_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $transfer->reference_no }}</span>
                                    @endcan
                                </td>
                                <td class="py-3.5 px-5 text-sm text-muted">{{ $transfer->toLocation?->name ?? '—' }}</td>
                                <td class="py-3.5 px-5"><x-status-badge :status="$transfer->status" /></td>
                                <td class="py-3.5 px-5 text-end text-sm text-muted tabular-nums whitespace-nowrap">{{ $transfer->transfer_date->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 px-6 text-center text-sm font-medium text-muted">No outgoing transfers yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sales --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Daily Sales</h2>
                <p class="text-xs text-muted mt-0.5">Posted invoices grouped by date</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Date</th>
                            <th class="py-3 px-5 text-center">Invoices</th>
                            <th class="py-3 px-5 text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['salesByDay'] as $day)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5 text-sm font-semibold text-ink whitespace-nowrap">{{ $day['date']->format('d M Y') }}</td>
                                <td class="py-3.5 px-5 text-center text-sm text-muted tabular-nums">{{ number_format($day['count']) }}</td>
                                <td class="py-3.5 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($day['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-12 px-6 text-center text-sm font-medium text-muted">No posted sales yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Recent Sales Invoices</h2>
                <p class="text-xs text-muted mt-0.5">Latest posted invoices at this branch</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Invoice</th>
                            <th class="py-3 px-5">Customer</th>
                            <th class="py-3 px-5 text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['recentInvoices'] as $invoice)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @can('view', $invoice)
                                        <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $invoice->invoice_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $invoice->invoice_no }}</span>
                                    @endcan
                                    <div class="text-[11px] text-muted tabular-nums">{{ $invoice->invoice_date->format('d/m/Y') }}</div>
                                </td>
                                <td class="py-3.5 px-5 text-sm text-muted">{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</td>
                                <td class="py-3.5 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->grand_total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-12 px-6 text-center text-sm font-medium text-muted">No sales invoices posted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Returns --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Sales Returns</h2>
                <p class="text-xs text-muted mt-0.5">Goods returned by customers at this branch</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Reference</th>
                            <th class="py-3 px-5">Date</th>
                            <th class="py-3 px-5 text-end">Value</th>
                            <th class="py-3 px-5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['salesReturns'] as $salesReturn)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @can('view', $salesReturn)
                                        <a href="{{ route('sales-returns.show', $salesReturn) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $salesReturn->return_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $salesReturn->return_no }}</span>
                                    @endcan
                                </td>
                                <td class="py-3.5 px-5 text-sm text-muted tabular-nums whitespace-nowrap">{{ $salesReturn->return_date->format('d/m/Y') }}</td>
                                <td class="py-3.5 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($salesReturn->refund_amount) }}</td>
                                <td class="py-3.5 px-5"><x-status-badge :status="$salesReturn->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 px-6 text-center text-sm font-medium text-muted">No sales returns yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base font-bold text-ink">Purchase Returns</h2>
                <p class="text-xs text-muted mt-0.5">Goods returned to suppliers from this branch</p>
            </div>
            <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                            <th class="py-3 px-5">Reference</th>
                            <th class="py-3 px-5">Date</th>
                            <th class="py-3 px-5 text-end">Value</th>
                            <th class="py-3 px-5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($data['purchaseReturns'] as $purchaseReturn)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    @can('view', $purchaseReturn)
                                        <a href="{{ route('purchase-returns.show', $purchaseReturn) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $purchaseReturn->reference_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $purchaseReturn->reference_no }}</span>
                                    @endcan
                                </td>
                                <td class="py-3.5 px-5 text-sm text-muted tabular-nums whitespace-nowrap">{{ $purchaseReturn->return_date->format('d/m/Y') }}</td>
                                <td class="py-3.5 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($purchaseReturn->grand_total) }}</td>
                                <td class="py-3.5 px-5"><x-status-badge :status="$purchaseReturn->payment_status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 px-6 text-center text-sm font-medium text-muted">No purchase returns yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Staff --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line">
            <h2 class="text-base font-bold text-ink">Staff</h2>
            <p class="text-xs text-muted mt-0.5">{{ $data['staff']->count() }} {{ Str::plural('user', $data['staff']->count()) }} assigned to this branch</p>
        </div>
        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                        <th class="py-3.5 px-5">Name</th>
                        <th class="py-3.5 px-5">Role</th>
                        <th class="py-3.5 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($data['staff'] as $staff)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 text-xs font-bold">
                                        {{ $staff->initials }}
                                    </div>
                                    <div>
                                        <span class="block text-sm font-semibold text-ink">{{ $staff->name }}</span>
                                        <span class="block text-xs text-muted">{{ $staff->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-5 text-sm text-muted">{{ $staff->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="py-4 px-5"><x-status-badge :status="$staff->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-12 px-6 text-center text-sm font-medium text-muted">No staff are assigned to this branch yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line">
            <h2 class="text-base font-bold text-ink">Recent Activity</h2>
            <p class="text-xs text-muted mt-0.5">Latest stock movements recorded at this branch</p>
        </div>
        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                        <th class="py-3.5 px-5">Type</th>
                        <th class="py-3.5 px-5 text-end">Quantity</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5">Note</th>
                        <th class="py-3.5 px-5 text-end">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($data['movements'] as $movement)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-4 px-5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $movement->quantity >= 0 ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                    {{ $movement->type->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-end text-sm font-bold tabular-nums {{ $movement->quantity >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                            </td>
                            <td class="py-4 px-5 text-sm text-ink">{{ $movement->unit?->displayName() ?? 'Unknown product' }}</td>
                            <td class="py-4 px-5 text-sm text-muted max-w-xs truncate">{{ $movement->note ?: '—' }}</td>
                            <td class="py-4 px-5 text-end text-sm text-muted tabular-nums whitespace-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 px-6 text-center text-sm font-medium text-muted">No stock activity recorded at this branch yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
