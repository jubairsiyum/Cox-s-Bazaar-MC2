@extends('layouts.app')

@section('title', 'Collections — ' . config('app.name', "Cox's Bazaar Mobile Center"))
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
                <span class="font-medium text-ink">Collections</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Collections</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">All distributor invoices — generated from sales orders or rung up on the POS.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('collections.index') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice no, customer..."
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Status</label>
                <select name="status" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Payment</label>
                <select name="payment_status" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    <option value="due" @selected(request('payment_status') === 'due')>Due</option>
                    <option value="partial" @selected(request('payment_status') === 'partial')>Partial</option>
                    <option value="paid" @selected(request('payment_status') === 'paid')>Paid</option>
                    <option value="cancelled" @selected(request('payment_status') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            @if($canFilterBranch && auth()->user()?->hasRole('Super Admin'))
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Branch</label>
                    <select name="branch_id" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs font-semibold text-on-brand transition-colors cursor-pointer">
                    <x-icon name="search" class="size-4" />
                    Filter
                </button>
                <a href="{{ route('collections.index') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Distributor Invoices Table --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5">Invoice #</th>
                        <th class="py-3.5 px-5">Retailer / Route</th>
                        <th class="py-3.5 px-5 hidden md:table-cell">Items</th>
                        <th class="py-3.5 px-5 text-right">Amount</th>
                        <th class="py-3.5 px-5">Payment</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($invoices as $invoice)
                        @php
                            $customer = $invoice->customer;
                            $retailerName = $customer?->name ?? 'Walk-in';
                            $area = filled($customer?->city) ? $customer->city : $invoice->location?->name;
                            $itemsList = $invoice->items;
                            $extraItems = max(0, $itemsList->count() - 2);
                            $itemsSummary = $itemsList->isEmpty()
                                ? '—'
                                : $itemsList->take(2)->map(fn ($item) => ($item->unit?->displayName() ?? 'Unknown product') . ' (' . $item->quantity . 'x)')->implode(', ')
                                    . ($extraItems > 0 ? ' … (+' . $extraItems . ' more)' : '');
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">
                                    {{ $invoice->invoice_no }}
                                </a>
                                <span class="block text-[11px] text-muted">{{ $invoice->invoice_date?->format('d/m/Y H:i') ?? '—' }}</span>
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="text-sm font-semibold text-ink">{{ $retailerName }}</div>
                                <div class="text-xs text-muted">{{ $area ?? '—' }}</div>
                            </td>
                            <td class="py-3.5 px-5 hidden md:table-cell max-w-xs text-xs text-ink/80">
                                <span class="block truncate">{{ $itemsSummary }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                <div class="text-sm font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->grand_total) }}</div>
                                @if((float) $invoice->due_amount > 0)
                                    <div class="text-[11px] text-danger tabular-nums">Due {{ \App\Helpers\Money::format($invoice->due_amount) }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <x-status-badge :status="$invoice->payment_status->badge()" :label="$invoice->payment_status->label()" />
                                @if($invoice->status === \App\Enums\SalesInvoiceStatus::Draft)
                                    <span class="block mt-1 text-[10px] font-semibold text-muted">Draft</span>
                                @elseif($invoice->status === \App\Enums\SalesInvoiceStatus::Voided)
                                    <span class="block mt-1 text-[10px] font-semibold text-danger">Voided</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">View</a>
                                    <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12">
                                <x-empty-state message="No distributor invoices found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-line px-5 py-4">
            <x-pagination :paginator="$invoices" />
        </div>
    </div>
</div>
@endsection
