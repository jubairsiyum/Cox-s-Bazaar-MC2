@extends('layouts.app')

@section('title', $invoice->invoice_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Invoices')

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
                <a href="{{ route('sales-invoices.index') }}" class="hover:text-brand-500 transition-colors">Sales Invoices</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $invoice->invoice_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $invoice->invoice_no }}</h1>
                <x-status-badge :status="$invoice->status->value" :label="$invoice->status->label()" />
                <x-status-badge :status="$invoice->payment_status->badge()" :label="$invoice->payment_status->label()" />
                @if($invoice->salesOrder)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                        From Order {{ $invoice->salesOrder->order_no }}
                    </span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                {{ $invoice->invoice_date->format('d/m/Y H:i') }} · {{ $invoice->location?->name ?? '—' }} · Created by {{ $invoice->creator?->name ?? '—' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-invoices.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>
            <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-icon name="enquiries" class="size-4" />
                <span>Print Invoice</span>
            </a>
            @can('update', $invoice)
                <a href="{{ route('sales-invoices.edit', $invoice) }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                    <x-icon name="settings" class="size-4" />
                    <span>Edit</span>
                </a>
            @endcan
            @can('post', $invoice)
                @if($invoice->status->value === 'draft')
                    <form method="POST" action="{{ route('sales-invoices.post', $invoice) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                            <x-icon name="check" class="size-4" />
                            <span>Post Invoice</span>
                        </button>
                    </form>
                @elseif($invoice->status->value === 'posted')
                    <form method="POST" action="{{ route('sales-invoices.void', $invoice) }}"
                          x-data="{ open: false }">
                        @csrf
                        @method('PATCH')
                        <button type="button" @click="open = !open"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-2.5 text-xs sm:text-sm font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">
                            <x-icon name="alert-triangle" class="size-4" />
                            <span>Void</span>
                        </button>
                        <div x-show="open" x-cloak class="mt-3 rounded-2xl border border-danger/30 bg-surface p-4 shadow-card w-72">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Void Reason</label>
                            <input type="text" name="void_reason" placeholder="e.g. entered by mistake"
                                   class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            <button type="submit"
                                    class="mt-3 w-full rounded-xl bg-danger hover:bg-danger/90 px-4 py-2 text-xs font-semibold text-white transition-colors cursor-pointer">
                                Confirm Void — Stock Will Be Restored
                            </button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if(session('error'))
        <div class="rounded-3xl border border-danger/30 bg-danger/10 px-5 py-4 text-sm font-semibold text-danger">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Items --}}
        <div class="xl:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-line">
                <h2 class="text-base sm:text-lg font-bold text-ink">Invoice Items</h2>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3 px-5 w-12">#</th>
                            <th class="py-3 px-5">Product</th>
                            <th class="py-3 px-5 text-center w-24">Qty</th>
                            <th class="py-3 px-5 text-right w-32">Rate</th>
                            <th class="py-3 px-5 text-right w-32">Discount</th>
                            <th class="py-3 px-5 text-right w-36">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($invoice->items as $index => $item)
                            <tr>
                                <td class="py-3 px-5 text-xs font-semibold text-muted tabular-nums">{{ $index + 1 }}</td>
                                <td class="py-3 px-5">
                                    <div class="text-sm font-semibold text-ink">{{ $item->unit?->displayName() ?? 'Unknown product' }}</div>
                                    <div class="text-[11px] text-muted">{{ $item->unit?->unit }}</div>
                                </td>
                                <td class="py-3 px-5 text-center text-sm text-ink tabular-nums">{{ $item->quantity }}</td>
                                <td class="py-3 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($item->unit_price) }}</td>
                                <td class="py-3 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($item->discount) }}</td>
                                <td class="py-3 px-5 text-right text-sm font-bold text-ink tabular-nums">{{ \App\Helpers\Money::format($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($invoice->note)
                <div class="px-5 sm:px-6 py-4 border-t border-line">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Note</p>
                    <p class="text-sm text-ink">{{ $invoice->note }}</p>
                </div>
            @endif

            @if($invoice->void_reason)
                <div class="px-5 sm:px-6 py-4 border-t border-danger/20 bg-danger/5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-danger mb-1">Void Reason</p>
                    <p class="text-sm text-ink">{{ $invoice->void_reason }}</p>
                </div>
            @endif
        </div>

        {{-- Summary & Payment --}}
        <div class="space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Totals</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Subtotal</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->subtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Discount</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->discount_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Tax ({{ $invoice->tax_rate }}%)</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->tax_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-line">
                        <dt class="font-bold text-ink">Grand Total</dt>
                        <dd class="text-lg font-extrabold text-ink tabular-nums">{{ \App\Helpers\Money::format($invoice->grand_total) }}</dd>
                    </div>
                    @if((float) $invoice->credit_amount > 0)
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Return Credits</dt>
                            <dd class="font-semibold text-warning tabular-nums">−{{ \App\Helpers\Money::format($invoice->credit_amount) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Paid</dt>
                        <dd class="font-semibold text-success tabular-nums">{{ \App\Helpers\Money::format($invoice->paid_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="font-bold text-ink">Due</dt>
                        <dd class="text-lg font-extrabold text-danger tabular-nums">{{ \App\Helpers\Money::format($invoice->payment_due) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Customer</h2>
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 font-bold">
                        {{ strtoupper(substr($invoice->customer?->name ?? 'W', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-bold text-ink">{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</p>
                        <p class="text-xs text-muted">{{ $invoice->customer?->mobile ?? '—' }}</p>
                    </div>
                </div>
            </div>

            @if($invoice->returns->isNotEmpty())
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Linked Returns</h2>
                    <ul class="space-y-2">
                        @foreach($invoice->returns as $return)
                            <li class="flex items-center justify-between gap-2">
                                @can('view', $return)
                                    <a href="{{ route('sales-returns.show', $return) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $return->return_no }}</a>
                                @else
                                    <span class="text-sm font-semibold text-ink tabular-nums">{{ $return->return_no }}</span>
                                @endcan
                                <x-status-badge :status="$return->status->badge()" :label="$return->status->label()" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
