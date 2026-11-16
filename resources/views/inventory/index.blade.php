@extends('layouts.app')

@section('title', 'Stock Levels')
@section('page_title', 'Stock Levels')

@section('content')
@php
    $sortLink = fn (string $col) => route('inventory.index', array_filter([
        'search' => request('search'),
        'brand_id' => request('brand_id'),
        'category_id' => request('category_id'),
        'stock_status' => request('stock_status'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));
@endphp

<div x-data="ledgerModal()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Stock Levels</h1>
        <span class="text-sm text-gray-500 dark:text-gray-400">Current stock, valuation and alerts across all product variants</span>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total SKUs</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary->skus ?? 0)) }}</div>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Stock</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format((int) ($summary->total_quantity ?? 0)) }}</div>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Stock Value</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ \App\Helpers\Money::format($summary->stock_value ?? 0) }}</div>
            </div>
        </div>
        <div class="card shadow-sm border-t-4 border-t-amber-500">
            <div class="card-body p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Low Stock (≤ {{ $threshold }})</div>
                <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format((int) ($summary->low_stock ?? 0)) }}</div>
            </div>
        </div>
        <div class="card shadow-sm border-t-4 border-t-red-500">
            <div class="card-body p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Out of Stock</div>
                <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format((int) ($summary->out_of_stock ?? 0)) }}</div>
            </div>
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Stock overview</h2>
        </div>

        <div class="card-body p-4">
            {{-- Filters --}}
            <form method="GET" action="{{ route('inventory.index') }}" class="mb-4 flex flex-wrap items-end gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name, code, barcode ..."
                           class="py-1.5 px-3 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Brand</label>
                    <select name="brand_id" class="py-1.5 px-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <option value="">All Brands</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Category</label>
                    <select name="category_id" class="py-1.5 px-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Stock Status</label>
                    <select name="stock_status" class="py-1.5 px-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <option value="">All</option>
                        <option value="in" @selected(request('stock_status') === 'in')>In Stock</option>
                        <option value="low" @selected(request('stock_status') === 'low')>Low Stock</option>
                        <option value="out" @selected(request('stock_status') === 'out')>Out of Stock</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Show</label>
                    <select name="per_page" class="py-1.5 px-2 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <option value="10" @selected($perPage == 10)>10</option>
                        <option value="25" @selected($perPage == 25)>25</option>
                        <option value="50" @selected($perPage == 50)>50</option>
                        <option value="100" @selected($perPage == 100)>100</option>
                    </select>
                </div>
                <button type="submit"
                        class="px-4 py-1.5 rounded-lg text-white text-xs font-medium shadow-xs transition hover:opacity-95 cursor-pointer"
                        style="background-color: #00c0ef;">
                    Filter
                </button>
                <a href="{{ route('inventory.index') }}"
                   class="px-4 py-1.5 rounded-lg text-xs font-medium border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 transition hover:bg-gray-50 dark:hover:bg-gray-700">
                    Reset
                </a>
            </form>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="stock-levels-table">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-3">
                                <a href="{{ $sortLink('product_name') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Product
                                    <span class="text-xs opacity-70">{{ $sort === 'product_name' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Brand</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Category</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Unit</th>
                            <th class="p-3">
                                <a href="{{ $sortLink('quantity') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Quantity
                                    <span class="text-xs opacity-70">{{ $sort === 'quantity' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3">
                                <a href="{{ $sortLink('unit_cost') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Unit Cost
                                    <span class="text-xs opacity-70">{{ $sort === 'unit_cost' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3">
                                <a href="{{ $sortLink('stock_value') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Stock Value
                                    <span class="text-xs opacity-70">{{ $sort === 'stock_value' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Status</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($stockLevels as $stockLevel)
                            @php
                                $stockValue = $stockLevel->quantity * (float) ($stockLevel->unit_cost ?? 0);
                            @endphp
                            <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40 hover:bg-gray-100 dark:hover:bg-gray-800/70 transition">
                                <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $stockLevel->displayName() }}
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-normal">{{ $stockLevel->product?->product_id }}</div>
                                </td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $stockLevel->product?->brand?->name ?? '—' }}</td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $stockLevel->product?->category?->name ?? '—' }}</td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $stockLevel->unit }}</td>
                                <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($stockLevel->quantity) }}</td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ \App\Helpers\Money::format($stockLevel->unit_cost ?? 0) }}</td>
                                <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ \App\Helpers\Money::format($stockValue) }}</td>
                                <td class="p-3">
                                    @if($stockLevel->quantity <= 0)
                                        <span class="badge badge-danger">Out of stock</span>
                                    @elseif($stockLevel->isLowStock())
                                        <span class="badge badge-warning">Low stock</span>
                                    @else
                                        <span class="badge badge-success">In stock</span>
                                    @endif
                                </td>
                                <td class="p-3 text-xs whitespace-nowrap">
                                    <button type="button" @click="openLedger({{ $stockLevel->id }}, @js($stockLevel->displayName()))"
                                            class="inline-flex items-center gap-1 rounded-full border border-indigo-500/60 px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Ledger
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm font-medium">
                                    No stock found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $stockLevels->links() }}
            </div>
        </div>
    </div>

    {{-- Stock Ledger Modal --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-10"
         @keydown.escape.window="modalOpen = false">
        <div class="w-full max-w-3xl rounded-lg bg-white shadow-2xl dark:bg-gray-900 mx-4" @click.stop>
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Stock Ledger — <span x-text="productName"></span>
                </h3>
                <button type="button" @click="modalOpen = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5">
                <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md">
                    <table class="data-table w-full text-left">
                        <thead>
                            <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                                <th class="p-3 text-xs font-bold text-gray-800 dark:text-gray-100">Date</th>
                                <th class="p-3 text-xs font-bold text-gray-800 dark:text-gray-100">Type</th>
                                <th class="p-3 text-xs font-bold text-gray-800 dark:text-gray-100">Change</th>
                                <th class="p-3 text-xs font-bold text-gray-800 dark:text-gray-100">After</th>
                                <th class="p-3 text-xs font-bold text-gray-800 dark:text-gray-100">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="(movement, index) in movements" :key="index">
                                <tr>
                                    <td class="p-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap" x-text="movement.date"></td>
                                    <td class="p-3 text-xs text-gray-900 dark:text-gray-100" x-text="movement.type"></td>
                                    <td class="p-3 text-xs font-medium" :class="movement.quantity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                        x-text="(movement.quantity > 0 ? '+' : '') + movement.quantity"></td>
                                    <td class="p-3 text-xs text-gray-900 dark:text-gray-100" x-text="movement.quantity_after"></td>
                                    <td class="p-3 text-xs text-gray-600 dark:text-gray-300" x-text="movement.note || '—'"></td>
                                </tr>
                            </template>
                            <tr x-show="movements.length === 0">
                                <td colspan="5" class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No movements recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function ledgerModal() {
    return {
        modalOpen: false,
        productName: '',
        movements: [],

        async openLedger(unitId, productName) {
            this.productName = productName;
            this.movements = [];
            this.modalOpen = true;

            try {
                const response = await fetch(`/inventory/${unitId}/movements`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.movements = data.items || [];
            } catch (error) {
                this.movements = [];
            }
        }
    };
}
</script>
@endsection
