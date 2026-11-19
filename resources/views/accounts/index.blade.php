@extends('layouts.app')

@section('title', ($type->value === 'cash' ? 'Cash Accounts' : 'Bank Accounts') . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', $type->value === 'cash' ? 'Cash Accounts' : 'Bank Accounts')

@section('content')
@php
    $isCash = $type === \App\Enums\AccountType::Cash;
    $sectionTitle = $isCash ? 'Cash Accounts' : 'Bank Accounts';
    $indexRoute = $isCash ? 'cash-accounts.index' : 'bank-accounts.index';
    $createRoute = $isCash ? 'cash-accounts.create' : 'bank-accounts.create';
    $currentSearch = (string) request('search', '');
    $currentStatus = (string) request('status', '');
    $typeTotal = $isCash ? $cashTotal : $bankTotal;
    $otherTotal = $isCash ? $bankTotal : $cashTotal;
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
                <span class="font-medium text-ink">{{ $sectionTitle }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $sectionTitle }}</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($accounts->total()) }} {{ Str::plural('account', $accounts->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                {{ $isCash
                    ? 'Manage the shop\'s cash boxes and counter tills used for daily income, expenses and supplier payments.'
                    : 'Manage business bank accounts used for transfers, large settlements and supplier payments.' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route($indexRoute) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <x-svg.x class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif

            @can('create', [App\Models\Account::class, $type])
                <a href="{{ route($createRoute) }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add {{ $isCash ? 'Cash' : 'Bank' }} Account</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- 4-Card Balance KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ $isCash ? 'Total Cash Balance' : 'Total Bank Balance' }}</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($typeTotal) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon :name="$isCash ? 'wallet' : 'payments'" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Across all {{ $isCash ? 'cash boxes' : 'bank accounts' }}, active and inactive</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ $isCash ? 'Bank Balance' : 'Cash Balance' }}</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($otherTotal) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon :name="$isCash ? 'payments' : 'wallet'" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                Held in {{ $isCash ? 'bank' : 'cash' }} accounts · Total <strong class="text-ink font-semibold">{{ \App\Helpers\Money::format($cashTotal + $bankTotal) }}</strong>
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Active Accounts</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($activeCount) }}
                        <span class="text-sm font-semibold text-muted">/ {{ number_format($totalCount) }}</span>
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Active accounts across both cash and bank sections</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Net Movement (This Month)</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums truncate {{ $monthNet >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ \App\Helpers\Money::format($monthNet) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="trend-up" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted tabular-nums">
                In <strong class="text-success font-semibold">{{ \App\Helpers\Money::format($monthIncome) }}</strong>
                · Out <strong class="text-danger font-semibold">{{ \App\Helpers\Money::format($monthExpense) }}</strong>
            </div>
        </div>
    </div>

    {{-- Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">All {{ strtolower($sectionTitle) }}</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($accounts->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $accounts->firstItem() }}–{{ $accounts->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($accounts->total()) }}</span> records
                    @else
                        No accounts match the current filters
                    @endif
                </p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3.5">
                <form method="GET" action="{{ route($indexRoute) }}" class="flex items-center gap-2 shrink-0">
                    @if($currentSearch !== '')<input type="hidden" name="search" value="{{ $currentSearch }}">@endif
                    @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
                    <label class="text-muted flex items-center gap-1.5 text-xs font-medium">
                        <span>Show</span>
                        <select name="per_page" onchange="this.form.submit()"
                                class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                            <option value="100" @selected($perPage == 100)>100</option>
                        </select>
                        <span>entries</span>
                    </label>
                </form>

                <form method="GET" action="{{ route($indexRoute) }}" class="relative flex-1 max-w-md">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text" name="search" value="{{ $currentSearch }}"
                               placeholder="Search name, account number, bank..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                        @if($currentSearch !== '')
                            <a href="{{ route($indexRoute, array_filter(['per_page' => request('per_page'), 'status' => $currentStatus !== '' ? $currentStatus : null])) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer" title="Clear search">
                                <x-svg.x class="size-3.5" />
                            </a>
                        @endif
                    </div>
                </form>

                <form method="GET" action="{{ route($indexRoute) }}" class="flex items-center gap-2 shrink-0">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if($currentSearch !== '')<input type="hidden" name="search" value="{{ $currentSearch }}">@endif
                    <select name="status" onchange="this.form.submit()"
                            class="rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All statuses</option>
                        <option value="active" @selected($currentStatus === 'active')>Active</option>
                        <option value="inactive" @selected($currentStatus === 'inactive')>Inactive</option>
                    </select>
                </form>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Account</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Account No.</th>
                        @if(! $isCash)
                            <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Bank / Branch</th>
                        @endif
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Opening</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Current Balance</th>
                        <th scope="col" class="py-3.5 px-5 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        @canany(['finance.manage', $isCash ? 'cash-accounts.manage' : 'bank-accounts.manage'])
                            <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $isCash ? 'bg-brand-500/10 text-brand-500' : 'bg-warning/10 text-warning' }}">
                                        <x-icon :name="$isCash ? 'wallet' : 'payments'" class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors truncate">{{ $account->name }}</span>
                                        @if($account->location)
                                            <span class="block text-xs text-muted truncate">{{ $account->location->name }}</span>
                                        @elseif($account->note)
                                            <span class="block text-xs text-muted truncate">{{ $account->note }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted tabular-nums">{{ $account->account_number ?: '—' }}</td>
                            @if(! $isCash)
                                <td class="py-4 px-5 text-start text-sm text-muted">
                                    {{ $account->bank_name ?: '—' }}
                                    @if($account->bank_branch)
                                        <span class="block text-xs text-muted/80">{{ $account->bank_branch }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="py-4 px-5 text-end text-sm text-muted tabular-nums whitespace-nowrap">{{ \App\Helpers\Money::format($account->opening_balance) }}</td>
                            <td class="py-4 px-5 text-end text-sm font-bold tabular-nums whitespace-nowrap {{ (float) $account->current_balance >= 0 ? 'text-ink' : 'text-danger' }}">
                                {{ \App\Helpers\Money::format($account->current_balance) }}
                            </td>
                            <td class="py-4 px-5 text-center">
                                <x-status-badge :status="$account->status" />
                            </td>
                            @canany(['finance.manage', $isCash ? 'cash-accounts.manage' : 'bank-accounts.manage'])
                                <td class="py-4 px-5 text-end whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        @can('update', $account)
                                            <a href="{{ route($isCash ? 'cash-accounts.edit' : 'bank-accounts.edit', $account) }}"
                                               class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                                <x-svg.pencil class="size-3.5 text-muted" />
                                                <span>Edit</span>
                                            </a>
                                        @endcan
                                        @can('delete', $account)
                                            <x-confirm-delete
                                                :action="route($isCash ? 'cash-accounts.destroy' : 'bank-accounts.destroy', $account)"
                                                :message="'Delete account ' . $account->name . '? This cannot be undone.'"
                                                confirm-text="Delete Account"
                                                label="Delete" />
                                        @endcan
                                    </div>
                                </td>
                            @endcanany
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isCash ? 6 : 7 }}" class="py-8 px-6 text-center">
                                <x-empty-state :message="'No ' . strtolower($sectionTitle) . ' found.'" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $accounts->links() }}
        </div>
    </div>
</div>
@endsection
