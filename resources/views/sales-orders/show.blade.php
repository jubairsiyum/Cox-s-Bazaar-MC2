@extends('layouts.app')

@section('title', $order->order_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Sales Orders')

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
                <a href="{{ route('sales-orders.index') }}" class="hover:text-brand-500 transition-colors">Sales Orders</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $order->order_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $order->order_no }}</h1>
                <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                {{ $order->order_date->format('d/m/Y H:i') }}
                @if($order->expected_date) · Expected {{ $order->expected_date->format('d/m/Y') }} @endif
                · {{ $order->location?->name ?? '—' }} · Created by {{ $order->creator?->name ?? '—' }}
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('sales-orders.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>

            @can('update', $order)
                @if($order->status === \App\Enums\SalesOrderStatus::Draft || $order->status === \App\Enums\SalesOrderStatus::Confirmed)
                    <button type="submit" form="payment-form"
                            class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Confirm Payment &amp; Generate Invoice</span>
                    </button>
                @endif

                @if($order->status->isEditable())
                    <a href="{{ route('sales-orders.edit', $order) }}"
                       class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                        <x-icon name="settings" class="size-4" />
                        <span>Edit</span>
                    </a>
                    <x-confirm-delete :action="route('sales-orders.destroy', $order)" label="Delete" confirm-text="Delete Order" />
                @endif

                @if($order->status !== \App\Enums\SalesOrderStatus::Cancelled)
                    <form method="POST" action="{{ route('sales-orders.cancel', $order) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-2.5 text-xs sm:text-sm font-semibold text-danger hover:bg-danger/15 transition-colors cursor-pointer">
                            <span>Cancel</span>
                        </button>
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
                <h2 class="text-base sm:text-lg font-bold text-ink">Order Items</h2>
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
                        @foreach($order->items as $index => $item)
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

            @if($order->note)
                <div class="px-5 sm:px-6 py-4 border-t border-line">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">Note</p>
                    <p class="text-sm text-ink">{{ $order->note }}</p>
                </div>
            @endif
        </div>

        {{-- Summary --}}
        <div class="space-y-6">
            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Order Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Subtotal</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->subtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Discount</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->discount_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">Tax ({{ $order->tax_rate }}%)</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->tax_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-line">
                        <dt class="font-bold text-ink">Grand Total</dt>
                        <dd class="text-lg font-extrabold text-ink tabular-nums">{{ \App\Helpers\Money::format($order->grand_total) }}</dd>
                    </div>
                </dl>
            </div>

            @if($order->status === \App\Enums\SalesOrderStatus::Draft || $order->status === \App\Enums\SalesOrderStatus::Confirmed)
                @can('update', $order)
                    <div class="rounded-3xl border border-brand-500/20 bg-surface p-5 sm:p-6 shadow-card">
                        <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Confirm Payment</h2>
                        <p class="text-xs text-muted mb-4 leading-relaxed">
                            This business never sells on credit. Capture the payment in full below — the invoice is generated as PAID and the stock is issued exactly once, only after the payment is confirmed.
                        </p>
                        <form id="payment-form" method="POST" action="{{ route('sales-orders.confirm', $order) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="payment_method" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Payment Method<span class="text-danger">*</span>
                                </label>
                                <select id="payment_method" name="payment_method" required
                                        class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('payment_method') border-danger @enderror">
                                    <option value="">Please Select</option>
                                    @foreach(\App\Enums\PaymentMethod::options() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="amount_received" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                                    Amount Received<span class="text-danger">*</span>
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 text-sm font-bold text-muted pointer-events-none">৳</span>
                                    <input type="number" id="amount_received" name="amount_received" step="0.01" min="0" required
                                           value="{{ old('amount_received', $order->grand_total) }}"
                                           class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm font-semibold text-ink tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('amount_received') border-danger @enderror">
                                </div>
                                <p class="mt-1.5 text-xs text-muted">
                                    Must equal the order total ({{ \App\Helpers\Money::format($order->grand_total) }}) — full payment is required, partial payments are rejected.
                                </p>
                                @error('amount_received')
                                    <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </form>
                    </div>
                @endcan
            @elseif($order->status === \App\Enums\SalesOrderStatus::Invoiced)
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Payment</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Amount Received</dt>
                            <dd class="font-semibold text-success tabular-nums">{{ \App\Helpers\Money::format($order->grand_total) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Payment Method</dt>
                            <dd class="font-semibold text-ink">{{ filled($order->payment_method) ? (\App\Enums\PaymentMethod::tryFrom($order->payment_method)?->label() ?? $order->payment_method) : '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted">Paid At</dt>
                            <dd class="font-semibold text-ink tabular-nums">{{ $order->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Customer</h2>
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 font-bold">
                        {{ strtoupper(substr($order->customer?->name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-bold text-ink">{{ $order->customer?->name ?? '—' }}</p>
                        <p class="text-xs text-muted">{{ $order->customer?->mobile ?? '—' }}</p>
                    </div>
                </div>
            </div>

            @if($order->invoices->isNotEmpty())
                <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
                    <h2 class="text-base font-bold text-ink pb-4 mb-4 border-b border-line">Generated Invoice</h2>
                    <ul class="space-y-2">
                        @foreach($order->invoices as $invoice)
                            <li class="flex items-center justify-between gap-2">
                                @can('view', $invoice)
                                    <a href="{{ route('sales-invoices.show', $invoice) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">{{ $invoice->invoice_no }}</a>
                                @else
                                    <span class="text-sm font-semibold text-ink tabular-nums">{{ $invoice->invoice_no }}</span>
                                @endcan
                                <x-status-badge :status="$invoice->status->value" :label="$invoice->status->label()" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
