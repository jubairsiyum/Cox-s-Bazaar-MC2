@php
    $productCountsByCategory = \App\Models\Product::query()
        ->whereNotNull('category_id')
        ->selectRaw('category_id, COUNT(*) as total_count')
        ->groupBy('category_id')
        ->pluck('total_count', 'category_id');
    $totalProductsInReport = $categories->sum(fn ($c) => (int) ($productCountsByCategory[$c->id] ?? 0));
@endphp

<x-report-document
    title="Categories List"
    mode="print"
    record-label="Official Master Data Record"
>
    <x-slot:summary>
        Showing <strong>{{ count($categories) }}</strong> {{ Str::plural('category', count($categories)) }}
        &middot; Linked Products: <strong>{{ number_format($totalProductsInReport) }}</strong>
        @if(request('status'))
            &middot; Status: <strong>{{ ucfirst(request('status')) }}</strong>
        @endif
        @if(request('search'))
            &middot; Filter: <strong>"{{ request('search') }}"</strong>
        @endif
    </x-slot:summary>

    @include('categories._report-table')
</x-report-document>
