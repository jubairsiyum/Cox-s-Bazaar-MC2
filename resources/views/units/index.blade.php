@extends('layouts.app')

@section('title', 'Units')
@section('page_title', 'Units')

@section('content')
@php
    $modalState = [
        'hasErrors' => $errors->any(),
        'mode' => old('_form_mode', 'add'),
        'unitId' => (int) old('unit_id', 0) ?: null,
        'productName' => (string) old('product_name', ''),
        'quantity' => (string) old('quantity', ''),
        'unit' => (string) old('unit', 'Pcs'),
        'note' => (string) old('note', ''),
    ];

    $sortLink = fn (string $col) => route('units.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));
@endphp

<div x-data="unitsPage(@js($modalState))" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Units</h1>
        <span class="text-sm text-gray-500 dark:text-gray-400">Manage your quantity data</span>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">All your units</h2>
            <div class="flex items-center gap-2">
                @can('import', App\Models\Unit::class)
                    <a href="{{ route('units.import') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="size-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import
                    </a>
                @endcan
                @can('create', App\Models\Unit::class)
                    <button type="button" @click="openAdd()"
                            class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95 cursor-pointer"
                            style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add
                    </button>
                @endcan
            </div>
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('units.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <label class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5 text-xs sm:text-sm">
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

                {{-- Middle: Export Buttons --}}
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('units.import-template') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Sample CSV
                    </a>
                    <a :href="exportUrl('csv')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </a>
                    <a :href="exportUrl('xlsx')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Excel
                    </a>
                    <button type="button" @click="printTable()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                            <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            Column visibility
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-44 rounded-lg shadow-2xl py-1 z-50 text-left border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-100 dark:hover:bg-gray-700 text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <button type="button" @click="openPdf()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-xs transition hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                        <svg class="size-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export PDF
                        <svg class="size-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('units.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search ..."
                           class="py-1.5 px-3 text-xs rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-indigo-500">
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="units-table">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th x-show="columns.product_name" class="p-3">
                                <a href="{{ $sortLink('product_name') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Product Name
                                    <span class="text-xs opacity-70">{{ $sort === 'product_name' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.quantity" class="p-3">
                                <a href="{{ $sortLink('quantity') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Quantity
                                    <span class="text-xs opacity-70">{{ $sort === 'quantity' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.unit" class="p-3">
                                <a href="{{ $sortLink('unit') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Unit
                                    <span class="text-xs opacity-70">{{ $sort === 'unit' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.note" class="p-3">
                                <a href="{{ $sortLink('note') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Note
                                    <span class="text-xs opacity-70">{{ $sort === 'note' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($units as $unit)
                            <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40 hover:bg-gray-100 dark:hover:bg-gray-800/70 transition">
                                <td x-show="columns.product_name" data-col="product_name" class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $unit->product_name }}
                                </td>
                                <td x-show="columns.quantity" data-col="quantity" class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format((float) $unit->quantity) }}
                                    @if($unit->isLowStock())
                                        <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:text-amber-400">
                                            LOW STOCK
                                        </span>
                                    @endif
                                </td>
                                <td x-show="columns.unit" data-col="unit" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                                        {{ $unit->unit }}
                                    </span>
                                </td>
                                <td x-show="columns.note" data-col="note" class="p-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $unit->note ?: '—' }}
                                </td>
                                <td class="p-3 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @can('update', $unit)
                                            <button type="button"
                                                    @click="openEdit(@js(['id' => $unit->id, 'productName' => $unit->product_name, 'quantity' => (string) $unit->quantity, 'unit' => $unit->unit, 'note' => $unit->note]))"
                                                    class="inline-flex items-center gap-1 rounded-full border border-indigo-500/60 px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </button>
                                        @endcan
                                        @can('delete', $unit)
                                            <form method="POST" action="{{ route('units.destroy', $unit) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete this quantity entry?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-full border border-red-400/70 px-3 py-1 text-xs font-medium text-red-500 dark:text-red-400 transition hover:bg-red-50 dark:hover:bg-red-500/10 cursor-pointer">
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
                                <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="p-8 text-center text-gray-500 dark:text-gray-400 font-medium">
                                    No quantity entries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $units->links() }}
            </div>
        </div>
    </div>

    {{-- Add / Edit Quantity Modal --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 py-10"
         @keydown.escape.window="closeModal()">
        <div class="w-full max-w-xl rounded-lg bg-white shadow-2xl dark:bg-gray-900 mx-4" @click.stop>
            <form :action="formAction()" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="editMode ? 'PATCH' : 'POST'">
                <input type="hidden" name="_form_mode" :value="editMode ? 'edit' : 'add'">
                <input type="hidden" name="unit_id" :value="unitId ?? ''">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                        x-text="editMode ? 'Edit quantity' : 'Add quantity'"></h3>
                    <button type="button" @click="closeModal()"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition cursor-pointer">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label for="unit-product-name" class="mb-1.5 block text-sm font-bold text-gray-800 dark:text-gray-200">
                            Product name<span class="text-red-500">*</span>:
                        </label>
                        <input type="text" id="unit-product-name" name="product_name" x-model="form.productName" required
                               placeholder="e.g. Phone Charger 20W, Screen Protector"
                               class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-hidden focus:border-indigo-500 @error('product_name') border-red-500 @enderror">
                        @error('product_name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="unit-quantity" class="mb-1.5 block text-sm font-bold text-gray-800 dark:text-gray-200">
                                Quantity<span class="text-red-500">*</span>:
                            </label>
                            <input type="number" step="1" min="0" id="unit-quantity" name="quantity" x-model="form.quantity" required
                                   placeholder="e.g. 50"
                                   class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-hidden focus:border-indigo-500 @error('quantity') border-red-500 @enderror">
                            @error('quantity')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="unit-unit" class="mb-1.5 block text-sm font-bold text-gray-800 dark:text-gray-200">
                                Unit<span class="text-red-500">*</span>:
                            </label>
                            <select id="unit-unit" name="unit" x-model="form.unit" required
                                    class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-hidden focus:border-indigo-500 @error('unit') border-red-500 @enderror">
                                @foreach (\App\Models\Unit::UNITS as $unitOption)
                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                @endforeach
                            </select>
                            @error('unit')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="unit-note" class="mb-1.5 block text-sm font-bold text-gray-800 dark:text-gray-200">
                            Note:
                        </label>
                        <input type="text" id="unit-note" name="note" x-model="form.note"
                               placeholder="e.g. 10 pcs per box"
                               class="w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400 focus:outline-hidden focus:border-indigo-500 @error('note') border-red-500 @enderror">
                        @error('note')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-end gap-3 border-t border-gray-100 dark:border-gray-800 px-6 py-4">
                    <button type="submit"
                            class="rounded-lg px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 cursor-pointer"
                            style="background-color: #4F46E5;">
                        Save
                    </button>
                    <button type="button" @click="closeModal()"
                            class="rounded-lg px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 cursor-pointer"
                            style="background-color: #1F2937;">
                        Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function unitsPage(state) {
    return {
        modalOpen: state.hasErrors,
        editMode: state.mode === 'edit',
        unitId: state.unitId,
        form: {
            productName: state.productName,
            quantity: state.quantity,
            unit: state.unit,
            note: state.note
        },

        columnsDropdownOpen: false,
        columns: {
            product_name: true,
            quantity: true,
            unit: true,
            note: true
        },
        columnList: [
            { key: 'product_name', label: 'Product Name' },
            { key: 'quantity', label: 'Quantity' },
            { key: 'unit', label: 'Unit' },
            { key: 'note', label: 'Note' }
        ],

        createAction: @js(route('units.store')),

        formAction() {
            return this.editMode ? @js(url('units')) + '/' + this.unitId : this.createAction;
        },

        openAdd() {
            this.editMode = false;
            this.unitId = null;
            this.form = { productName: '', quantity: '', unit: 'Pcs', note: '' };
            this.modalOpen = true;
        },

        openEdit(unit) {
            this.editMode = true;
            this.unitId = unit.id;
            this.form = {
                productName: unit.productName,
                quantity: unit.quantity,
                unit: unit.unit ?? 'Pcs',
                note: unit.note ?? ''
            };
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
        },

        toggleColumn(key) {
            this.columns[key] = !this.columns[key];
        },

        activeColumnsQuery() {
            return Object.keys(this.columns)
                .filter(k => this.columns[k])
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            return `/units/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/units/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/units/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
