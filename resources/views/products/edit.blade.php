@extends('layouts.app')

@section('title', 'Edit Product')
@section('page_title', 'Product')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Product</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">Add/Update Product</span>
            </div>
        </div>
        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ route('dashboard') }}" class="hover:text-brand-500 flex items-center gap-1">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Home
            </a>
            <span>&rsaquo;</span>
            <a href="{{ route('products.index') }}" class="hover:text-brand-500">Products List</a>
            <span>&rsaquo;</span>
            <span class="text-gray-700 dark:text-gray-300 font-medium">Product</span>
        </div>
    </div>

    {{-- Main Form Card --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        @if ($errors->any())
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                Please Enter Valid Data
            </div>
        @endif

        <div class="card-body">
            <form method="POST" action="{{ route('products.update', $product) }}">
                @csrf
                @method('PATCH')

                <div class="max-w-3xl space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="name"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Product Name<span class="text-red-500 font-bold">*</span>
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. iPhone 15 Pro Max, Galaxy S24 Ultra">
                            @error('name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="brand_id"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Brand
                        </label>
                        <div class="sm:col-span-2">
                            <select id="brand_id" name="brand_id" class="form-control @error('brand_id') is-invalid @enderror">
                                <option value="">Select Brand</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            @error('brand_id')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="category_id"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Category
                        </label>
                        <div class="sm:col-span-2">
                            <select id="category_id" name="category_id" class="form-control @error('category_id') is-invalid @enderror">
                                <option value="">Select Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="model"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Model
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="model" name="model" value="{{ old('model', $product->model) }}"
                                class="form-control @error('model') is-invalid @enderror"
                                placeholder="e.g. A2890, SM-S928B">
                            @error('model')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Barcode scanner field --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="barcode"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Barcode / IMEI
                        </label>
                        <div class="sm:col-span-2" x-data="barcodeField()" @barcode-scanned.window="applyScan($event.detail.code)">
                            <div class="flex items-center gap-2">
                                <input type="text" id="barcode" name="barcode" x-ref="input"
                                    value="{{ old('barcode', $product->barcode) }}"
                                    placeholder="Scan barcode or type IMEI / EAN"
                                    @keydown.enter.prevent
                                    class="form-control @error('barcode') is-invalid @enderror font-mono">
                                <button type="button" @click="focus()"
                                        class="px-3 py-2.5 rounded-md text-white text-sm font-medium flex items-center gap-1.5 shrink-0 shadow-sm transition hover:opacity-90"
                                        style="background-color: #00c0ef;">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M4 5v14M8 5v14M12 5v14M16 5v14M20 5v14"/>
                                    </svg>
                                    Scan
                                </button>
                            </div>
                            <p x-show="lastScan" x-cloak class="mt-1.5 text-xs text-green-600 dark:text-green-400">
                                Last scanned: <span x-text="lastScan" class="font-mono font-semibold"></span>
                            </p>
                            @error('barcode')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="storage"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Storage
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="storage" name="storage" value="{{ old('storage', $product->storage) }}"
                                class="form-control @error('storage') is-invalid @enderror"
                                placeholder="e.g. 256GB, 512GB, 8GB/256GB">
                            @error('storage')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="color"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Color
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="color" name="color" value="{{ old('color', $product->color) }}"
                                class="form-control @error('color') is-invalid @enderror"
                                placeholder="e.g. Black, Blue, Natural Titanium">
                            @error('color')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="purchase_price"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Purchase Price (৳)
                        </label>
                        <div class="sm:col-span-2">
                            <input type="number" id="purchase_price" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}"
                                step="0.01" min="0"
                                class="form-control @error('purchase_price') is-invalid @enderror"
                                placeholder="0.00">
                            @error('purchase_price')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="sale_price"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Sale Price (৳)
                        </label>
                        <div class="sm:col-span-2">
                            <input type="number" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->sale_price) }}"
                                step="0.01" min="0"
                                class="form-control @error('sale_price') is-invalid @enderror"
                                placeholder="0.00">
                            @error('sale_price')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="status"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Status
                        </label>
                        <div class="sm:col-span-2">
                            <select id="status" name="status" class="form-control @error('status') is-invalid @enderror">
                                <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Inactive</option>
                            </select>
                            @error('status')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-start gap-2">
                        <label for="note"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right pt-2">
                            Note
                        </label>
                        <div class="sm:col-span-2">
                            <textarea id="note" name="note" rows="3"
                                class="form-control @error('note') is-invalid @enderror"
                                placeholder="Additional product details (optional)">{{ old('note', $product->note) }}</textarea>
                            @error('note')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div
                    class="mt-8 pt-4 border-t border-gray-100 dark:border-gray-800 flex justify-center items-center gap-4">
                    <button type="submit"
                        class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                        style="background-color: #00a65a;">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Save
                    </button>
                    <a href="{{ route('products.index') }}"
                        class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                        style="background-color: #f39c12;">
                        Close
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
