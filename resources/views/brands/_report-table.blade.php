<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th>{{ $label }}</th>
            @endforeach
            <th class="num" style="width: 110px;">Products</th>
        </tr>
    </thead>
    <tbody>
        @forelse($brands as $index => $brand)
            @php $brandProductCount = (int) ($productCountsByBrand[$brand->id] ?? 0); @endphp
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td>
                        @if($key === 'name')
                            <div class="brand-cell">
                                <x-brand-logo :name="$brand->name" size="xs" containerClass="brand-icon-box" />
                                <span class="brand-name">{{ $brand->name }}</span>
                            </div>
                        @elseif($key === 'note')
                            {{ $brand->note ?: '—' }}
                        @else
                            {{ $brand->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
                <td class="num" style="font-weight: 700; color: {{ $brandProductCount > 0 ? '#1e3a8a' : '#94a3b8' }};">
                    {{ number_format($brandProductCount) }} {{ Str::plural('product', $brandProductCount) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 2 }}" class="empty-cell">No brands found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
