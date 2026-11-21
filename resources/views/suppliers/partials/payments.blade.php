<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex flex-wrap items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
            <x-icon name="payments" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base sm:text-lg font-bold text-ink">Payments</h2>
            <p class="text-xs text-muted">Every payment made to {{ $supplier->name }}, newest first. Pending payments are not counted until approved.</p>
        </div>
        <div class="text-xs tabular-nums text-muted text-end">
            <p>Against invoices <strong class="text-ink">{{ \App\Helpers\Money::format($payments['invoice_total']) }}</strong></p>
            <p>Supplier-level <strong class="text-ink">{{ \App\Helpers\Money::format($payments['supplier_total']) }}</strong></p>
            <p class="text-sm">Total <strong class="text-success">{{ \App\Helpers\Money::format($payments['total']) }}</strong></p>
        </div>
    </div>

    @if($payments['rows']->isEmpty())
        <x-empty-state message="No payments recorded for this supplier yet." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-[11px] font-bold uppercase tracking-wider text-muted">
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4 text-end">Amount</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4">Reference / Note</th>
                        <th class="py-3 px-4">Branch</th>
                        <th class="py-3 px-4">Order</th>
                        <th class="py-3 px-4">Recorded By</th>
                        <th class="py-3 pl-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments['rows'] as $payment)
                        <tr class="border-b border-line/70 hover:bg-soft/40 transition-colors {{ $payment['counted'] ? '' : 'opacity-70' }}">
                            <td class="py-3 pr-4 text-xs text-muted whitespace-nowrap tabular-nums">{{ $payment['date']->format('d M Y') }}</td>
                            <td class="py-3 px-4 text-xs text-muted whitespace-nowrap">{{ $payment['kind'] }}</td>
                            <td class="py-3 px-4 text-end tabular-nums font-semibold text-success">{{ \App\Helpers\Money::format($payment['amount']) }}</td>
                            <td class="py-3 px-4 text-xs text-ink">{{ $payment['method'] }}</td>
                            <td class="py-3 px-4 text-xs text-muted break-words max-w-xs">{{ $payment['reference'] ?: '—' }}</td>
                            <td class="py-3 px-4 text-xs text-muted">{{ $payment['branch'] }}</td>
                            <td class="py-3 px-4 text-xs font-semibold text-ink tabular-nums">{{ $payment['order'] }}</td>
                            <td class="py-3 px-4 text-xs text-muted">{{ $payment['recorded_by'] }}</td>
                            <td class="py-3 pl-4">
                                <x-status-badge :status="$payment['status_key']" :label="$payment['status_label']" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
