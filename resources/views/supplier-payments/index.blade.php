@extends('layouts.app')

@section('title', 'Supplier Payments — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Supplier Payments')

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
                <span class="font-medium text-ink">Supplier Payments</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Supplier Payments</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($payments->total()) }} {{ Str::plural('payment', $payments->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Every payment settling supplier purchase dues — paid from a cash or bank account — in one finance register.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request()->hasAny(['search', 'supplier_id', 'account_id', 'status', 'date_from', 'date_to']))
                <a href="{{ route('supplier-payments.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <x-svg.x class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif

            @if(auth()->user()?->can('supplier-payments.manage') || auth()->user()?->can('finance.manage'))
                <a href="{{ route('supplier-payments.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Record Payment</span>
                </a>
            @endif
        </div>
    </div>

    {{-- 4-Card Supplier Payment KPI Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Paid This Month</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format($monthTotal) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="payments" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">
                <strong class="text-ink font-semibold tabular-nums">{{ number_format($monthCount) }}</strong> {{ Str::plural('payment', $monthCount) }} settled in {{ now()->format('F Y') }}
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Supplier Dues Outstanding</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight mt-1.5 tabular-nums truncate {{ $totalOutstanding > 0 ? 'text-danger' : 'text-success' }}">{{ \App\Helpers\Money::format($totalOutstanding) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl {{ $totalOutstanding > 0 ? 'bg-danger/10 text-danger ring-1 ring-danger/20' : 'bg-success/10 text-success ring-1 ring-success/20' }}">
                    <x-icon name="wallet" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Total purchase due still payable to all suppliers</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Pending Requests</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">{{ number_format($pendingRequests) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                    <x-icon name="alert-triangle" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Due payment requests awaiting manager approval</div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card lift flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Filtered Total</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">{{ \App\Helpers\Money::format((float) $payments->getCollection()->sum('amount')) }}</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <div class="mt-3 text-xs text-muted">Sum of the payments shown on the current page</div>
        </div>
    </div>

    {{-- Payments Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Payment Register</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($payments->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $payments->firstItem() }}–{{ $payments->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($payments->total()) }}</span> records
                    @else
                        No supplier payments match the current filters
                    @endif
                </p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <form method="GET" action="{{ route('supplier-payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 items-end">
                <div class="relative">
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Search</label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text" name="search" value="{{ $currentSearch }}"
                               placeholder="Supplier, invoice no, note..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-3 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Supplier</label>
                    <select name="supplier_id" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All suppliers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Paid From</label>
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
                        @foreach(\App\Enums\ApprovalStatus::options() as $value => $label)
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
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Supplier</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Purchase Invoice</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Paid From</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Amount</th>
                        <th scope="col" class="py-3.5 px-5 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start text-sm text-ink tabular-nums whitespace-nowrap">
                                {{ ($payment->payment_date ?? $payment->created_at)->format('d M Y') }}
                            </td>
                            <td class="py-4 px-5 text-start">
                                <span class="block text-sm font-semibold text-ink">{{ $payment->purchaseOrder?->supplier?->name ?? '—' }}</span>
                                <span class="block text-xs text-muted">by {{ $payment->creator?->name ?? 'Unknown' }}</span>
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted tabular-nums">{{ $payment->purchaseOrder?->reference_no ?? '—' }}</td>
                            <td class="py-4 px-5 text-start text-sm text-muted">
                                {{ $payment->account?->name ?? '—' }}
                                @if($payment->method)
                                    <span class="block text-xs text-muted/80">{{ $payment->methodLabel() }}</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-end text-sm font-bold tabular-nums whitespace-nowrap text-ink">{{ \App\Helpers\Money::format($payment->amount) }}</td>
                            <td class="py-4 px-5 text-center">
                                <x-status-badge :status="$payment->status->value" :label="$payment->status->label()" />
                            </td>
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a href="{{ route('supplier-payments.show', $payment) }}"
                                       class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                        <x-svg.eye class="size-3.5 text-muted" />
                                        <span>View</span>
                                    </a>
                                    @if(auth()->user()?->can('supplier-payments.manage') || auth()->user()?->can('finance.manage'))
                                        <x-confirm-delete
                                            :action="route('supplier-payments.destroy', $payment)"
                                            :message="'Delete the payment of ' . \App\Helpers\Money::format($payment->amount) . ' against ' . ($payment->purchaseOrder?->reference_no ?? 'this purchase') . '? Applied payments restore all due balances.'"
                                            confirm-text="Delete Payment"
                                            label="Delete" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 px-6 text-center">
                                <x-empty-state message="No supplier payments found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $payments->links() }}
        </div>
    </div>

    {{-- Supplier Payment Requests (payment-request messaging from supplier profiles) --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex items-center gap-3 px-5 sm:px-6 py-5 border-b border-line">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
                <x-icon name="chat" class="size-5" />
            </div>
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Supplier Payment Requests</h2>
                <p class="text-xs text-muted">Requests suppliers sent to branches — take action here to clear the due.</p>
            </div>
        </div>

        @if($paymentRequests->isEmpty())
            <div class="px-5 sm:px-6 py-8">
                <x-empty-state message="No supplier payment requests." />
            </div>
        @else
            <div class="table-wrap max-w-full overflow-x-auto">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line text-[11px] font-bold uppercase tracking-wider text-muted">
                            <th class="py-3.5 px-5">Supplier</th>
                            <th class="py-3.5 px-5">Branch</th>
                            <th class="py-3.5 px-5">Purchase</th>
                            <th class="py-3.5 px-5">Message</th>
                            <th class="py-3.5 px-5">Amount</th>
                            <th class="py-3.5 px-5">Status</th>
                            <th class="py-3.5 px-5 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentRequests as $paymentRequest)
                            <tr class="border-b border-line/70 hover:bg-soft/40 transition-colors">
                                <td class="py-3.5 px-5">
                                    <a href="{{ route('suppliers.profile', $paymentRequest->supplier) }}" class="text-sm font-semibold text-ink hover:text-brand-500 transition-colors">
                                        {{ $paymentRequest->supplier?->name ?? 'Unknown supplier' }}
                                    </a>
                                </td>
                                <td class="py-3.5 px-5 text-xs text-muted">{{ $paymentRequest->location?->name ?? '—' }}</td>
                                <td class="py-3.5 px-5 text-xs tabular-nums">
                                    @if($paymentRequest->purchaseOrder)
                                        <a href="{{ route('purchase-invoices.show', $paymentRequest->purchaseOrder) }}" class="text-brand-500 hover:underline">
                                            {{ $paymentRequest->purchaseOrder->reference_no }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-xs text-muted max-w-64 truncate" title="{{ $paymentRequest->message }}">{{ $paymentRequest->message }}</td>
                                <td class="py-3.5 px-5 text-sm font-semibold text-ink tabular-nums">{{ $paymentRequest->amount !== null ? \App\Helpers\Money::format($paymentRequest->amount) : '—' }}</td>
                                <td class="py-3.5 px-5">
                                    @php
                                        $requestStatusKey = match ($paymentRequest->status->value) {
                                            'open' => 'due',
                                            'actioned' => 'paid',
                                            default => 'cancelled',
                                        };
                                    @endphp
                                    <x-status-badge :status="$requestStatusKey" :label="$paymentRequest->status->label()" />
                                </td>
                                <td class="py-3.5 px-5 text-end whitespace-nowrap">
                                    @if($paymentRequest->status === \App\Enums\SupplierPaymentRequestStatus::Open)
                                        <a href="{{ route('supplier-payments.create', array_filter([
                                            'supplier_id' => $paymentRequest->supplier_id,
                                            'purchase_order_id' => $paymentRequest->purchase_order_id,
                                            'amount' => $paymentRequest->amount,
                                            'payment_request_id' => $paymentRequest->id,
                                        ])) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-brand-500 px-3.5 py-2 text-[11px] font-semibold text-on-brand hover:bg-brand-600 transition-colors">
                                            <x-icon name="payments" class="size-3.5" />
                                            <span>Record Payment</span>
                                        </a>
                                    @elseif($paymentRequest->payment)
                                        <a href="{{ route('supplier-payments.show', $paymentRequest->payment) }}"
                                           class="text-xs font-semibold text-brand-500 hover:underline">View Payment</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
