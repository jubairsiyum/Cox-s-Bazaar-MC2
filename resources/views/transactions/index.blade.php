@extends('layouts.app')

@section('title', 'Transactions — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Transactions')

@section('content')
@php
    $currentSearch = (string) request('search', '');
    $monthNet = $monthIncome - $monthExpense;
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
                <span class="text-muted/60">/</span>
                <span class="text-muted">Finance</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Transactions</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Transactions</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($transactions->total()) }} {{ Str::plural('entry', $transactions->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">One ledger for money in (income) and money out (expenses). Entries are queued as pending and only move balances once approved.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request()->hasAny(['search', 'type', 'status', 'account_id', 'category', 'date_from', 'date_to']))
                <a href="{{ route('transactions.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <x-svg.x class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif

            @can('create', [App\Models\AccountTransaction::class, null])
                <a href="{{ route('transactions.create', ['type' => 'income']) }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-success/30 bg-success/10 px-4 py-2.5 text-xs sm:text-sm font-semibold text-success hover:bg-success hover:text-on-brand transition-colors">
                    <x-icon name="trend-up" class="size-4" />
                    <span>Add Income</span>
                </a>
                <a href="{{ route('transactions.create', ['type' => 'expense']) }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Expense</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Money Flow KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Income This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-success mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($monthIncome) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Completed income entries, {{ now()->format('F Y') }}</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Expense This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-danger mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($monthExpense) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                    <x-icon name="cart" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Completed expense entries, {{ now()->format('F Y') }}</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Net This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums truncate {{ $monthNet >= 0 ? 'text-success' : 'text-danger' }}">{{ \App\Helpers\Money::format($monthNet) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Income minus expenses for the current month</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Awaiting Approval</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($pendingCount) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Pending entries do not affect account balances yet</div>
        </div>
    </div>

    {{-- Ledger Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Income &amp; Expense Ledger</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($transactions->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($transactions->total()) }}</span> entries
                    @else
                        No entries match the current filters
                    @endif
                </p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <form method="GET" action="{{ route('transactions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 items-end">
                <div class="relative sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Search</label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text" name="search" value="{{ $currentSearch }}"
                               placeholder="Reference, category, note..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-3 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Type</label>
                    <select name="type" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All types</option>
                        @foreach(\App\Enums\AccountTransactionType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All statuses</option>
                        @foreach(\App\Enums\AccountEntryStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Account</label>
                    <select name="account_id" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All accounts</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    </div>
                    <button type="submit"
                            class="btn-scale rounded-xl bg-brand-500 px-3.5 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Date</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Reference</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Account</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Type</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Category</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Amount</th>
                        <th scope="col" class="py-3.5 px-5 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start text-sm text-ink tabular-nums whitespace-nowrap">{{ $transaction->transaction_date->format('d M Y') }}</td>
                            <td class="py-4 px-5 text-start">
                                <span class="text-sm font-semibold text-ink">{{ $transaction->reference_no ?: '—' }}</span>
                                @if($transaction->note)
                                    <span class="block text-xs text-muted max-w-[180px] truncate" title="{{ $transaction->note }}">{{ $transaction->note }}</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted">{{ $transaction->account?->name ?? '—' }}</td>
                            <td class="py-4 px-5 text-start">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $transaction->isIncome() ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20' }}">
                                    <x-icon :name="$transaction->isIncome() ? 'trend-up' : 'cart'" class="size-3.5" />
                                    {{ $transaction->type->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted">{{ $transaction->category }}</td>
                            <td class="py-4 px-5 text-end text-sm font-bold tabular-nums whitespace-nowrap {{ $transaction->isIncome() ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->isIncome() ? '+' : '−' }}{{ \App\Helpers\Money::format($transaction->amount) }}
                            </td>
                            <td class="py-4 px-5 text-center">
                                <x-status-badge :status="$transaction->status->value" :label="$transaction->status->label()" />
                            </td>
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('approve', $transaction)
                                        <form method="POST" action="{{ route('transactions.approve', $transaction) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-success/30 bg-success/10 px-3 py-1.5 text-xs font-semibold text-success hover:bg-success hover:text-on-brand transition-colors cursor-pointer">
                                                <x-svg.check class="size-3.5" />
                                                <span>Approve</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('transactions.reject', $transaction) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-danger hover:text-danger transition-colors cursor-pointer">
                                                <span>Reject</span>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('update', $transaction)
                                        <a href="{{ route('transactions.edit', $transaction) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                    @endcan
                                    @can('delete', $transaction)
                                        <x-confirm-delete
                                            :action="route('transactions.destroy', $transaction)"
                                            :message="'Delete entry ' . ($transaction->reference_no ?: '#' . $transaction->id) . '? Completed entries restore their balance effect.'"
                                            confirm-text="Delete Entry"
                                            label="Delete" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 px-6 text-center">
                                <x-empty-state message="No income or expense entries found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection
