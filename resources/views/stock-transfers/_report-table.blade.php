<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ in_array($key, ['shipping_charges', 'total_amount'], true) ? 'num' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($transfers as $index => $transfer)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ in_array($key, ['shipping_charges', 'total_amount'], true) ? 'num' : '' }}{{ $key === 'total_amount' ? ' strong' : '' }}">
                        @switch($key)
                            @case('transfer_date')
                                {{ $transfer->transfer_date->format('d/m/Y H:i') }}
                                @break
                            @case('reference_no')
                                <span class="strong">{{ $transfer->reference_no }}</span>
                                @break
                            @case('from_location')
                                {{ $transfer->fromLocation?->name ?? '—' }}
                                @break
                            @case('to_location')
                                {{ $transfer->toLocation?->name ?? '—' }}
                                @break
                            @case('status')
                                {{ $transfer->status->label() }}
                                @break
                            @case('shipping_charges')
                                {{ \App\Helpers\Money::format($transfer->shipping_charges) }}
                                @break
                            @case('total_amount')
                                {{ \App\Helpers\Money::format($transfer->total_amount) }}
                                @break
                            @case('additional_notes')
                                {{ $transfer->additional_notes ?: '—' }}
                                @break
                            @default
                                {{ $transfer->{$key} ?? '—' }}
                        @endswitch
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No stock transfers found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
