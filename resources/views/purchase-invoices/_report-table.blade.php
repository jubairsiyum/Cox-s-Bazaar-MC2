<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ in_array($key, ['grand_total', 'payment_due'], true) ? 'num' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($orders as $index => $order)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ in_array($key, ['grand_total', 'payment_due'], true) ? 'num' : '' }}">
                        @switch($key)
                            @case('order_date')
                                {{ $order->order_date->format('d/m/Y H:i') }}
                                @break
                            @case('reference_no')
                                <span class="strong">{{ $order->reference_no }}</span>
                                @break
                            @case('location')
                                {{ $order->location?->name ?? '—' }}
                                @break
                            @case('supplier')
                                {{ $order->supplier?->name ?? '—' }}
                                @break
                            @case('purchase_status')
                                {{ $order->purchase_status->label() }}
                                @break
                            @case('payment_status')
                                {{ $order->payment_status->label() }}
                                @break
                            @case('grand_total')
                                <span class="strong">{{ \App\Helpers\Money::format($order->grand_total) }}</span>
                                @break
                            @case('payment_due')
                                <span class="{{ (float) $order->payment_due > 0 ? 'due-alert' : '' }}">
                                    {{ \App\Helpers\Money::format($order->payment_due) }}
                                </span>
                                @break
                            @case('added_by')
                                {{ $order->creator?->name ?? '—' }}
                                @break
                            @default
                                {{ $order->{$key} ?? '—' }}
                        @endswitch
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No purchase orders found.</td>
            </tr>
        @endforelse
    </tbody>
    @if(count($orders) > 0)
        <tfoot>
            <tr>
                <td></td>
                @foreach($columns as $key => $label)
                    @if($loop->first)
                        <td>Totals ({{ count($orders) }} records)</td>
                    @elseif($key === 'grand_total')
                        <td class="num">{{ \App\Helpers\Money::format($reportGrandTotal) }}</td>
                    @elseif($key === 'payment_due')
                        <td class="num due-alert">{{ \App\Helpers\Money::format($reportPaymentDue) }}</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
