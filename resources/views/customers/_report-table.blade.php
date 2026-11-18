<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ $key === 'status' ? 'center' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($customers as $index => $customer)
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ $key === 'status' ? 'center' : '' }}">
                        @if($key === 'customer_id')
                            <span class="code-pill">{{ $customer->customer_id }}</span>
                        @elseif($key === 'name')
                            <span class="strong">{{ $customer->name }}</span>
                        @elseif($key === 'mobile')
                            {{ $customer->mobile ?: '—' }}
                        @elseif($key === 'tel_no' || $key === 'phone')
                            {{ $customer->tel_no ?: '—' }}
                        @elseif($key === 'email')
                            {{ $customer->email ?: '—' }}
                        @elseif($key === 'city')
                            {{ $customer->city ?: '—' }}
                        @elseif($key === 'status')
                            <span class="status-pill {{ $customer->status === 'active' ? 'status-active' : 'status-inactive' }}">
                                {{ ucfirst($customer->status) }}
                            </span>
                        @else
                            {{ $customer->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 1 }}" class="empty-cell">No customers found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
