@php
    $reportTotal = $transfers->sum('total_amount');
@endphp

<x-report-document
    title="Stock Transfers List"
    mode="pdf"
    :back-url="route('stock-transfers.index')"
    :landscape="true"
    :compact="true"
    record-label="Official Logistics & Transfer Record"
>
    <x-slot:summary>
        Showing <strong>{{ count($transfers) }}</strong> stock {{ Str::plural('transfer', count($transfers)) }}
        &middot; Total Value: <strong>{{ \App\Helpers\Money::format($reportTotal) }}</strong>
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('stock-transfers._report-table')
</x-report-document>
