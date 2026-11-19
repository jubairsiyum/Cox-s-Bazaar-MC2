@extends('layouts.app')

@section('title', 'Sales Invoices — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Invoices')

@section('content')
@php
    $exportParams = array_filter(
        request()->only(['search', 'customer_id', 'status', 'payment_status', 'date_from', 'date_to', 'branch_id', 'location_id']),
        fn ($value) => $value !== null && $value !== '',
    );
@endphp
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
                <span class="font-medium text-ink">Sales Invoices</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Sales Invoices</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Invoices generated from sales orders, with post/void workflow and payment tracking. POS sales are listed under Collections.</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Segmented Export Bar --}}
            <div class="inline-flex items-center rounded-xl border border-line bg-surface p-0.5 shadow-2xs">
                <a href="{{ route('sales-invoices.export', array_merge($exportParams, ['format' => 'csv'])) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                   title="Export sales invoices to CSV">
                    <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>CSV</span>
                </a>
                <span class="h-4 w-px bg-line"></span>
                <a href="{{ route('sales-invoices.export', array_merge($exportParams, ['format' => 'xlsx'])) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                   title="Export sales invoices to Excel">
                    <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Excel</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="enquiries" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">Invoices</p>
                    <p class="text-xl font-extrabold text-ink tabular-nums">{{ $totals['total'] }}</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-muted">Draft {{ $totals['draft'] }} · Posted {{ $totals['posted'] }} · Voided {{ $totals['voided'] }}</p>
        </div>
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Grand Total</p>
            <p class="mt-1 text-xl font-extrabold text-ink tabular-nums">{{ \App\Helpers\Money::format($totals['grand_total']) }}</p>
            <p class="mt-3 text-[11px] text-muted">Incl. tax of the filtered range</p>
        </div>
        <div class="rounded-3xl border border-success/20 bg-success/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Collected</p>
            <p class="mt-1 text-xl font-extrabold text-success tabular-nums">{{ \App\Helpers\Money::format($totals['paid_amount']) }}</p>
            <p class="mt-3 text-[11px] text-muted">Paid {{ $totals['paid'] }} · Partial {{ $totals['partial'] }}</p>
        </div>
        <div class="rounded-3xl border border-danger/20 bg-danger/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Outstanding</p>
            <p class="mt-1 text-xl font-extrabold text-danger tabular-nums">{{ \App\Helpers\Money::format($totals['due_amount']) }}</p>
            <p class="mt-3 text-[11px] text-muted">Due invoices {{ $totals['due'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('sales-invoices.index') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice no, customer..."
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Customer</label>
                <select name="customer_id" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
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
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Branch</label>
                <select name="branch_id" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Location</label>
                <select name="location_id" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs font-semibold text-on-brand transition-colors cursor-pointer">
                    <x-icon name="search" class="size-4" />
                    Filter
                </button>
                <a href="{{ route('sales-invoices.index') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Register Table --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5">Invoice No</th>
                        <th class="py-3.5 px-5">Date</th>
                        <th class="py-3.5 px-5">Customer</th>
                        <th class="py-3.5 px-5">Location</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5">Payment</th>
                        <th class="py-3.5 px-5 text-right">Total</th>
                        <th class="py-3.5 px-5 text-right">Paid</th>
                        <th class="py-3.5 px-5 text-right">Due</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5">
                                <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">
                                    {{ $invoice->invoice_no }}
                                </a>
                            </td>
                            <td class="py-3.5 px-5 text-sm text-ink tabular-nums">{{ $invoice->invoice_date->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 px-5 text-sm text-ink">{{ $invoice->customer?->name ?? 'Walk-in' }}</td>
                            <td class="py-3.5 px-5 text-sm text-muted">{{ $invoice->location?->name ?? '—' }}</td>
                            <td class="py-3.5 px-5"><x-status-badge :status="$invoice->status->value" :label="$invoice->status->label()" /></td>
                            <td class="py-3.5 px-5"><x-status-badge :status="$invoice->payment_status->badge()" :label="$invoice->payment_status->label()" /></td>
                            <td class="py-3.5 px-5 text-right text-sm font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->grand_total) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm text-success tabular-nums">{{ \App\Helpers\Money::format($invoice->paid_amount) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm font-semibold text-danger tabular-nums">{{ \App\Helpers\Money::format($invoice->payment_due) }}</td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">View</a>
                                    <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">Print</a>
                                    {{-- Edit/Delete only for draft invoices: the rule lives in the
                                         policy, but the Super Admin's Gate bypass makes @can always
                                         true, so the document state is checked explicitly too. --}}
                                    @if($invoice->status->isEditable())
                                        @can('update', $invoice)
                                            <a href="{{ route('sales-invoices.edit', $invoice) }}" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">Edit</a>
                                        @endcan
                                    @endif
                                    @can('post', $invoice)
                                        @if($invoice->status->value === 'draft')
                                            <form method="POST" action="{{ route('sales-invoices.post', $invoice) }}" class="inline" onsubmit="return confirm('Post this invoice? Stock will be issued.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg border border-brand-500/30 bg-brand-500/10 px-2.5 py-1 text-[11px] font-semibold text-brand-500 hover:bg-brand-500/15 transition-colors cursor-pointer">Post</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('void', $invoice)
                                        @if($invoice->status->value === 'posted')
                                            <form method="POST" action="{{ route('sales-invoices.void', $invoice) }}" class="inline" onsubmit="return confirm('Void this invoice? Stock will be restored.')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-lg border border-danger/30 bg-danger/10 px-2.5 py-1 text-[11px] font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">Void</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @if($invoice->status->isEditable())
                                        @can('delete', $invoice)
                                            <form method="POST" action="{{ route('sales-invoices.destroy', $invoice) }}" class="inline" onsubmit="return confirm('Delete this draft invoice? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-danger/30 px-2.5 py-1 text-[11px] font-semibold text-danger hover:bg-danger/10 transition-colors cursor-pointer">Delete</button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12">
                                <x-empty-state message="No sales invoices found." />
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
