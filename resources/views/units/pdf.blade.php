@php
    $totalQuantityInReport = (int) $units->sum('quantity');
    $lowStockInReport = $units->filter(fn ($u) => $u->isLowStock())->count();
@endphp

<x-report-document
    title="Units List"
    mode="pdf"
    :back-url="route('units.index')"
    record-label="Official Stock Quantity Record"
>
    <x-slot:summary>
        Showing <strong>{{ count($units) }}</strong> {{ Str::plural('entry', count($units)) }}
        &middot; Total Qty: <strong>{{ number_format($totalQuantityInReport) }}</strong>
        @if($lowStockInReport > 0)
            &middot; Low Stock: <strong>{{ number_format($lowStockInReport) }}</strong>
        @endif
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('units._report-table')
</x-report-document>
