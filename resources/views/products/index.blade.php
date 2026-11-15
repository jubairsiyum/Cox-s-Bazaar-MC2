@extends('layouts.app')

@section('title', 'Products List')
@section('page_title', 'Products List')

@section('content')
<div class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Products List</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">View/Search Products</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Home
                </a>
                <span>&rsaquo;</span>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Products List</span>
            </div>
            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}"
                   class="px-4 py-2 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-1.5 hover:opacity-95"
                   style="background-color: #00c0ef;">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Product
                </a>
            @endcan
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-header border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Products List</h2>
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Filters, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                    @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                    <label class="text-gray-600 dark:text-gray-400 flex items-center gap-1 text-xs sm:text-sm">
                        Show
                        <select name="per_page" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <option value="10" @selected($perPage == 10)>10</option>
                            <option value="25" @selected($perPage == 25)>25</option>
                            <option value="50" @selected($perPage == 50)>50</option>
                            <option value="100" @selected($perPage == 100)>100</option>
                        </select>
                        entries
                    </label>
                </form>

                <div class="flex items-center gap-2 flex-wrap">
                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                        <select name="brand_id" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <option value="">All Brands</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                        <select name="category_id" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                        @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                        <select name="status" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <option value="">All Status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                    </form>
                </div>

                <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    @if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif
                    @if(request('category_id'))<input type="hidden" name="category_id" value="{{ request('category_id') }}">@endif
                    <label class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5 text-xs sm:text-sm">
                        Search:
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Name, model, barcode, brand..."
                               class="py-1 px-2.5 text-xs rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-cyan-500">
                    </label>
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="products-table">
                    <thead>
                        <tr style="background-color: #337ab7;">
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">PRODUCT ID</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">PRODUCT</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">MODEL</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">BARCODE / IMEI</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">SALE PRICE</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider" style="color: #ffffff !important;">STATUS</th>
                            <th class="p-2.5 font-bold text-xs tracking-wider text-right" style="color: #ffffff !important;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @forelse($products as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
                                <td class="p-2.5 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ $product->product_id }}</td>
                                <td class="p-2.5">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $product->brand?->name ?? '—' }} &middot; {{ $product->category?->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="p-2.5 text-gray-600 dark:text-gray-300">{{ $product->model ?: '—' }}</td>
                                <td class="p-2.5 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ $product->barcode ?: '—' }}</td>
                                <td class="p-2.5 text-gray-900 dark:text-gray-100 font-semibold">{{ \App\Helpers\Money::format($product->sale_price) }}</td>
                                <td class="p-2.5">
                                    @can('update', $product)
                                        <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Click to change status to {{ $product->status === 'active' ? 'Inactive' : 'Active' }}"
                                                    class="cursor-pointer inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-white transition hover:opacity-80 active:scale-95 shadow-xs"
                                                    style="background-color: {{ $product->status === 'active' ? '#00a65a' : '#dd4b39' }};">
                                                {{ ucfirst($product->status) }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-white"
                                              style="background-color: {{ $product->status === 'active' ? '#00a65a' : '#dd4b39' }};">
                                            {{ ucfirst($product->status) }}
                                        </span>
                                    @endcan
                                </td>
                                <td class="p-2.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        @can('update', $product)
                                            <a href="{{ route('products.edit', $product) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white rounded shadow-xs hover:opacity-90"
                                               style="background-color: #f39c12;">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </a>
                                        @endcan
                                        @can('delete', $product)
                                            <form method="POST" action="{{ route('products.destroy', $product) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white rounded shadow-xs hover:opacity-90"
                                                        style="background-color: #dd4b39;">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-500 dark:text-gray-400 font-medium">
                                    No products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
