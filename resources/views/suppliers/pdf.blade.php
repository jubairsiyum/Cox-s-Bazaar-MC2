@php
    $totalInReport = count($suppliers);
    $totalDueInReport = (float) $suppliers->sum('purchase_due');
    $totalReturnDueInReport = (float) $suppliers->sum('purchase_return_due');
    $hasFinancialCols = isset($columns['purchase_due']) || isset($columns['purchase_return_due']);
@endphp

<x-report-document
    title="Suppliers List"
    mode="pdf"
    :back-url="route('suppliers.index')"
    max-width="1080px"
    record-label="Official Party Directory Record"
>
    <x-slot:summary>
        Showing <strong>{{ number_format($totalInReport) }}</strong> {{ Str::plural('supplier', $totalInReport) }}
        &middot; Purchase Due: <strong>{{ \App\Helpers\Money::format($totalDueInReport) }}</strong>
        &middot; Return Due: <strong>{{ \App\Helpers\Money::format($totalReturnDueInReport) }}</strong>
        @if(!empty($search))
            &middot; Search: <strong>"{{ $search }}"</strong>
        @endif
        @if(!empty($status))
            &middot; Status: <strong>{{ ucfirst($status) }}</strong>
        @endif
    </x-slot:summary>

    @include('suppliers._report-table')
</x-report-document>
