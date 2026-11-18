@php
    $reportGrandTotal = (float) $orders->sum('grand_total');
    $reportPaymentDue = (float) $orders->sum('payment_due');
@endphp

<x-report-document
    title="Purchase Orders List"
    mode="print"
    :landscape="true"
    :compact="true"
    record-label="Official Purchasing Register"
    footer-right="Confidential · Official Purchasing Statement"
>
    <x-slot:summary>
        Showing <strong>{{ count($orders) }}</strong> purchase {{ Str::plural('invoice', count($orders)) }}
        &middot; Grand Total: <strong>{{ \App\Helpers\Money::format($reportGrandTotal) }}</strong>
        &middot; Total Due: <strong>{{ \App\Helpers\Money::format($reportPaymentDue) }}</strong>
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('purchase-invoices._report-table')
</x-report-document>
