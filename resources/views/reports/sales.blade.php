@extends('layouts.app')

@section('title', 'Sales Report — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Report')

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
                <span class="text-muted">Reports</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Sales Report</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Sales Report</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Invoice-wise sales with totals and payment status breakdown for the selected range.</p>
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
                    <p class="text-xl font-extrabold text-ink tabular-nums">{{ $totals['count'] }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Sales Value</p>
            <p class="mt-1 text-xl font-extrabold text-ink tabular-nums">{{ \App\Helpers\Money::format($totals['grand_total']) }}</p>
            @if($totals['credit_amount'] > 0)
                <p class="mt-3 text-[11px] text-muted">Return credits: {{ \App\Helpers\Money::format($totals['credit_amount']) }}</p>
            @endif
        </div>
        <div class="rounded-3xl border border-success/20 bg-success/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Collected</p>
            <p class="mt-1 text-xl font-extrabold text-success tabular-nums">{{ \App\Helpers\Money::format($totals['paid_amount']) }}</p>
        </div>
        <div class="rounded-3xl border border-danger/20 bg-danger/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Outstanding</p>
            <p class="mt-1 text-xl font-extrabold text-danger tabular-nums">{{ \App\Helpers\Money::format($totals['due_amount']) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('reports.sales') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Payment Status</label>
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
                <a href="{{ route('reports.sales') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Invoice-wise listing --}}
        <div class="xl:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base sm:text-lg font-bold text-ink">Invoice-wise Sales</h2>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-5">Invoice</th>
                            <th class="py-3 px-5">Date</th>
                            <th class="py-3 px-5">Customer</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-right">Total</th>
                            <th class="py-3 px-5 text-right">Paid</th>
                            <th class="py-3 px-5 text-right">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($invoices as $invoice)
                            <tr class="hover:bg-soft/40 transition-colors">
                                <td class="py-3 px-5">
                                    @can('view', $invoice)
                                        <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">{{ $invoice->invoice_no }}</a>
                                    @else
                                        <span class="text-sm font-semibold text-ink tabular-nums">{{ $invoice->invoice_no }}</span>
                                    @endcan
                                </td>
                                <td class="py-3 px-5 text-sm text-ink tabular-nums">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                <td class="py-3 px-5 text-sm text-ink">{{ $invoice->customer?->name ?? 'Walk-in' }}</td>
                                <td class="py-3 px-5"><x-status-badge :status="$invoice->payment_status->badge()" :label="$invoice->payment_status->label()" /></td>
                                <td class="py-3 px-5 text-right text-sm font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->grand_total) }}</td>
                                <td class="py-3 px-5 text-right text-sm text-success tabular-nums">{{ \App\Helpers\Money::format($invoice->paid_amount) }}</td>
                                <td class="py-3 px-5 text-right text-sm font-semibold text-danger tabular-nums">{{ \App\Helpers\Money::format($invoice->due_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12">
                                    <x-empty-state message="No invoices in the selected range." />
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

        {{-- Breakdowns --}}
        <div class="space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Payment Status Breakdown</h2>
                <ul class="space-y-3">
                    @foreach(['due', 'partial', 'paid', 'cancelled'] as $status)
                        @php $row = $paymentBreakdown->get($status); @endphp
                        <li class="rounded-2xl border border-line/70 bg-soft/40 p-3.5">
                            <div class="flex items-center justify-between">
                                <x-status-badge :status="$status" :label="\App\Enums\SalesPaymentStatus::from($status)->label()" />
                                <span class="text-sm font-bold text-ink tabular-nums">{{ $row->total ?? 0 }} invoice(s)</span>
                            </div>
                            <div class="mt-2 grid grid-cols-3 gap-2 text-[11px] text-muted">
                                <div>
                                    <span class="block font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($row->grand_total ?? 0) }}</span>
                                    Total
                                </div>
                                <div>
                                    <span class="block font-semibold text-success tabular-nums">{{ \App\Helpers\Money::format($row->paid_amount ?? 0) }}</span>
                                    Paid
                                </div>
                                <div>
                                    <span class="block font-semibold text-danger tabular-nums">{{ \App\Helpers\Money::format($row->due_amount ?? 0) }}</span>
                                    Due
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Document Status Breakdown</h2>
                <ul class="space-y-2 text-sm">
                    @foreach(['draft', 'posted', 'voided'] as $status)
                        @php $row = $statusBreakdown->get($status); @endphp
                        <li class="flex items-center justify-between rounded-2xl border border-line/70 bg-soft/40 px-3.5 py-2.5">
                            <x-status-badge :status="$status" :label="\App\Enums\SalesInvoiceStatus::from($status)->label()" />
                            <div class="text-right">
                                <span class="font-bold text-ink tabular-nums">{{ $row->total ?? 0 }}</span>
                                <span class="text-[11px] text-muted">· {{ \App\Helpers\Money::format($row->grand_total ?? 0) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
