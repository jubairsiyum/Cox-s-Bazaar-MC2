<div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card">
    <div class="flex flex-wrap items-center gap-3 pb-4 mb-5 border-b border-line">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning ring-1 ring-warning/20">
            <x-icon name="warehouse" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base sm:text-lg font-bold text-ink">Purchase Returns</h2>
            <p class="text-xs text-muted">Goods returned to {{ $supplier->name }}. An approved return adds its unpaid remainder to the supplier's return due; a pending or rejected return adds nothing.</p>
        </div>
        <div class="text-xs tabular-nums text-muted text-end">
            <p>Returned value <strong class="text-ink">{{ \App\Helpers\Money::format($returns['value_total']) }}</strong></p>
            <p>Refunded <strong class="text-success">{{ \App\Helpers\Money::format($returns['refunded_total']) }}</strong></p>
            <p>Return due added <strong class="text-danger">{{ \App\Helpers\Money::format($returns['due_total']) }}</strong></p>
        </div>
    </div>

    @if($returns['rows']->isEmpty())
        <x-empty-state message="No purchase returns recorded for this supplier yet." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-line text-[11px] font-bold uppercase tracking-wider text-muted">
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 px-4">Reference</th>
                        <th class="py-3 px-4">Branch</th>
                        <th class="py-3 px-4">Invoice</th>
                        <th class="py-3 px-4">Products Returned</th>
                        <th class="py-3 px-4 text-end">Qty</th>
                        <th class="py-3 px-4 text-end">Value</th>
                        <th class="py-3 px-4 text-end">Refunded</th>
                        <th class="py-3 px-4 text-end">Return Due Added</th>
                        <th class="py-3 pl-4">Approval</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($returns['rows'] as $return)
                        <tr class="border-b border-line/70 align-top hover:bg-soft/40 transition-colors {{ $return['counted'] ? '' : 'opacity-70' }}">
                            <td class="py-3.5 pr-4 text-xs text-muted whitespace-nowrap tabular-nums">{{ $return['date']->format('d M Y') }}</td>
                            <td class="py-3.5 px-4 text-xs font-semibold text-ink tabular-nums">{{ $return['reference'] }}</td>
                            <td class="py-3.5 px-4 text-xs text-muted">{{ $return['branch'] }}</td>
                            <td class="py-3.5 px-4 text-xs text-muted tabular-nums">{{ $return['order'] }}</td>
                            <td class="py-3.5 px-4 text-xs text-ink">
                                <ul class="space-y-0.5">
                                    @forelse($return['lines'] as $line)
                                        <li>{{ $line['product'] }} <span class="text-muted">× {{ $line['quantity'] }} @ {{ \App\Helpers\Money::format($line['rate']) }}</span></li>
                                    @empty
                                        <li class="text-muted">No products recorded.</li>
                                    @endforelse
                                </ul>
                            </td>
                            <td class="py-3.5 px-4 text-end tabular-nums">{{ $return['quantity'] }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-ink">{{ \App\Helpers\Money::format($return['value']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums text-success">{{ \App\Helpers\Money::format($return['refunded']) }}</td>
                            <td class="py-3.5 px-4 text-end tabular-nums font-semibold {{ $return['due_added'] > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ $return['counted'] ? \App\Helpers\Money::format($return['due_added']) : '—' }}
                            </td>
                            <td class="py-3.5 pl-4">
                                <x-status-badge :status="$return['status_key']" :label="$return['status_label']" />
                                <span class="block mt-1 text-[11px] text-muted">by {{ $return['recorded_by'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="text-sm font-bold text-ink">
                        <td class="py-3 pr-4" colspan="5">Total (approved)</td>
                        <td class="py-3 px-4 text-end tabular-nums">{{ $returns['quantity_total'] }}</td>
                        <td class="py-3 px-4 text-end tabular-nums">{{ \App\Helpers\Money::format($returns['value_total']) }}</td>
                        <td class="py-3 px-4 text-end tabular-nums text-success">{{ \App\Helpers\Money::format($returns['refunded_total']) }}</td>
                        <td class="py-3 px-4 text-end tabular-nums text-danger">{{ \App\Helpers\Money::format($returns['due_total']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
