@php
    $totalInReport = count($customers);
    $activeInReport = $customers->where('status', 'active')->count();
    $inactiveInReport = $totalInReport - $activeInReport;
@endphp

<x-report-document
    title="Customers List"
    mode="pdf"
    :back-url="route('customers.index')"
    download-label="Download PDF"
    :show-close="true"
    max-width="1040px"
    record-label="Official Party Directory Record"
>
    <x-slot:summary>
        Showing <strong>{{ number_format($totalInReport) }}</strong> {{ Str::plural('customer', $totalInReport) }}
        &middot; Active: <strong>{{ number_format($activeInReport) }}</strong>
        &middot; Inactive: <strong>{{ number_format($inactiveInReport) }}</strong>
        @if(!empty($search))
            &middot; Search: <strong>"{{ $search }}"</strong>
        @endif
        @if(!empty($status))
            &middot; Status: <strong>{{ ucfirst($status) }}</strong>
        @endif
    </x-slot:summary>

    @include('customers._report-table')
</x-report-document>
