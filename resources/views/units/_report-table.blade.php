<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ $key === 'quantity' ? 'num' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($units as $index => $unit)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ $key === 'quantity' ? 'num' : '' }}">
                        @if($key === 'product_name')
                            <span class="strong">{{ $unit->displayName() }}</span>
                        @elseif($key === 'quantity')
                            <span class="strong">{{ number_format((int) $unit->quantity) }}</span>
                            @if($unit->isLowStock())
                                <span class="low-pill">LOW</span>
                            @endif
                        @elseif($key === 'unit')
                            <span class="unit-pill">{{ $unit->unit }}</span>
                        @elseif($key === 'note')
                            {{ $unit->note ?: '—' }}
                        @else
                            {{ $unit->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No quantity entries found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
