@php
    $productCountsByBrand = \App\Models\Product::query()
        ->whereNotNull('brand_id')
        ->selectRaw('brand_id, COUNT(*) as total_count')
        ->groupBy('brand_id')
        ->pluck('total_count', 'brand_id');
    $totalProductsInReport = $brands->sum(fn ($b) => (int) ($productCountsByBrand[$b->id] ?? 0));
@endphp

<x-report-document
    title="Brands List"
    mode="print"
    record-label="Official Master Data Record"
>
    <x-slot:summary>
        Showing <strong>{{ count($brands) }}</strong> registered manufacturer {{ Str::plural('brand', count($brands)) }}
        &middot; Linked Products: <strong>{{ number_format($totalProductsInReport) }}</strong>
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('brands._report-table')
</x-report-document>
