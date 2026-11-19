@extends('layouts.app')

@section('title', 'Purchase Order ' . $order->reference_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Purchase Invoices')

@section('content')
@php
    $grandTotal = (float) $order->grand_total;
    $paidAmount = (float) $order->paid_amount;
    $paymentDue = (float) $order->payment_due;
    $paidPct = $grandTotal > 0 ? min(100, (int) round(($paidAmount / $grandTotal) * 100)) : 0;
    $totalUnits = (int) $order->items->sum('quantity');
@endphp

<style>
    @media print {
        header, aside, nav, .no-print { display: none !important; }
        main { padding: 0 !important; }
    }
</style>

<div x-data="{ confirmModal: null }" class="space-y-6">
    {{-- Executive Detail Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2 no-print" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Purchasing</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('purchase-invoices.index') }}" class="hover:text-brand-500 transition-colors">Purchase Invoices</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $order->reference_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">
                    Purchase Order {{ $order->reference_no }}
                </h1>
                @include('purchase-invoices.partials.consolidated-status', ['order' => $order])
                <x-status-badge :status="$order->payment_status->value" :label="$order->payment_status->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Ordered {{ $order->order_date->format('d M Y · h:i A') }} from <strong class="text-ink">{{ $order->supplier?->name ?? 'Unknown Supplier' }}</strong>
            </p>
        </div>

        {{-- Action Bar --}}
        <div class="flex items-center gap-2 flex-wrap shrink-0 no-print">
            @can('approve', $order)
                @if($order->approval_status === \App\Enums\ApprovalStatus::Pending)
                    <button type="button" @click="confirmModal = 'approve'"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-success hover:bg-success/90 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Approve / Receive</span>
                    </button>
                @endif
            @endcan

            @can('reject', $order)
                @if($order->approval_status === \App\Enums\ApprovalStatus::Pending)
                    <button type="button" @click="confirmModal = 'reject'"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-danger/30 bg-danger/10 hover:bg-danger hover:text-on-brand px-4 py-2.5 text-xs sm:text-sm font-semibold text-danger transition-colors cursor-pointer">
                        <x-svg.x class="size-4" />
                        <span>Reject</span>
                    </button>
                @endif
            @endcan

            @can('create', App\Models\PurchaseReturn::class)
                <a href="{{ route('purchase-returns.create', ['purchase_order' => $order->id]) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                    <svg class="size-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Return</span>
                </a>
            @endcan

            @can('update', $order)
                <a href="{{ route('purchase-invoices.edit', $order) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                    <x-svg.pencil class="size-4" />
                    <span>Edit</span>
                </a>
            @endcan

            <button type="button" onclick="window.print()"
                    class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                <svg class="size-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print</span>
            </button>

            <a href="{{ route('purchase-invoices.index') }}"
               class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs sm:text-sm font-semibold text-muted shadow-2xs hover:bg-soft hover:text-ink transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>
        </div>
    </div>

    {{-- 4-Card Financial Overview Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Grand Total</span>
                    <p class="text-2xl font-bold tracking-tight text-ink mt-1.5 tabular-nums truncate">
                        {{ \App\Helpers\Money::format($order->grand_total) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="taka" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-muted tabular-nums">
                Subtotal {{ \App\Helpers\Money::format($order->subtotal) }} · Tax {{ rtrim(rtrim(number_format((float) $order->tax_rate, 2), '0'), '.') }}%
            </p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Amount Paid</span>
                    <p class="text-2xl font-bold tracking-tight text-success mt-1.5 tabular-nums truncate">
                        {{ \App\Helpers\Money::format($order->paid_amount) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="check" class="size-5" />
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="h-1.5 w-full rounded-full bg-soft overflow-hidden">
                    <div class="h-full rounded-full bg-success" style="width: {{ $paidPct }}%;"></div>
                </div>
                <p class="text-xs text-muted tabular-nums">{{ $paidPct }}% settled</p>
            </div>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Balance Due</span>
                    <p class="text-2xl font-bold tracking-tight {{ $paymentDue > 0 ? 'text-danger' : 'text-success' }} mt-1.5 tabular-nums truncate">
                        {{ \App\Helpers\Money::format($order->payment_due) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl {{ $paymentDue > 0 ? 'bg-danger/10 text-danger ring-1 ring-danger/20' : 'bg-success/10 text-success ring-1 ring-success/20' }}">
                    <x-icon name="wallet" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-muted">
                {{ $paymentDue > 0 ? 'Remaining payable to supplier' : 'Fully paid with zero balance' }}
            </p>
        </div>

        <div class="rounded-3xl border border-line bg-surface p-5 shadow-card flex flex-col justify-between">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Items Count</span>
                    <p class="text-2xl sm:text-3xl font-bold tracking-tight text-ink mt-1.5 tabular-nums">
                        {{ number_format($order->items->count()) }}
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-muted tabular-nums">
                <strong class="text-ink font-semibold">{{ number_format($totalUnits) }}</strong> total {{ Str::plural('unit', $totalUnits) }} ordered
            </p>
        </div>
    </div>

    {{-- Supplier, Location & Approval Audit Cards --}}
    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Supplier & Order Information --}}
        <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
            <div class="flex items-center gap-3 pb-4 mb-4 border-b border-line">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="store" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Supplier &amp; Location Details</h2>
                    <p class="text-xs text-muted">Originating supplier, receiving branch, and invoice document</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Supplier</dt>
                    <dd class="mt-1 font-bold text-ink">{{ $order->supplier?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Business Location</dt>
                    <dd class="mt-1 font-bold text-ink">{{ $order->location?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Reference No</dt>
                    <dd class="mt-1 font-bold text-ink tabular-nums">{{ $order->reference_no }}</dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Order Date</dt>
                    <dd class="mt-1 font-semibold text-ink tabular-nums">{{ $order->order_date->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2 rounded-2xl bg-soft/50 border border-line/70 p-3.5 flex items-center justify-between gap-3">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Attach Document</dt>
                        <dd class="mt-1 text-xs text-muted">
                            {{ $order->document ? 'Supplier invoice or supporting file attached' : 'No supporting document attached' }}
                        </dd>
                    </div>
                    @if($order->document)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->document) }}" target="_blank"
                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-brand-500/10 px-3 py-1.5 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 hover:bg-brand-500 hover:text-on-brand transition-colors shrink-0">
                            <x-svg.paper-clip class="size-3.5" />
                            <span>View document</span>
                        </a>
                    @else
                        <span class="text-xs font-medium text-muted">—</span>
                    @endif
                </div>
            </dl>
        </div>

        {{-- Approval Audit Trail --}}
        <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
            <div class="flex items-center gap-3 pb-4 mb-4 border-b border-line">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="roles" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Approval &amp; Audit Trail</h2>
                    <p class="text-xs text-muted">Creation, authorization, and status verification log</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Added By</dt>
                    <dd class="mt-1 font-bold text-ink">{{ $order->creator?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Status</dt>
                    <dd class="mt-1.5">
                        @include('purchase-invoices.partials.consolidated-status', ['order' => $order])
                    </dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Approved By</dt>
                    <dd class="mt-1 font-bold text-ink">{{ $order->approver?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Approved At</dt>
                    <dd class="mt-1 font-semibold text-ink tabular-nums">{{ $order->approved_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2 rounded-2xl bg-soft/50 border border-line/70 p-3.5">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Workflow Status</dt>
                        <dd class="mt-1 text-xs text-muted">
                            @if($order->approval_status === \App\Enums\ApprovalStatus::Approved)
                                Stock was received into inventory and supplier payables updated when this purchase was created.
                            @elseif($order->approval_status === \App\Enums\ApprovalStatus::Rejected)
                                This purchase order was rejected and cancelled without affecting stock.
                            @else
                                Legacy purchase awaiting sign-off before stock quantities and supplier balances update.
                            @endif
                        </dd>
                    </div>
                </div>
            </dl>
        </div>
    </div>

    {{-- Line Items & Financial Breakdown Card --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 sm:py-5 border-b border-line bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Invoice Line Items</h2>
                <p class="text-xs text-muted mt-0.5">{{ $order->items->count() }} {{ Str::plural('product', $order->items->count()) }} · {{ number_format($totalUnits) }} total {{ Str::plural('unit', $totalUnits) }}</p>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5 w-14">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 text-right">Quantity</th>
                        <th class="py-3.5 px-5 text-right">Unit Price</th>
                        <th class="py-3.5 px-5 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($order->items as $index => $item)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5 text-xs font-semibold text-muted tabular-nums">{{ $index + 1 }}</td>
                            <td class="py-3.5 px-5">
                                <div class="text-sm font-semibold text-ink">{{ $item->unit?->displayName() ?? 'Unknown product' }}</div>
                                @if($item->unit?->unit)
                                    <div class="mt-0.5 inline-flex items-center rounded-md bg-soft px-2 py-0.5 text-[11px] font-medium text-muted border border-line">
                                        {{ $item->unit->unit }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 text-right text-sm font-semibold text-ink tabular-nums">{{ number_format($item->quantity) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm text-muted tabular-nums">{{ \App\Helpers\Money::format($item->unit_price) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($item->subtotal) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 px-6 text-center text-sm text-muted">
                                No products on this purchase order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Financial Breakdown Footer --}}
        <div class="border-t border-line bg-canvas/30 p-5 sm:p-6">
            <div class="max-w-md ml-auto space-y-2.5 text-sm">
                <div class="flex items-center justify-between text-muted">
                    <span>Subtotal</span>
                    <span class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->subtotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-muted">
                    <span>Tax ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 2), '0'), '.') }}%)</span>
                    <span class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->tax_amount) }}</span>
                </div>
                <div class="pt-2.5 border-t border-line flex items-baseline justify-between">
                    <span class="font-bold text-ink">Grand Total</span>
                    <span class="text-xl font-extrabold text-brand-500 tabular-nums">{{ \App\Helpers\Money::format($order->grand_total) }}</span>
                </div>
                <div class="flex items-center justify-between text-muted">
                    <span>Amount Paid</span>
                    <span class="font-semibold text-success tabular-nums">{{ \App\Helpers\Money::format($order->paid_amount) }}</span>
                </div>
                <div class="pt-2.5 border-t border-line flex items-center justify-between">
                    <span class="font-bold text-ink">Payment Due</span>
                    <span class="text-base font-extrabold {{ $paymentDue > 0 ? 'text-danger' : 'text-success' }} tabular-nums">{{ \App\Helpers\Money::format($order->payment_due) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Approve Modal --}}
    @can('approve', $order)
        @if($order->approval_status === \App\Enums\ApprovalStatus::Pending)
            <div x-show="confirmModal === 'approve'" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs no-print"
                 @keydown.escape.window="confirmModal = null">
                <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6 sm:p-7" @click.stop>
                    <form method="POST" action="{{ route('purchase-invoices.approve', $order) }}">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-start justify-between pb-4 border-b border-line">
                            <div class="flex items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                                    <x-icon name="check" class="size-5" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-ink">Approve &amp; Receive</h3>
                                    <p class="text-xs text-muted">{{ $order->reference_no }}</p>
                                </div>
                            </div>
                            <button type="button" @click="confirmModal = null" class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft cursor-pointer">
                                <x-svg.x class="size-5" />
                            </button>
                        </div>
                        <p class="py-5 text-sm text-muted leading-relaxed">
                            Approve and receive this purchase order of <strong class="text-ink">{{ \App\Helpers\Money::format($order->grand_total) }}</strong>? Stock quantities and supplier payable due will update immediately.
                        </p>
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                            <button type="button" @click="confirmModal = null"
                                    class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs sm:text-sm font-semibold text-ink hover:bg-soft cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn-scale rounded-xl bg-success hover:bg-success/90 px-5 py-2 text-xs sm:text-sm font-semibold text-on-brand shadow-sm cursor-pointer">
                                Approve / Receive
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    {{-- Reject Modal --}}
    @can('reject', $order)
        @if($order->approval_status === \App\Enums\ApprovalStatus::Pending)
            <div x-show="confirmModal === 'reject'" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs no-print"
                 @keydown.escape.window="confirmModal = null">
                <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6 sm:p-7" @click.stop>
                    <form method="POST" action="{{ route('purchase-invoices.reject', $order) }}">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-start justify-between pb-4 border-b border-line">
                            <div class="flex items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                                    <x-icon name="alert-triangle" class="size-5" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-ink">Reject Purchase Order</h3>
                                    <p class="text-xs text-muted">{{ $order->reference_no }}</p>
                                </div>
                            </div>
                            <button type="button" @click="confirmModal = null" class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft cursor-pointer">
                                <x-svg.x class="size-5" />
                            </button>
                        </div>
                        <p class="py-5 text-sm text-muted leading-relaxed">
                            Reject this purchase order? It will be marked as cancelled and neither inventory stock nor supplier balances will be affected.
                        </p>
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                            <button type="button" @click="confirmModal = null"
                                    class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs sm:text-sm font-semibold text-ink hover:bg-soft cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn-scale rounded-xl bg-danger hover:bg-danger/90 px-5 py-2 text-xs sm:text-sm font-semibold text-on-brand shadow-sm cursor-pointer">
                                Confirm Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
</div>

@if(request('print'))
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
@endif
@endsection
