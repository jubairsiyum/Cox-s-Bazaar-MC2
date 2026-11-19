@php
    $reportGrandTotal = (float) $returns->sum('grand_total');
    $reportPaymentDue = (float) $returns->sum('payment_due');
@endphp

<x-report-document
    title="Purchase Returns List"
    mode="print"
    :landscape="true"
    :compact="true"
    record-label="Official Purchase Returns Register"
    footer-right="Confidential · Official Purchase Returns Statement"
>
    <x-slot:summary>
        Showing <strong>{{ count($returns) }}</strong> purchase {{ Str::plural('return', count($returns)) }}
        &middot; Return Total: <strong>{{ \App\Helpers\Money::format($reportGrandTotal) }}</strong>
        &middot; Return Due: <strong>{{ \App\Helpers\Money::format($reportPaymentDue) }}</strong>
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('purchase-returns._report-table')
</x-report-document>
