@php
    $reportTotal = $adjustments->sum('total_amount');
@endphp

<x-report-document
    title="Stock Adjustments List"
    mode="print"
    record-label="Official Inventory Audit Record"
>
    <x-slot:summary>
        Showing <strong>{{ count($adjustments) }}</strong> stock {{ Str::plural('adjustment', count($adjustments)) }}
        &middot; Total Value: <strong>{{ \App\Helpers\Money::format($reportTotal) }}</strong>
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('stock-adjustments._report-table')
</x-report-document>
