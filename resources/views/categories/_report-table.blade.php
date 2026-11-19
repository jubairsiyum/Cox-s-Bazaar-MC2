<table>
    <thead>
        <tr>
            <th class="row-idx">#</th>
            @foreach($columns as $key => $label)
                <th class="{{ $key === 'status' ? 'center' : '' }}">{{ $label }}</th>
            @endforeach
            <th class="num" style="width: 110px;">Products</th>
        </tr>
    </thead>
    <tbody>
        @forelse($categories as $index => $category)
            @php $catProductCount = (int) ($productCountsByCategory[$category->id] ?? 0); @endphp
            <tr>
                <td class="row-idx">{{ $index + 1 }}</td>
                @foreach($columns as $key => $label)
                    <td class="{{ $key === 'status' ? 'center' : '' }}">
                        @if($key === 'name')
                            <span class="strong">{{ $category->name }}</span>
                        @elseif($key === 'description')
                            {{ $category->description ?: '—' }}
                        @elseif($key === 'status')
                            <span class="status-pill {{ $category->status === 'active' ? 'status-active' : 'status-inactive' }}">
                                {{ ucfirst($category->status) }}
                            </span>
                        @else
                            {{ $category->{$key} ?? '—' }}
                        @endif
                    </td>
                @endforeach
                <td class="num" style="font-weight: 700; color: {{ $catProductCount > 0 ? '#1e3a8a' : '#94a3b8' }};">
                    {{ number_format($catProductCount) }} {{ Str::plural('product', $catProductCount) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($columns) + 2 }}" class="empty-cell">No categories found.</td>
            </tr>
        @endforelse
    </tbody>
</table>
