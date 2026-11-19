<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ in_array($key, ['purchase_due', 'purchase_return_due'], true) ? 'num' : ($key === 'status' ? 'center' : '') }}">
                    {{ $label }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($suppliers as $index => $supplier)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ in_array($key, ['purchase_due', 'purchase_return_due'], true) ? 'num' : ($key === 'status' ? 'center' : '') }}">
                        @if($key === 'supplier_id')
                            <span class="code-pill">{{ $supplier->supplier_id }}</span>
                        @elseif($key === 'name')
                            <span class="strong">{{ $supplier->name }}</span>
                        @elseif($key === 'mobile')
                            {{ $supplier->mobile ?: '—' }}
                        @elseif($key === 'email')
                            {{ $supplier->email ?: '—' }}
                        @elseif($key === 'tel_no' || $key === 'phone')
                            {{ $supplier->tel_no ?: '—' }}
                        @elseif($key === 'purchase_due')
                            <span class="{{ (float) $supplier->purchase_due > 0 ? 'due-alert' : '' }}">
                                {{ $currencySymbol }}{{ number_format((float) $supplier->purchase_due, 2) }}
                            </span>
                        @elseif($key === 'purchase_return_due')
                            <span class="{{ (float) $supplier->purchase_return_due > 0 ? 'return-alert' : '' }}">
                                {{ $currencySymbol }}{{ number_format((float) $supplier->purchase_return_due, 2) }}
                            </span>
                        @elseif($key === 'status')
                            <span class="status-pill {{ $supplier->status === 'active' ? 'status-active' : 'status-inactive' }}">
                                {{ ucfirst($supplier->status) }}
                            </span>
                        @else
                            {{ $supplier->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No suppliers found.</td>
            </tr>
        @endforelse
    </tbody>
    @if($hasFinancialCols && $totalInReport > 0)
        <tfoot>
            <tr>
                <td class="center"></td>
                @foreach($columns as $key => $label)
                    @if($key === 'purchase_due')
                        <td class="num due-alert">{{ $currencySymbol }}{{ number_format($totalDueInReport, 2) }}</td>
                    @elseif($key === 'purchase_return_due')
                        <td class="num return-alert">{{ $currencySymbol }}{{ number_format($totalReturnDueInReport, 2) }}</td>
                    @elseif($loop->first)
                        <td style="text-transform: uppercase; letter-spacing: 0.04em; font-size: 10.5px;">Total</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
