<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
            <x-icon name="truck" class="size-5" />
        </div>
        <div>
            <h2 class="text-base sm:text-lg font-bold text-ink">Purchase Orders</h2>
            <p class="text-xs text-muted">Every purchase order with its products and quantities. Accept a pending order to confirm it (Pending → Ordered). Delivery is recorded automatically once the branch marks the goods received.</p>
        </div>
    </div>

    @if($orders->isEmpty())
        <x-empty-state message="No purchase orders recorded for this supplier yet." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-[11px] font-bold uppercase tracking-wider text-muted">
                        <th class="py-3 pr-4">Reference</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Branch</th>
                        <th class="py-3 px-4">Branch Status</th>
                        <th class="py-3 px-4">Supplier Status</th>
                        <th class="py-3 px-4 text-end">Total</th>
                        <th class="py-3 px-4 text-end">Paid</th>
                        <th class="py-3 px-4 text-end">Due</th>
                        <th class="py-3 pl-4 text-end">Actions</th>
                    </tr>
                </thead>
                @foreach($orders as $row)
                    @php
                        $order = $row['order'];
                    @endphp
                    <tbody x-data="{ open: false }" class="border-b border-line/70">
                        <tr class="hover:bg-soft/40 transition-colors {{ $row['cancelled'] ? 'opacity-70' : '' }}">
                            <td class="py-3.5 pr-4">
                                @if($isSupplierPortal)
                                    <span class="text-sm font-semibold text-ink tabular-nums">{{ $order->reference_no }}</span>
                                @else
                                    <a href="{{ route('purchase-invoices.show', $order) }}" class="text-sm font-semibold text-brand-500 hover:underline tabular-nums">
                                        {{ $order->reference_no }}
                                    </a>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-muted whitespace-nowrap">{{ $order->order_date->format('d M Y') }}</td>
                            <td class="py-3.5 px-4 text-xs text-muted">{{ $order->location?->name ?? '—' }}</td>
                            <td class="py-3.5 px-4">
                                <x-status-badge :status="$row['branch_status'][0]" :label="$row['branch_status'][1]" />
                            </td>
                            <td class="py-3.5 px-4">
                                @if($row['deleted'])
                                    <x-status-badge status="cancelled" label="Deleted" />
                                @else
                                    <x-status-badge :status="$row['supplier_status'][0]" :label="$row['supplier_status'][1]" />
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-ink">{{ \App\Helpers\Money::format($row['grand_total']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-success">{{ \App\Helpers\Money::format($row['paid']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-danger">{{ \App\Helpers\Money::format($row['due']) }}</td>
                            <td class="py-3.5 pl-4 text-end whitespace-nowrap">
                                @can('recordOrderStatus', $supplier)
                                    @if($row['awaiting_acceptance'])
                                        <form method="POST" action="{{ route('suppliers.profile.accept-order', [$supplier, $order]) }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-[11px] font-semibold text-ink hover:border-brand-500/40 hover:text-brand-500 transition-colors cursor-pointer">
                                                <x-icon name="check" class="size-3.5" />
                                                <span>Accept Order</span>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                                <button type="button" @click="open = ! open"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-[11px] font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                                    <span x-text="open ? 'Hide Items' : 'Show Items'">Show Items</span>
                                    <x-icon name="chevron" class="size-3.5 transition-transform" x-bind:class="{ 'rotate-180': open }" />
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak style="display: none;">
                            <td colspan="9" class="pb-4 pt-1">
                                <div class="rounded-2xl border border-line bg-soft/40 p-4 space-y-3">
                                    @if($row['deleted'])
                                        <p class="text-xs font-semibold text-danger">This order was deleted by the branch{{ $order->deleted_at ? ' on '.$order->deleted_at->format('d M Y') : '' }}. It is kept for the record and carries no due.</p>
                                    @elseif($row['cancelled'])
                                        <p class="text-xs font-semibold text-danger">This order was cancelled. It is listed for the record and carries no due.</p>
                                    @endif

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="border-b border-line text-[10px] font-bold uppercase tracking-wider text-muted">
                                                    <th class="py-2 pr-3">Product</th>
                                                    <th class="py-2 px-3 text-end">Quantity</th>
                                                    <th class="py-2 px-3 text-end">Rate</th>
                                                    <th class="py-2 px-3 text-end">Discount</th>
                                                    <th class="py-2 pl-3 text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($row['lines'] as $line)
                                                    <tr class="border-b border-line/60">
                                                        <td class="py-2 pr-3 font-semibold text-ink">{{ $line['product'] }}</td>
                                                        <td class="py-2 px-3 text-end tabular-nums">{{ $line['quantity'] }}</td>
                                                        <td class="py-2 px-3 text-end tabular-nums">{{ \App\Helpers\Money::format($line['rate']) }}</td>
                                                        <td class="py-2 px-3 text-end tabular-nums">{{ \App\Helpers\Money::format($line['discount']) }}</td>
                                                        <td class="py-2 pl-3 text-end tabular-nums text-ink">{{ \App\Helpers\Money::format($line['subtotal']) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="py-3 text-center text-muted">No products recorded on this order.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                            <tfoot>
                                                <tr class="font-semibold text-ink">
                                                    <td class="pt-2 pr-3">Totals ({{ $row['quantity'] }} {{ Str::plural('unit', $row['quantity']) }})</td>
                                                    <td colspan="3" class="pt-2 px-3 text-end text-muted">Subtotal {{ \App\Helpers\Money::format($row['subtotal']) }} · Tax ({{ (float) $order->tax_rate }}%) {{ \App\Helpers\Money::format($row['tax_amount']) }}</td>
                                                    <td class="pt-2 pl-3 text-end tabular-nums">{{ \App\Helpers\Money::format($row['grand_total']) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-xs tabular-nums text-muted">
                                        <span>Paid <strong class="text-success">{{ \App\Helpers\Money::format($row['paid']) }}</strong></span>
                                        <span>Due <strong class="text-danger">{{ \App\Helpers\Money::format($row['due']) }}</strong></span>
                                        <span>Manager <strong class="text-ink">{{ $order->creator?->name ?? '—' }}</strong></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>
