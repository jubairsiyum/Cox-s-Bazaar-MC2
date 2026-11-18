@extends('layouts.app')

@section('title', $return->return_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Returns')

@section('content')
@php $displayStatus = $return->displayStatusFor(auth()->user()); @endphp

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
                <a href="{{ route('sales-returns.index') }}" class="hover:text-brand-500 transition-colors">Sales Returns</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $return->return_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $return->return_no }}</h1>
                <x-status-badge :status="$displayStatus['key']" :label="$displayStatus['label']" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                {{ $return->return_date->format('d/m/Y H:i') }} · {{ $return->location?->name ?? '—' }} · Submitted by {{ $return->creator?->name ?? '—' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-returns.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>

            @if($return->status === \App\Enums\SalesReturnStatus::Pending || $return->status === \App\Enums\SalesReturnStatus::Rejected)
                @can('update', $return)
                    <a href="{{ route('sales-returns.edit', $return) }}"
                       class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                        <x-icon name="settings" class="size-4" />
                        <span>Edit</span>
                    </a>
                    <x-confirm-delete :action="route('sales-returns.destroy', $return)" label="Delete" confirm-text="Delete Return" />
                @endcan
            @endif

            @if($return->canBeApprovedBy(auth()->user()))
                <form method="POST" action="{{ route('sales-returns.approve', $return) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-success hover:bg-success/90 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Accept</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('sales-returns.reject', $return) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-2.5 text-xs sm:text-sm font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">
                        <x-icon name="alert-triangle" class="size-4" />
                        <span>Reject</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('error'))
        <div class="rounded-3xl border border-danger/30 bg-danger/10 px-5 py-4 text-sm font-semibold text-danger">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Returned Items --}}
        <div class="xl:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base sm:text-lg font-bold text-ink">Returned Products</h2>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-5 w-12">#</th>
                            <th class="py-3 px-5">Product</th>
                            <th class="py-3 px-5 text-center w-24">Qty</th>
                            <th class="py-3 px-5 w-28">Condition</th>
                            <th class="py-3 px-5 text-center w-24">Restock</th>
                            <th class="py-3 px-5 w-44">Reason</th>
                            <th class="py-3 px-5 text-right w-32">Unit Price</th>
                            <th class="py-3 px-5 text-right w-36">Refund</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($return->items as $index => $item)
                            <tr>
                                <td class="py-3 px-5 text-xs font-semibold text-muted tabular-nums">{{ $index + 1 }}</td>
                                <td class="py-3 px-5">
                                    <div class="text-sm font-semibold text-ink">{{ $item->unit?->displayName() ?? 'Unknown product' }}</div>
                                    <div class="text-[11px] text-muted">{{ $item->unit?->unit }}</div>
                                </td>
                                <td class="py-3 px-5 text-center text-sm text-ink tabular-nums">{{ $item->quantity }}</td>
                                <td class="py-3 px-5">
                                    <x-status-badge :status="$item->condition->value === 'damaged' ? 'rejected' : 'active'" :label="$item->condition->label()" />
                                </td>
                                <td class="py-3 px-5 text-center text-sm text-ink">{{ $item->restock ? 'Yes' : 'No' }}</td>
                                <td class="py-3 px-5 text-xs text-muted">{{ $item->reason ?: '—' }}</td>
                                <td class="py-3 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($item->unit_price) }}</td>
                                <td class="py-3 px-5 text-right text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($return->reason)
                <div class="px-5 sm:px-6 py-4 border-t border-line">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Reason for Return</p>
                    <p class="text-sm text-ink">{{ $return->reason }}</p>
                </div>
            @endif
        </div>

        {{-- Summary & Approval --}}
        <div class="space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Return Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Total Refund / Credit</dt>
                        <dd class="text-lg font-extrabold text-brand-500 tabular-nums">{{ \App\Helpers\Money::format($return->refund_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Restock</dt>
                        <dd class="font-semibold text-ink">{{ $return->restocked_at ? 'Stock returned on '.$return->restocked_at->format('d/m/Y H:i') : ($return->items->contains('restock', true) ? 'On approval' : 'Not requested') }}</dd>
                    </div>
                    @if($return->approved_at)
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">{{ $return->status === \App\Enums\SalesReturnStatus::Rejected ? 'Rejected' : 'Approved' }}</dt>
                            <dd class="font-semibold text-ink">{{ $return->approver?->name ?? '—' }} · {{ $return->approved_at->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Linked Invoice</h2>
                @if($return->salesInvoice)
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Invoice No</dt>
                            <dd>
                                @can('view', $return->salesInvoice)
                                    <a href="{{ route('sales-invoices.show', $return->salesInvoice) }}" class="font-bold text-brand-500 hover:underline tabular-nums">{{ $return->salesInvoice->invoice_no }}</a>
                                @else
                                    <span class="font-semibold text-ink tabular-nums">{{ $return->salesInvoice->invoice_no }}</span>
                                @endcan
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Grand Total</dt>
                            <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($return->salesInvoice->grand_total) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Paid</dt>
                            <dd class="font-semibold text-success tabular-nums">{{ \App\Helpers\Money::format($return->salesInvoice->paid_amount) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Due</dt>
                            <dd class="font-semibold text-danger tabular-nums">{{ \App\Helpers\Money::format($return->salesInvoice->payment_due) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-[11px] text-muted leading-relaxed">
                        On approval this return credits the invoice: the due is reduced first and any excess reduces the recorded payments.
                    </p>
                @else
                    <p class="text-sm text-muted">No linked invoice.</p>
                @endif
            </div>

            @if($return->canBeApprovedBy(auth()->user()))
                <div class="rounded-3xl border border-warning/30 bg-warning/5 p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-3 mb-3 border-b border-warning/20">Awaiting Your Decision</h2>
                    <div class="flex items-center gap-2.5">
                        <form method="POST" action="{{ route('sales-returns.approve', $return) }}" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="w-full rounded-2xl bg-success hover:bg-success/90 px-4 py-2.5 text-xs font-semibold text-white transition-colors cursor-pointer">
                                Accept Return
                            </button>
                        </form>
                        <form method="POST" action="{{ route('sales-returns.reject', $return) }}" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="w-full rounded-2xl border border-danger/30 bg-danger/10 px-4 py-2.5 text-xs font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
