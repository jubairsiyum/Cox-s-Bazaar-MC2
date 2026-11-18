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
        @forelse($returns as $index => $return)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ in_array($key, ['grand_total', 'payment_due'], true) ? 'num' : '' }}">
                        @switch($key)
                            @case('return_date')
                                {{ $return->return_date->format('d/m/Y H:i') }}
                                @break
                            @case('reference_no')
                                <span class="strong">{{ $return->reference_no }}</span>
                                @break
                            @case('parent_purchase')
                                {{ $return->purchaseOrder?->reference_no ?? '—' }}
                                @break
                            @case('reason')
                                {{ $return->reason ?: '—' }}
                                @break
                            @case('location')
                                {{ $return->location?->name ?? '—' }}
                                @break
                            @case('supplier')
                                {{ $return->supplier?->name ?? '—' }}
                                @break
                            @case('approval_status')
                                {{ $return->approval_status->label() }}
                                @break
                            @case('payment_status')
                                {{ $return->payment_status->label() }}
                                @break
                            @case('grand_total')
                                <span class="strong">{{ \App\Helpers\Money::format($return->grand_total) }}</span>
                                @break
                            @case('payment_due')
                                <span class="{{ (float) $return->payment_due > 0 ? 'return-alert' : '' }}">
                                    {{ \App\Helpers\Money::format($return->payment_due) }}
                                </span>
                                @break
                            @default
                                {{ $return->{$key} ?? '—' }}
                        @endswitch
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No purchase returns found.</td>
            </tr>
        @endforelse
    </tbody>
    @if(count($returns) > 0)
        <tfoot>
            <tr>
                <td></td>
                @foreach($columns as $key => $label)
                    @if($loop->first)
                        <td>Totals ({{ count($returns) }} records)</td>
                    @elseif($key === 'grand_total')
                        <td class="num">{{ \App\Helpers\Money::format($reportGrandTotal) }}</td>
                    @elseif($key === 'payment_due')
                        <td class="num return-alert">{{ \App\Helpers\Money::format($reportPaymentDue) }}</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
