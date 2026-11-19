@extends('layouts.app')

@section('title', 'Edit Product — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Product')

@section('content')
@php
    $previewState = [
        'name' => (string) old('name', $product->name),
        'storage' => (string) old('storage', $product->storage ?? ''),
        'color' => (string) old('color', $product->color ?? ''),
        'model' => (string) old('model', $product->model ?? ''),
        'purchasePrice' => (string) old('purchase_price', $product->purchase_price ?? ''),
        'salePrice' => (string) old('sale_price', $product->sale_price ?? ''),
        'quantity' => (string) old('quantity', $product->stockRow?->quantity ?? 0),
        'unit' => (string) old('unit', $product->stockRow?->unit ?? 'Pcs'),
        'status' => (string) old('status', $product->status ?? 'active'),
    ];
@endphp

<div x-data="productFormPreview(@js($previewState))" class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('products.index') }}" class="hover:text-brand-500 transition-colors">Products List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Product</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Product</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-mono font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    {{ $product->product_id }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Update pricing, barcode/IMEI, or stock details. Quantity adjustments automatically log to the stock ledger.</p>
        </div>

        <a href="{{ route('products.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0 self-start sm:self-auto">
            <x-svg.arrow-left class="size-4 text-muted" />
            <span>Back to Products</span>
        </a>
    </div>

    {{-- Validation Alert Banner --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger">
            <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
            <div class="text-xs sm:text-sm">
                <p class="font-bold">Please Enter Valid Data</p>
                <p class="mt-0.5 opacity-90">Check the highlighted fields below and resolve any validation errors before saving.</p>
            </div>
        </div>
    @endif

    {{-- Main 3-Column Form + Live Preview Layout --}}
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3 items-start">
        @csrf
        @method('PATCH')

        {{-- Left 2 Columns: Structured Form Cards --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- Section 1: Core Identity & Classification --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="smartphone" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Product Identity &amp; Classification</h2>
                        <p class="text-xs text-muted mt-0.5">Core naming, manufacturer brand, category, and model number.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Product Name --}}
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Product Name<span class="text-danger ml-0.5">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               x-model="name"
                               value="{{ old('name', $product->name) }}"
                               required
                               placeholder="e.g. iPhone 15 Pro Max, Galaxy S24 Ultra"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                        @error('name')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Product Photo --}}
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label for="photo" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                                Photo
                            </label>
                            <span class="text-[11px] text-muted">Optional — JPG, PNG, WEBP or SVG, max 2 MB</span>
                        </div>
                        <input type="file"
                               id="photo"
                               name="photo"
                               accept=".jpg,.jpeg,.png,.webp,.svg"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-xs sm:text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink hover:file:bg-line cursor-pointer shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('photo') border-danger @enderror">
                        @if($product->photo)
                            <div class="mt-2.5 flex items-center gap-2.5 rounded-xl border border-line bg-soft/50 p-2.5">
                                <x-thumbnail :src="$product->photo_url" :alt="$product->name . ' photo'" :label="$product->name" size="size-10 rounded-lg" />
                                <p class="text-xs text-muted">Current photo — leave empty to keep it.</p>
                            </div>
                        @endif
                        @error('photo')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Brand --}}
                    <div>
                        <label for="brand_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Brand
                        </label>
                        <select id="brand_id" name="brand_id"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('brand_id') border-danger @enderror">
                            <option value="">Select Brand</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Category
                        </label>
                        <select id="category_id" name="category_id"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('category_id') border-danger @enderror">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Model --}}
                    <div>
                        <label for="model" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Model
                        </label>
                        <input type="text"
                               id="model"
                               name="model"
                               x-model="model"
                               value="{{ old('model', $product->model) }}"
                               placeholder="e.g. A2890, SM-S928B"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('model') border-danger @enderror">
                        @error('model')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Status
                        </label>
                        <select id="status" name="status" x-model="status"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('status') border-danger @enderror">
                            <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Inactive</option>
                        </select>
                        @error('status')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 2: Variant Specs & Barcode Scanner --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="sparkles" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Variant Specifications &amp; Barcode / IMEI</h2>
                        <p class="text-xs text-muted mt-0.5">Storage/color specifications and hardware scanner barcode input.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Storage --}}
                    <div>
                        <label for="storage" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Storage
                        </label>
                        <input type="text"
                               id="storage"
                               name="storage"
                               x-model="storage"
                               value="{{ old('storage', $product->storage) }}"
                               placeholder="e.g. 256GB, 512GB, 8GB/256GB"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('storage') border-danger @enderror">
                        @error('storage')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Color --}}
                    <div>
                        <label for="color" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Color
                        </label>
                        <input type="text"
                               id="color"
                               name="color"
                               x-model="color"
                               value="{{ old('color', $product->color) }}"
                               placeholder="e.g. Black, Blue, Natural Titanium"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('color') border-danger @enderror">
                        @error('color')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Barcode / IMEI Scanner Field --}}
                    <div class="sm:col-span-2" x-data="barcodeField()" @barcode-scanned.window="applyScan($event.detail.code)">
                        <label for="barcode" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Barcode / IMEI
                        </label>
                        <div class="flex items-center gap-2.5">
                            <input type="text"
                                   id="barcode"
                                   name="barcode"
                                   x-ref="input"
                                   value="{{ old('barcode', $product->barcode) }}"
                                   placeholder="Scan barcode or type IMEI / EAN"
                                   @keydown.enter.prevent
                                   class="flex-1 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-mono text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('barcode') border-danger @enderror">
                            <button type="button"
                                    @click="startScan()"
                                    :class="scanning ? 'bg-warning text-on-brand' : 'bg-brand-500 hover:bg-brand-600 text-on-brand'"
                                    class="btn-scale inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition-colors shrink-0 cursor-pointer">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M4 5v14M8 5v14M12 5v14M16 5v14M20 5v14"/>
                                </svg>
                                <span x-text="scanning ? 'Scanning…' : 'Scan'">Scan</span>
                            </button>
                        </div>
                        <p x-show="scanning && !lastScan" x-cloak class="mt-1.5 text-xs font-medium text-brand-500">
                            Listening for barcode — scan with your USB/Bluetooth scanner…
                        </p>
                        <p x-show="lastScan" x-cloak class="mt-1.5 text-xs font-medium text-success">
                            Last scanned: <span x-text="lastScan" class="font-mono font-semibold"></span>
                        </p>
                        @error('barcode')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Section 3: Pricing, Stock & Notes --}}
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                        <x-icon name="taka" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Pricing, Stock &amp; Notes</h2>
                        <p class="text-xs text-muted mt-0.5">Update BDT purchase/sale pricing and synchronize current stock quantity.</p>
                    </div>
                </div>

                <div class="p-6 grid gap-5 sm:grid-cols-2">
                    {{-- Purchase Price --}}
                    <div>
                        <label for="purchase_price" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Purchase Price (৳)
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-muted">৳</span>
                            <input type="number"
                                   id="purchase_price"
                                   name="purchase_price"
                                   x-model="purchasePrice"
                                   value="{{ old('purchase_price', $product->purchase_price) }}"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('purchase_price') border-danger @enderror">
                        </div>
                        @error('purchase_price')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Sale Price --}}
                    <div>
                        <label for="sale_price" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Sale Price (৳)
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-muted">৳</span>
                            <input type="number"
                                   id="sale_price"
                                   name="sale_price"
                                   x-model="salePrice"
                                   value="{{ old('sale_price', $product->sale_price) }}"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   class="w-full rounded-xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('sale_price') border-danger @enderror">
                        </div>
                        @error('sale_price')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Quantity --}}
                    <div>
                        <label for="quantity" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Quantity
                        </label>
                        <input type="number"
                               id="quantity"
                               name="quantity"
                               x-model="quantity"
                               value="{{ old('quantity', $product->stockRow?->quantity) }}"
                               step="1"
                               min="0"
                               placeholder="0"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted tabular-nums shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('quantity') border-danger @enderror">
                        @error('quantity')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Unit --}}
                    <div>
                        <label for="unit" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                            Unit
                        </label>
                        <select id="unit" name="unit" x-model="unit"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('unit') border-danger @enderror">
                            @foreach(\App\Models\Unit::UNITS as $unitOption)
                                <option value="{{ $unitOption }}" @selected(old('unit', $product->stockRow?->unit ?? 'Pcs') === $unitOption)>{{ $unitOption }}</option>
                            @endforeach
                        </select>
                        @error('unit')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Note --}}
                    <div class="sm:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                                Note
                            </label>
                            <span class="text-[11px] text-muted">Optional</span>
                        </div>
                        <textarea id="note"
                                  name="note"
                                  rows="3"
                                  placeholder="Additional product details (optional)"
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-y @error('note') border-danger @enderror">{{ old('note', $product->note) }}</textarea>
                        @error('note')
                            <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Form Footer Action Bar --}}
                <div class="border-t border-line px-6 py-4 bg-canvas/30 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ route('products.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface px-5 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Save Changes</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Right Column: Live Margin & Product Meta --}}
        <div class="space-y-5 xl:sticky xl:top-6">
            {{-- Live Product Summary Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Live SKU Preview</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                          :class="status === 'active' ? 'bg-success/10 text-success ring-1 ring-success/20' : 'bg-danger/10 text-danger ring-1 ring-danger/20'">
                        <span class="size-1.5 rounded-full" :class="status === 'active' ? 'bg-success' : 'bg-danger'"></span>
                        <span x-text="status === 'active' ? 'Active' : 'Inactive'">{{ ucfirst($product->status) }}</span>
                    </span>
                </div>

                <div class="rounded-2xl border border-line bg-canvas/40 p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        @if($product->photo)
                            <x-thumbnail :src="$product->photo_url" :alt="$product->name . ' photo'" :label="$product->name" size="size-10 rounded-xl" />
                        @elseif($product->brand)
                            <x-brand-logo :name="$product->brand->name" size="sm" />
                        @endif
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink leading-snug" x-text="displayName()"></p>
                            <p class="text-[11px] font-mono text-muted mt-0.5">
                                {{ $product->product_id }}<span x-show="model.trim()" x-text="' · ' + model.trim()"></span>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-line/70">
                        <div>
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Sale Price</span>
                            <span class="block text-base font-extrabold text-ink tabular-nums mt-0.5" x-text="formatMoney(salePrice)">{{ \App\Helpers\Money::format($product->sale_price) }}</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-muted">Stock On Hand</span>
                            <span class="block text-sm font-bold text-brand-500 tabular-nums mt-0.5" x-text="(parseInt(quantity, 10) || 0) + ' ' + unit">{{ (int) ($product->stockRow?->quantity ?? 0) }} {{ $product->stockRow?->unit ?? 'Pcs' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Profit Margin Meter --}}
                <div class="rounded-2xl border border-line bg-soft/50 p-4 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted font-medium">Est. Unit Profit</span>
                        <span class="font-bold tabular-nums"
                              :class="profit() >= 0 ? 'text-success' : 'text-danger'"
                              x-text="formatMoney(profit())">৳0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted font-medium">Gross Margin</span>
                        <span class="font-bold tabular-nums"
                              :class="marginPct() >= 0 ? 'text-brand-500' : 'text-danger'"
                              x-text="marginPct().toFixed(1) + '%'">0.0%</span>
                    </div>
                </div>
            </div>

            {{-- Ledger Notice Card --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="warehouse" class="size-4" />
                    </div>
                    <h3 class="text-sm font-bold text-ink">Audit &amp; Stock Ledger</h3>
                </div>
                <p class="text-xs text-muted leading-relaxed">
                    Changing the quantity field automatically writes a signed <strong class="text-ink">Adjustment</strong> entry in the stock movement ledger to keep inventory history balanced.
                </p>
                <div class="pt-1 text-[11px] text-muted border-t border-line flex items-center justify-between">
                    <span>Last updated</span>
                    <span class="font-semibold text-ink">{{ $product->updated_at?->format('d M Y, h:i A') ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function productFormPreview(initial) {
    return {
        name: initial.name,
        storage: initial.storage,
        color: initial.color,
        model: initial.model,
        purchasePrice: initial.purchasePrice,
        salePrice: initial.salePrice,
        quantity: initial.quantity,
        unit: initial.unit,
        status: initial.status,

        displayName() {
            const base = this.name.trim() || 'Product Name';
            const parts = [this.storage.trim(), this.color.trim()].filter(Boolean);
            return parts.length ? `${base} (${parts.join(' / ')})` : base;
        },

        profit() {
            const sale = parseFloat(this.salePrice) || 0;
            const purchase = parseFloat(this.purchasePrice) || 0;
            return sale - purchase;
        },

        marginPct() {
            const sale = parseFloat(this.salePrice) || 0;
            if (sale <= 0) return 0;
            return (this.profit() / sale) * 100;
        },

        formatMoney(value) {
            const num = parseFloat(value) || 0;
            if (typeof window.formatBDT === 'function') {
                return window.formatBDT(num);
            }
            const sign = num < 0 ? '-' : '';
            return `${sign}৳${Math.abs(num).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
    };
}
</script>
@endsection
