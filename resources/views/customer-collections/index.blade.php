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
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Customer Collections</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Payments received against sales invoices with printable receipts.</p>
        </div>

        @can('create', \App\Models\CustomerCollection::class)
            <a href="{{ route('customer-collections.create') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors">
                <x-icon name="plus" class="size-4" />
                <span>Record Payment</span>
            </a>
        @endcan
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="wallet" class="size-5" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted">Collections</p>
                    <p class="text-xl font-extrabold text-ink tabular-nums">{{ $totals['total'] }}</p>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-success/20 bg-success/5 p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Total Collected</p>
            <p class="mt-1 text-xl font-extrabold text-success tabular-nums">{{ \App\Helpers\Money::format($totals['amount']) }}</p>
            <p class="mt-3 text-[11px] text-muted">Across the filtered range</p>
        </div>
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Allocation</p>
            <p class="mt-1 text-sm font-semibold text-ink leading-relaxed">Every payment is allocated to posted invoices and reduces their due immediately.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('customer-collections.index') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Receipt no, reference, customer..."
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Method</label>
                <select name="method" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach(\App\Enums\PaymentMethod::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if(auth()->user()?->hasRole('Super Admin'))
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
                <a href="{{ route('customer-collections.index') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
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
                        <th class="py-3.5 px-5">Receipt No</th>
                        <th class="py-3.5 px-5">Date</th>
                        <th class="py-3.5 px-5">Customer</th>
                        <th class="py-3.5 px-5">Method</th>
                        <th class="py-3.5 px-5">Invoices</th>
                        <th class="py-3.5 px-5">Location</th>
                        <th class="py-3.5 px-5 text-right">Amount</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($collections as $collection)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5">
                                <a href="{{ route('customer-collections.show', $collection) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">
                                    {{ $collection->collection_no }}
                                </a>
                            </td>
                            <td class="py-3.5 px-5 text-sm text-ink tabular-nums">{{ $collection->collection_date->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 px-5 text-sm text-ink">{{ $collection->customer?->name ?? '—' }}</td>
                            <td class="py-3.5 px-5"><x-status-badge :status="'active'" :label="$collection->method->label()" /></td>
                            <td class="py-3.5 px-5 text-sm text-muted tabular-nums">{{ $collection->allocations_count ?? $collection->allocations->count() }} invoice(s)</td>
                            <td class="py-3.5 px-5 text-sm text-muted">{{ $collection->location?->name ?? '—' }}</td>
                            <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($collection->amount) }}</td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('customer-collections.show', $collection) }}" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">View</a>
                                    <a href="{{ route('customer-collections.receipt', $collection) }}" target="_blank" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors">Receipt</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12">
                                <x-empty-state message="No collections found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-line px-5 py-4">
            <x-pagination :paginator="$collections" />
        </div>
    </div>
</div>
@endsection
