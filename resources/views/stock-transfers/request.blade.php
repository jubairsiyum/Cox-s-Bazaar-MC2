@extends('layouts.app')

@section('title', 'Request Stock — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Transfers')

@section('content')
<div class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <a href="{{ route('stock-transfers.index') }}" class="hover:text-brand-500 transition-colors">Stock Transfers</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Request Stock</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Request Stock</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    {{ $branchName }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Ask another location for stock. Nothing moves until that location approves your request.</p>
        </div>

        <a href="{{ route('stock-transfers.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Transfers</span>
        </a>
    </div>

    <form method="POST" action="{{ route('stock-transfers.requests.store') }}" x-data="stockRequestForm({ items: @js($items) })" class="space-y-6">
        @csrf

        {{-- Validation Summary --}}
        @if ($errors->any())
            <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-danger/15 text-danger">
                    <x-icon name="alert-triangle" class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-danger">Please Enter Valid Data</p>
                    <ul class="mt-1 list-disc list-inside space-y-0.5 text-xs text-ink/90">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Card 1: Source --}}
        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-line">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="truck" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Request From</h2>
                    <p class="text-xs text-muted mt-0.5">Choose the warehouse or brand shop that should send the stock.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Requesting Branch</label>
                    <p class="w-full rounded-xl border border-line bg-soft/60 px-3.5 py-2.5 text-sm font-semibold text-ink">{{ $branchName }}</p>
                </div>

                <div>
                    <label for="from_location_id" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Request From <span class="text-danger">*</span>
                    </label>
                    <select id="from_location_id" name="from_location_id" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('from_location_id') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}" @selected(old('from_location_id') == $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                    @error('from_location_id')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Card 2: Products --}}
        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 mb-5 border-b border-line">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="box" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Products & Quantities</h2>
                        <p class="text-xs text-muted mt-0.5">Search for the products you need and enter how many you want.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums self-start sm:self-auto">
                    <span x-text="items.length"></span>
                    <span x-text="items.length === 1 ? 'product' : 'products'"></span>
                </span>
            </div>

            {{-- Interactive Product Search --}}
            <div class="relative max-w-2xl mx-auto mb-5" @click.outside="searchResults = []">
                <div class="relative flex items-center">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-muted">
                        <x-icon name="search" class="size-4" />
                    </span>
                    <input type="text"
                           x-model="searchQuery"
                           @input.debounce.300ms="searchProducts()"
                           @keydown.escape="searchResults = []"
                           placeholder="Search products to request..."
                           class="w-full rounded-2xl border border-line bg-canvas/50 pl-11 pr-10 py-3 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:bg-surface focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all">
                    <span x-show="searching" x-cloak class="absolute inset-y-0 right-0 pr-3.5 flex items-center">
                        <span class="size-4 rounded-full border-2 border-brand-500 border-t-transparent animate-spin"></span>
                    </span>
                </div>

                <div x-show="searchResults.length > 0" x-cloak
                     class="absolute z-30 mt-2 w-full rounded-2xl border border-line bg-surface shadow-card max-h-64 overflow-auto p-1.5">
                    <template x-for="product in searchResults" :key="product.id">
                        <button type="button" @click="addProduct(product)"
                                class="w-full flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 text-left hover:bg-soft transition-colors cursor-pointer">
                            <div class="min-w-0">
                                <span class="block text-sm font-semibold text-ink truncate" x-text="product.product_name"></span>
                                <span class="block text-[11px] text-muted" x-text="`Unit: ${product.unit}`"></span>
                            </div>
                            <span class="shrink-0 inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-xs font-bold tabular-nums text-muted ring-1 ring-line"
                                  x-text="`At your branch: ${product.quantity}`"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="rounded-2xl border border-line overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                                <th class="py-3.5 px-4">Product</th>
                                <th class="py-3.5 px-4">Quantity</th>
                                <th class="py-3.5 px-4 text-center w-14">
                                    <span class="sr-only">Remove</span>
                                    <x-svg.trash-bin class="size-4 inline-block text-muted" />
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-surface">
                            <template x-for="(item, index) in items" :key="`${item.unit_id}-${index}`">
                                <tr class="hover:bg-soft/30 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="text-sm font-semibold text-ink" x-text="item.product_name"></div>
                                        <span class="mt-0.5 inline-block rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-ink border border-line" x-text="item.unit || 'Unit'"></span>
                                        <input type="hidden" :name="`items[${index}][unit_id]`" :value="item.unit_id">
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <input type="number" min="1" step="1"
                                               :name="`items[${index}][quantity]`"
                                               x-model="item.quantity"
                                               class="w-28 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink tabular-nums focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button type="button" @click="removeItem(index)"
                                                class="inline-flex size-8 items-center justify-center rounded-xl text-danger hover:bg-danger/10 transition-colors cursor-pointer"
                                                title="Remove row">
                                            <x-svg.trash-bin class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="items.length === 0">
                                <td colspan="3" class="py-10 px-6 text-center text-xs text-muted">
                                    No products added yet. Use the search box above to add products to this request.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @error('items')
                <p class="mt-2 text-xs font-medium text-danger">{{ $message }}</p>
            @enderror
            @foreach ($errors->get('items.*') as $messages)
                @foreach ($messages as $message)
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @endforeach
            @endforeach
        </div>

        {{-- Card 3: Note & Actions --}}
        <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
            <div>
                <label for="additional_notes" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                    Note
                </label>
                <textarea id="additional_notes" name="additional_notes" rows="3"
                          placeholder="Why you need this stock, urgency, or delivery notes (optional)"
                          class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('additional_notes') border-danger @enderror">{{ old('additional_notes') }}</textarea>
                @error('additional_notes')
                    <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 pt-5 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('stock-transfers.index') }}"
                   class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Send Request</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function stockRequestForm(state) {
    return {
        items: state.items || [],
        searchQuery: '',
        searchResults: [],
        searching: false,

        async searchProducts() {
            const query = this.searchQuery.trim();

            if (query.length === 0) {
                this.searchResults = [];
                return;
            }

            this.searching = true;
            try {
                const response = await fetch(@js(route('stock-transfers.search-units')) + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                this.searchResults = data.items || [];
            } catch (error) {
                this.searchResults = [];
            } finally {
                this.searching = false;
            }
        },

        addProduct(product) {
            const existing = this.items.find(item => item.unit_id === product.id);

            if (existing) {
                existing.quantity = (parseInt(existing.quantity, 10) || 0) + 1;
            } else {
                this.items.push({
                    unit_id: product.id,
                    product_name: product.product_name,
                    unit: product.unit,
                    quantity: 1
                });
            }

            this.searchQuery = '';
            this.searchResults = [];
        },

        removeItem(index) {
            this.items.splice(index, 1);
        }
    };
}
</script>
@endsection
