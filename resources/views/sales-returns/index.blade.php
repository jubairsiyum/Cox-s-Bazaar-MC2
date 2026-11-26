@extends('layouts.app')

@section('title', 'Sales Returns — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Returns')

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
                <span class="font-medium text-ink">Sales Returns</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Sales Returns</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Customer returns with branch-manager approval, invoice credits and restock control.</p>
        </div>

        @can('create', \App\Models\SalesReturn::class)
            <a href="{{ route('sales-returns.create') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors">
                <x-icon name="plus" class="size-4" />
                <span>Apply Return</span>
            </a>
        @endcan
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="enquiries" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">Returns</p>
                    <p class="text-xl font-extrabold text-ink tabular-nums">{{ $totals['total'] }}</p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-muted">Approved {{ $totals['approved'] }} · Restocked {{ $totals['completed'] }} · Rejected {{ $totals['rejected'] }}</p>
        </div>
        <div class="rounded-3xl border border-warning/20 bg-warning/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Awaiting Approval</p>
            <p class="mt-1 text-xl font-extrabold text-warning tabular-nums">{{ $totals['pending'] }}</p>
            <p class="mt-3 text-[11px] text-muted">Pending branch manager review</p>
        </div>
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Total Refund / Credit</p>
            <p class="mt-1 text-xl font-extrabold text-ink tabular-nums">{{ \App\Helpers\Money::format($totals['refund_amount']) }}</p>
            <p class="mt-3 text-[11px] text-muted">Across the filtered range</p>
        </div>
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Approved Effects</p>
            <p class="mt-1 text-sm font-semibold text-ink leading-relaxed">Invoice credits + stock restock apply only after approval.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('sales-returns.index') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Return no, invoice no, customer..."
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Branch</label>
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
                <a href="{{ route('sales-returns.index') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
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
                        <th class="py-3.5 px-5">Return No</th>
                        <th class="py-3.5 px-5">Date</th>
                        <th class="py-3.5 px-5">Parent Invoice</th>
                        <th class="py-3.5 px-5">Customer</th>
                        <th class="py-3.5 px-5">Location</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5 text-right">Refund</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($returns as $return)
                        @php $displayStatus = $return->displayStatusFor(auth()->user()); @endphp
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5">
                                <a href="{{ route('sales-returns.show', $return) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">
                                    {{ $return->return_no }}
                                </a>
                            </td>
                            <td class="py-3.5 px-5 text-sm text-ink tabular-nums">{{ $return->return_date->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 px-5 text-sm text-ink tabular-nums">{{ $return->salesInvoice?->invoice_no ?? '—' }}</td>
                            <td class="py-3.5 px-5 text-sm text-ink">{{ $return->customer?->name ?? $return->salesInvoice?->customer?->name ?? 'Walk-in' }}</td>
                            <td class="py-3.5 px-5 text-sm text-muted">{{ $return->location?->name ?? '—' }}</td>
                            <td class="py-3.5 px-5"><x-status-badge :status="$displayStatus['key']" :label="$displayStatus['label']" /></td>
                            <td class="py-3.5 px-5 text-right text-sm font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($return->refund_amount) }}</td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('sales-returns.show', $return) }}" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">View</a>
                                    @if($return->canBeApprovedBy(auth()->user()))
                                        <form method="POST" action="{{ route('sales-returns.approve', $return) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg border border-success/30 bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success hover:bg-success/15 transition-colors cursor-pointer">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('sales-returns.reject', $return) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-lg border border-danger/30 bg-danger/10 px-2.5 py-1 text-[11px] font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12">
                                <x-empty-state message="No sales returns found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-line px-5 py-4">
            <x-pagination :paginator="$returns" />
        </div>
    </div>
</div>
@endsection
