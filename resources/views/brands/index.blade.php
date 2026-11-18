@extends('layouts.app')

@section('title', 'Brands')
@section('page_title', 'Brands')

@section('content')
@php
    $modalState = [
        'hasErrors' => $errors->any(),
        'mode' => old('_form_mode', 'add'),
        'brandId' => (int) old('brand_id', 0) ?: null,
        'name' => (string) old('name', ''),
        'note' => (string) old('note', ''),
        'logoUrl' => null,
    ];

    $sortLink = fn (string $col) => route('brands.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));
@endphp

<div x-data="brandsPage(@js($modalState))" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-ink">Brands</h1>
        <span class="text-sm text-muted">Manage your brands</span>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-line">
            <h2 class="text-lg font-semibold text-ink">All your brands</h2>
            @can('create', App\Models\Brand::class)
                <button type="button" @click="openAdd()"
                        class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-on-brand shadow-md transition hover:opacity-95 cursor-pointer"
                        style="background: linear-gradient(135deg, var(--brand-500) 0%, var(--brand-600) 100%);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </button>
            @endcan
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('brands.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <label class="text-muted flex items-center gap-1.5 text-xs sm:text-sm">
                        Show
                        <select name="per_page" onchange="this.form.submit()"
                                class="form-select py-1 px-2 text-xs rounded border-line bg-surface">
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
                    <a :href="exportUrl('csv')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition hover:bg-canvas cursor-pointer">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export CSV
                    </a>
                    <a :href="exportUrl('xlsx')"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition hover:bg-canvas cursor-pointer">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Excel
                    </a>
                    <button type="button" @click="printTable()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition hover:bg-canvas cursor-pointer">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition hover:bg-canvas cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            Column visibility
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-44 rounded-lg shadow-2xl py-1 z-50 text-left border border-line bg-surface">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-ink transition hover:bg-soft text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <button type="button" @click="openPdf()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-xs transition hover:bg-canvas cursor-pointer">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export PDF
                        <svg class="size-3 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('brands.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search ..."
                           class="py-1.5 px-3 text-xs rounded-lg border border-line bg-surface text-ink focus:outline-hidden focus:border-brand-500">
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-line rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="brands-table">
                    <thead>
                        <tr class="bg-surface border-b border-line">
                            <th x-show="columns.name" class="p-3">
                                <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 text-sm font-bold text-ink hover:text-brand-500">
                                    Brands
                                    <span class="text-xs opacity-70">{{ $sort === 'name' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th x-show="columns.logo" class="p-3 text-sm font-bold text-ink">Logo</th>
                            <th x-show="columns.note" class="p-3">
                                <a href="{{ $sortLink('note') }}" class="flex items-center gap-1 text-sm font-bold text-ink hover:text-brand-500">
                                    Note
                                    <span class="text-xs opacity-70">{{ $sort === 'note' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            @can('brands.manage')
                                <th class="p-3 text-sm font-bold text-ink">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-surface">
                        @forelse($brands as $brand)
                            <tr class="odd:bg-canvas dark:odd:bg-surface/40 hover:bg-soft/70 transition">
                                <td x-show="columns.name" data-col="name" class="p-3 text-sm font-medium text-ink">
                                    {{ $brand->name }}
                                </td>
                                <td x-show="columns.logo" data-col="logo" class="p-3">
                                    @if($brand->logo)
                                        <x-thumbnail :src="$brand->logo_url" :alt="$brand->name . ' logo'" :label="$brand->name" />
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td x-show="columns.note" data-col="note" class="p-3 text-sm text-muted">
                                    {{ $brand->note ?: '—' }}
                                </td>
                                @can('brands.manage')
                                    <td class="p-3 text-xs whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @can('update', $brand)
                                                <button type="button"
                                                        @click="openEdit(@js(['id' => $brand->id, 'name' => $brand->name, 'note' => $brand->note, 'logoUrl' => $brand->logo_url]))"
                                                        class="inline-flex items-center gap-1 rounded-full border border-brand-500/60 px-3 py-1 text-xs font-medium text-brand-500 transition hover:bg-soft cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Edit
                                                </button>
                                            @endcan
                                            @can('delete', $brand)
                                                <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                                      onsubmit="return confirm('Are you sure you want to delete this brand?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 rounded-full border border-danger/70 px-3 py-1 text-xs font-medium text-danger transition hover:bg-soft cursor-pointer">
                                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="p-8 text-center text-muted text-sm font-medium">
                                    No brands found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $brands->links() }}
            </div>
        </div>
    </div>

    {{-- Add / Edit Brand Modal --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-overlay py-10"
         @keydown.escape.window="closeModal()">
        <div class="w-full max-w-xl rounded-lg bg-surface shadow-2xl mx-4" @click.stop>
            <form :action="formAction()" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" :value="editMode ? 'PATCH' : 'POST'">
                <input type="hidden" name="_form_mode" :value="editMode ? 'edit' : 'add'">
                <input type="hidden" name="brand_id" :value="brandId ?? ''">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h3 class="text-lg font-semibold text-ink"
                        x-text="editMode ? 'Edit brand' : 'Add brand'"></h3>
                    <button type="button" @click="closeModal()"
                            class="text-muted hover:text-muted transition cursor-pointer">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label for="brand-name" class="mb-1.5 block text-sm font-bold text-ink">
                            Brand name<span class="text-danger">*</span>:
                        </label>
                        <input type="text" id="brand-name" name="name" x-model="form.name" required
                               placeholder="Brand name"
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-muted focus:outline-hidden focus:border-brand-500 @error('name') border-danger @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="brand-note" class="mb-1.5 block text-sm font-bold text-ink">
                            Short description:
                        </label>
                        <input type="text" id="brand-note" name="note" x-model="form.note"
                               placeholder="Short description"
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-muted focus:outline-hidden focus:border-brand-500 @error('note') border-danger @enderror">
                        @error('note')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="brand-logo" class="mb-1.5 block text-sm font-bold text-ink">
                            Logo:
                        </label>
                        <input type="file" id="brand-logo" name="logo" x-ref="logoInput"
                               accept=".jpg,.jpeg,.png,.webp,.svg"
                               class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink file:mr-3 file:rounded file:border-0 file:bg-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink cursor-pointer focus:outline-hidden focus:border-brand-500 @error('logo') border-danger @enderror">
                        <p class="mt-1 text-xs text-muted">JPG, PNG, WEBP or SVG — max 2 MB.</p>
                        <div x-show="logoUrl" class="mt-2 flex items-center gap-2">
                            <img :src="logoUrl" alt="Current brand logo"
                                 class="size-10 rounded border border-line bg-surface object-contain p-0.5"
                                 onerror="this.remove()">
                            <p class="text-xs text-muted">Current logo — leave empty to keep it.</p>
                        </div>
                        @error('logo')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-end gap-3 border-t border-line px-6 py-4">
                    <button type="submit"
                            class="rounded-lg px-6 py-2 text-sm font-semibold text-on-brand shadow-sm transition hover:opacity-95 cursor-pointer"
                            style="background-color: var(--brand-500);">
                        Save
                    </button>
                    <button type="button" @click="closeModal()"
                            class="rounded-lg px-6 py-2 text-sm font-semibold text-on-brand shadow-sm transition hover:opacity-95 cursor-pointer"
                            style="background-color: var(--brand-500);">
                        Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function brandsPage(state) {
    return {
        modalOpen: state.hasErrors,
        editMode: state.mode === 'edit',
        brandId: state.brandId,
        logoUrl: state.logoUrl,
        form: {
            name: state.name,
            note: state.note
        },

        columnsDropdownOpen: false,
        columns: {
            name: true,
            logo: true,
            note: true
        },
        columnList: [
            { key: 'name', label: 'Brands' },
            { key: 'logo', label: 'Logo' },
            { key: 'note', label: 'Note' }
        ],

        createAction: @js(route('brands.store')),

        formAction() {
            return this.editMode ? @js(url('brands')) + '/' + this.brandId : this.createAction;
        },

        openAdd() {
            this.editMode = false;
            this.brandId = null;
            this.logoUrl = null;
            this.form = { name: '', note: '' };
            this.resetLogoInput();
            this.modalOpen = true;
        },

        openEdit(brand) {
            this.editMode = true;
            this.brandId = brand.id;
            this.logoUrl = brand.logoUrl ?? null;
            this.form = { name: brand.name, note: brand.note ?? '' };
            this.resetLogoInput();
            this.modalOpen = true;
        },

        resetLogoInput() {
            if (this.$refs.logoInput) {
                this.$refs.logoInput.value = '';
            }
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
            return `/brands/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/brands/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}'
            });
            window.open(`/brands/print-view?${params.toString()}`, '_blank');
        }
    };
}
</script>
@endsection
