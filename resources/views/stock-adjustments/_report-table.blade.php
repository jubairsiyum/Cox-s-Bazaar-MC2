<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ $key === 'total_amount' ? 'num' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($adjustments as $index => $adjustment)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ $key === 'total_amount' ? 'num strong' : '' }}">
                        @switch($key)
                            @case('adjustment_date')
                                {{ $adjustment->adjustment_date->format('d/m/Y H:i') }}
                                @break
                            @case('reference_no')
                                <span class="strong">{{ $adjustment->reference_no }}</span>
                                @break
                            @case('type')
                                {{ $adjustment->type->label() }}
                                @break
                            @case('total_amount')
                                {{ \App\Helpers\Money::format($adjustment->total_amount) }}
                                @break
                            @case('reason')
                                {{ $adjustment->reason ?: '—' }}
                                @break
                            @case('creator')
                                {{ $adjustment->creator?->name ?? '—' }}
                                @break
                            @default
                                {{ $adjustment->{$key} ?? '—' }}
                        @endswitch
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No stock adjustments found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
