<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
            <x-icon name="warehouse" class="size-5" />
        </div>
        <div>
            <h2 class="text-base sm:text-lg font-bold text-ink">Branch-wise Dues</h2>
            <p class="text-xs text-muted">Which branches owe {{ $supplier->name }} money and which have cleared — expand a branch for the order-level breakdown. Cancelled orders carry no due.</p>
        </div>
    </div>

    @if($branches->isEmpty())
        <x-empty-state message="No purchase orders recorded for this supplier yet." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-[11px] font-bold uppercase tracking-wider text-muted">
                        <th class="py-3 pr-4">Branch</th>
                        <th class="py-3 px-4">Responsible Manager(s)</th>
                        <th class="py-3 px-4 text-end">Orders</th>
                        <th class="py-3 px-4 text-end">Ordered</th>
                        <th class="py-3 px-4 text-end">Returned</th>
                        <th class="py-3 px-4 text-end">Paid</th>
                        <th class="py-3 px-4 text-end">Due</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 pl-4 text-end">Details</th>
                    </tr>
                </thead>
                @foreach($branches as $branch)
                <tbody x-data="{ open: false }">
                        <tr class="border-b border-line/70 hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 pr-4">
                                <span class="block text-sm font-semibold text-ink">{{ $branch['location_name'] }}</span>
                                <span class="block text-[11px] text-muted">{{ $branch['branch_name'] }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-muted">
                                {{ $branch['orders']->pluck('creator.name')->filter()->unique()->implode(', ') ?: '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-end tabular-nums">{{ $branch['orders']->count() }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-ink">{{ \App\Helpers\Money::format($branch['grand_total']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-warning">{{ \App\Helpers\Money::format($branch['returned']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-success">{{ \App\Helpers\Money::format($branch['paid_amount']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums font-bold {{ $branch['cleared'] ? 'text-success' : 'text-danger' }}">{{ \App\Helpers\Money::format($branch['due']) }}</td>
                            <td class="py-3.5 px-4">
                                <x-status-badge :status="$branch['cleared'] ? 'paid' : 'due'" :label="$branch['cleared'] ? 'Dues Cleared' : 'Still Owing'" />
                            </td>
                            <td class="py-3.5 pl-4 text-end">
                                <button type="button" @click="open = ! open"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-[11px] font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                                    <span x-text="open ? 'Hide Orders' : 'Show Orders'">Show Orders</span>
                                    <x-icon name="chevron" class="size-3.5 transition-transform" x-bind:class="{ 'rotate-180': open }" />
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak style="display: none;">
                            <td colspan="9" class="pb-4 pt-1">
                                <div class="rounded-2xl border border-line bg-soft/40 p-4 space-y-3">
                                    @foreach($branch['orders'] as $order)
                                        <div class="rounded-2xl border border-line bg-surface px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                                                @if($isSupplierPortal)
                                                    <span class="text-sm font-semibold text-ink tabular-nums">{{ $order->reference_no }}</span>
                                                @else
                                                    <a href="{{ route('purchase-invoices.show', $order) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">
                                                        {{ $order->reference_no }}
                                                    </a>
                                                @endif
                                                <span class="text-[11px] text-muted">{{ $order->order_date->format('d M Y') }}</span>
                                                <span class="text-[11px] text-muted">Manager: <strong class="text-ink">{{ $order->creator?->name ?? '—' }}</strong></span>
                                                @php
                                                    [$orderStatusKey, $orderStatusLabel] = $order->supplierDisplayStatus();
                                                @endphp
                                                <x-status-badge :status="$orderStatusKey" :label="$orderStatusLabel" />
                                                <span class="ml-auto text-xs tabular-nums text-muted">
                                                    Order <strong class="text-ink">{{ \App\Helpers\Money::format($order->grand_total) }}</strong>
                                                    · Paid <strong class="text-success">{{ \App\Helpers\Money::format($order->paid_amount) }}</strong>
                                                    · Due <strong class="text-danger">{{ \App\Helpers\Money::format($order->payment_due) }}</strong>
                                                </span>
                                            </div>

                                            @if($order->payments->isNotEmpty())
                                                <div class="mt-2.5 border-t border-line pt-2.5">
                                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-1.5">Partial Payments</p>
                                                    <ul class="space-y-1">
                                                        @foreach($order->payments as $payment)
                                                            <li class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-muted tabular-nums">
                                                                <span class="font-semibold text-ink">{{ \App\Helpers\Money::format($payment->amount) }}</span>
                                                                <span>{{ ($payment->payment_date ?? $payment->created_at)->format('d M Y') }}</span>
                                                                <span>{{ $payment->methodLabel() ?? 'Unspecified method' }}</span>
                                                                <span>recorded by <strong class="text-ink">{{ $payment->creator?->name ?? 'Unknown user' }}</strong></span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                </tbody>
                @endforeach
                <tfoot>
                    <tr class="text-sm font-bold text-ink">
                        <td class="py-3 pr-4" colspan="3">Total</td>
                        <td class="py-3 px-4 text-end tabular-nums">{{ \App\Helpers\Money::format($branches->sum('grand_total')) }}</td>
                        <td class="py-3 px-4 text-end tabular-nums text-warning">{{ \App\Helpers\Money::format($branches->sum('returned')) }}</td>
                        <td class="py-3 px-4 text-end tabular-nums text-success">{{ \App\Helpers\Money::format($branches->sum('paid_amount')) }}</td>
                        <td class="py-3 px-4 text-end tabular-nums text-danger">{{ \App\Helpers\Money::format($branches->sum('due')) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
