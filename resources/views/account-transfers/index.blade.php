@extends('layouts.app')

@section('title', 'Account Transfers — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Transfers')

@section('content')
@php
    $currentSearch = (string) request('search', '');
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
                <span class="font-medium text-ink">Transfers</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Account Transfers</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($transfers->total()) }} {{ Str::plural('transfer', $transfers->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Move money between your own cash boxes and bank accounts. Transfers are queued as pending and move both balances once approved.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request()->hasAny(['search', 'status', 'account_id', 'date_from', 'date_to']))
                <a href="{{ route('account-transfers.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <x-svg.x class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\AccountTransfer::class)
                <a href="{{ route('account-transfers.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Transfer</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Transfer KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Transfers This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($monthCount) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="trips" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">All transfer requests recorded in {{ now()->format('F Y') }}</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Moved This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($monthCompleted) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="arrow-up-right" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Completed transfers between your own accounts</div>
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
            <div class="mt-3 text-xs text-muted">Pending transfers do not move money yet</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Accounts</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($accounts->count()) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="wallet" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Eligible sources and destinations for transfers</div>
        </div>
    </div>

    {{-- Transfers Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Transfer Register</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($transfers->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $transfers->firstItem() }}–{{ $transfers->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($transfers->total()) }}</span> records
                    @else
                        No transfers match the current filters
                    @endif
                </p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <form method="GET" action="{{ route('account-transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div class="relative">
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Search</label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text" name="search" value="{{ $currentSearch }}"
                               placeholder="Reference, note..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-3 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                    </div>
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
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">From</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">To</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Amount</th>
                        <th scope="col" class="py-3.5 px-5 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($transfers as $transfer)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start text-sm text-ink tabular-nums whitespace-nowrap">{{ $transfer->transfer_date->format('d M Y') }}</td>
                            <td class="py-4 px-5 text-start">
                                <span class="text-sm font-semibold text-ink">{{ $transfer->reference_no ?: '—' }}</span>
                                @if($transfer->note)
                                    <span class="block text-xs text-muted max-w-[180px] truncate" title="{{ $transfer->note }}">{{ $transfer->note }}</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted">{{ $transfer->fromAccount?->name ?? '—' }}</td>
                            <td class="py-4 px-5 text-start text-sm text-muted">{{ $transfer->toAccount?->name ?? '—' }}</td>
                            <td class="py-4 px-5 text-end text-sm font-bold tabular-nums whitespace-nowrap text-ink">{{ \App\Helpers\Money::format($transfer->amount) }}</td>
                            <td class="py-4 px-5 text-center">
                                <x-status-badge :status="$transfer->status->value" :label="$transfer->status->label()" />
                            </td>
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('approve', $transfer)
                                        <form method="POST" action="{{ route('account-transfers.approve', $transfer) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-success/30 bg-success/10 px-3 py-1.5 text-xs font-semibold text-success hover:bg-success hover:text-on-brand transition-colors cursor-pointer">
                                                <x-svg.check class="size-3.5" />
                                                <span>Approve</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('account-transfers.reject', $transfer) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-danger hover:text-danger transition-colors cursor-pointer">
                                                <span>Reject</span>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('update', $transfer)
                                        <a href="{{ route('account-transfers.edit', $transfer) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                    @endcan
                                    @can('delete', $transfer)
                                        <x-confirm-delete
                                            :action="route('account-transfers.destroy', $transfer)"
                                            :message="'Delete transfer ' . ($transfer->reference_no ?: '#' . $transfer->id) . '? Completed transfers restore both balances.'"
                                            confirm-text="Delete Transfer"
                                            label="Delete" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 px-6 text-center">
                                <x-empty-state message="No account transfers found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $transfers->links() }}
        </div>
    </div>
</div>
@endsection
