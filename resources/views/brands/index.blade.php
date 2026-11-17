@extends('layouts.app')

@section('title', 'Brands — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Brands')

@section('content')
@php
$modalState = [
'hasErrors' => $errors->any(),
'mode' => old('_form_mode', 'add'),
'brandId' => (int) old('brand_id', 0) ?: null,
'name' => (string) old('name', ''),
'note' => (string) old('note', ''),
];

$sortLink = fn (string $col) => route('brands.index', array_filter([
'search' => request('search'),
'per_page' => request('per_page', 15),
'sort' => $col,
'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
]));

// Metric summaries
$totalBrandsCount = $brands->total();
$pageWithNotesCount = $brands->filter(fn ($b) => !empty($b->note))->count();
@endphp

<div x-data="brandsPage(@js($modalState))" class="space-y-6">

    {{-- Breadcrumb Navigation & Top Action Bar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-1.5" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Brands</span>
            </nav>

            {{-- Title & Subtitle --}}
            <div class="flex items-baseline gap-2.5 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Brands</h1>
                <span class="text-xs sm:text-sm text-muted">Manage your brands</span>
                <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-xs font-semibold text-brand-500">
                    {{ $totalBrandsCount }} {{ Str::plural('brand', $totalBrandsCount) }}
                </span>
            </div>
        </div>

        {{-- Top Right Actions --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request('search') || request('sort'))
            <a href="{{ route('brands.index') }}"
                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-medium text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                title="Reset filters">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Reset</span>
            </a>
            @endif

            @can('create', App\Models\Brand::class)
            <button type="button" @click="openAdd()"
                class="btn-scale inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                <x-icon name="plus" class="size-4" />
                <span>Add Brand</span>
            </button>
            @endcan
        </div>
    </div>

    {{-- Executive Metric Summary KPI Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Card 1: Total Brands --}}
        <div class="rounded-2xl border border-line bg-surface p-4 sm:p-5 shadow-card transition-all">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <span class="text-xs font-medium text-muted uppercase tracking-wider">Total Brands</span>
                    <h3 class="text-2xl font-bold tracking-tight text-ink mt-1">{{ number_format($totalBrandsCount) }}</h3>
                    <p class="text-xs text-muted mt-1">Registered manufacturer profiles</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="sparkles" class="size-5" />
                </div>
            </div>
        </div>

        {{-- Card 2: Documented Portfolio --}}
        <div class="rounded-2xl border border-line bg-surface p-4 sm:p-5 shadow-card transition-all">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <span class="text-xs font-medium text-muted uppercase tracking-wider">Catalog Documentation</span>
                    <h3 class="text-2xl font-bold tracking-tight text-ink mt-1">
                        {{ $pageWithNotesCount }} <span class="text-sm font-normal text-muted">/ {{ $brands->count() }} on page</span>
                    </h3>
                    <p class="text-xs text-muted mt-1">Profiles with detailed notes</p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success ring-1 ring-success/20">
                    <x-icon name="box" class="size-5" />
                </div>
            </div>
        </div>

        {{-- Card 3: Quick Filter & Sort State --}}
        <div class="rounded-2xl border border-line bg-surface p-4 sm:p-5 shadow-card transition-all sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-xs font-medium text-muted uppercase tracking-wider">Listing Scope</span>
                    <h3 class="text-base font-bold tracking-tight text-ink mt-1 truncate">
                        @if(request('search'))
                        "{{ request('search') }}"
                        @else
                        Active Catalog
                        @endif
                    </h3>
                    <p class="text-xs text-muted mt-1 truncate">
                        Sorted by <span class="font-medium text-ink">{{ ucfirst($sort) }}</span> ({{ strtoupper($direction) }})
                    </p>
                </div>
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="search" class="size-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Main Table Container Card --}}
    <div class="rounded-2xl border border-line bg-surface shadow-card overflow-hidden">
        {{-- Card Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-line px-5 py-4 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-semibold text-ink">All your brands</h2>
                <p class="text-xs text-muted mt-0.5">Browse, search, edit, and export your manufacturer brand lines</p>
            </div>

        </div>

        {{-- Interactive Toolbar: Per page, Exports, Column Visibility & Search --}}
        <div class="p-4 sm:p-5 border-b border-line bg-canvas/30">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {{-- Left: Show Entries & Search Bar --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-1 min-w-0">
                    {{-- Per Page Selector Form --}}
                    <form method="GET" action="{{ route('brands.index') }}" class="flex items-center gap-2 shrink-0">
                        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
                        <label class="text-muted flex items-center gap-1.5 text-xs font-medium">
                            <span>Show</span>
                            <select name="per_page" onchange="this.form.submit()"
                                class="rounded-xl border border-line bg-surface py-1.5 px-2.5 text-xs font-medium text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                                <option value="10" @selected($perPage==10)>10</option>
                                <option value="15" @selected($perPage==15 || $perPage> 15)>15</option>
                            </select>
                            <span>entries</span>
                        </label>
                    </form>

                    {{-- Search Input with Clean UI Group --}}
                    <form method="GET" action="{{ route('brands.index') }}" class="relative flex-1 max-w-md">
                        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                        @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
                        @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

                        <div class="relative flex items-center">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
                                <x-icon name="search" class="size-4" />
                            </span>
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Search ..."
                                class="w-full rounded-xl border border-line bg-surface pl-9 pr-9 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                            @if(request('search'))
                            <a href="{{ route('brands.index', array_filter(['per_page' => request('per_page'), 'sort' => request('sort'), 'direction' => request('direction')])) }}"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-muted hover:text-ink cursor-pointer"
                                title="Clear search">
                                <x-svg.x class="size-3.5" />
                            </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Right: Export Tools & Column Visibility --}}
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    {{-- Export CSV --}}
                    <a :href="exportUrl('csv')"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                        title="Export filtered records to CSV">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export CSV</span>
                    </a>

                    {{-- Export Excel --}}
                    <a :href="exportUrl('xlsx')"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                        title="Export filtered records to Excel">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Export Excel</span>
                    </a>

                    {{-- PDF Preview --}}
                    <button type="button" @click="openPdf()"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                        title="Generate clean printable PDF preview">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span>Export PDF</span>
                    </button>

                    {{-- Print Preview --}}
                    <button type="button" @click="printTable()"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer"
                        title="Print brand table list">
                        <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Print</span>
                    </button>

                    {{-- Column Visibility Dropdown --}}
                    <div class="relative inline-block text-left" @click.outside="columnsDropdownOpen = false">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                            <svg class="size-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span>Column visibility</span>
                            <span class="ml-0.5 rounded-full bg-soft px-1.5 py-0.2 text-[10px] font-bold text-brand-500"
                                x-text="activeColumnsCount() + '/2'"></span>
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-48 rounded-2xl border border-line bg-surface p-1.5 shadow-card z-50 text-left">
                            <div class="px-2 py-1.5 text-[11px] font-semibold text-muted uppercase tracking-wider border-b border-line mb-1">
                                Toggle Columns
                            </div>
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                    class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium rounded-xl text-ink transition-colors hover:bg-soft text-left cursor-pointer">
                                    <div class="flex items-center gap-2">
                                        <span class="flex size-4 items-center justify-center rounded border transition-colors"
                                            :class="columns[col.key] ? 'bg-brand-500 border-brand-500 text-on-brand' : 'border-line bg-surface'">
                                            <svg x-show="columns[col.key]" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                        <span x-text="col.label"></span>
                                    </div>
                                    <span x-show="columns[col.key]" class="text-[10px] text-brand-500 font-semibold">Visible</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="brands-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        {{-- Brand Column Header --}}
                        <th x-show="columns.name" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Brands</span>
                                <span class="flex flex-col text-[10px] leading-none opacity-60 group-hover:opacity-100 transition-opacity">
                                    @if($sort === 'name')
                                    <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                    <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>

                        {{-- Note Column Header --}}
                        <th x-show="columns.note" scope="col" class="py-3.5 px-5 text-start">
                            <a href="{{ $sortLink('note') }}" class="inline-flex items-center gap-1.5 font-bold text-ink hover:text-brand-500 transition-colors group">
                                <span>Note</span>
                                <span class="flex flex-col text-[10px] leading-none opacity-60 group-hover:opacity-100 transition-opacity">
                                    @if($sort === 'note')
                                    <span class="text-brand-500 font-black">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                    <span>⇅</span>
                                    @endif
                                </span>
                            </a>
                        </th>

                        {{-- Action Column Header (Strictly preserved for authorization test assertions) --}}
                        @can('brands.manage')
                        <th scope="col" class="py-3.5 px-5 text-end text-xs font-bold text-ink">Action</th>
                        @endcan
                    </tr>
                </thead>

                <tbody class="divide-y divide-line bg-surface">
                    @forelse($brands as $brand)
                    @php
                    $initials = strtoupper(mb_substr($brand->name, 0, 2));
                    @endphp
                    <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                        {{-- Brand Name Column --}}
                        <td x-show="columns.name" data-col="name" class="py-4 px-5 text-start">
                            <div class="flex items-center gap-3">
                                {{-- Brand Official Logo Badge --}}
                                <x-brand-logo :name="$brand->name" size="default" class="group-hover:scale-110 transition-transform duration-200" />
                                <div>
                                    <span class="block text-sm font-semibold text-ink group-hover:text-brand-500 transition-colors">
                                        {{ $brand->name }}
                                    </span>
                                    <span class="block text-[11px] text-muted">
                                        ID #{{ $brand->id }} · {{ $brand->created_at?->format('d M Y') ?? 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        {{-- Note Column --}}
                        <td x-show="columns.note" data-col="note" class="py-4 px-5 text-start">
                            @if($brand->note)
                            <p class="text-sm text-ink/90 font-normal leading-relaxed max-w-xl">
                                {{ $brand->note }}
                            </p>
                            @else
                            <span class="text-xs text-muted/60 italic">—</span>
                            @endif
                        </td>

                        {{-- Actions Column --}}
                        @can('brands.manage')
                        <td class="py-4 px-5 text-end whitespace-nowrap">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                @can('update', $brand)
                                <button type="button"
                                    @click="openEdit(@js(['id' => $brand->id, 'name' => $brand->name, 'note' => $brand->note]))"
                                    class="btn-scale inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors cursor-pointer"
                                    title="Edit {{ $brand->name }}">
                                    <x-svg.pencil class="size-3.5 text-muted group-hover:text-brand-500" />
                                    <span>Edit</span>
                                </button>
                                @endcan

                                @can('delete', $brand)
                                <button type="button"
                                    @click="confirmDelete(@js(['id' => $brand->id, 'name' => $brand->name, 'action' => route('brands.destroy', $brand)]))"
                                    class="btn-scale inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-danger shadow-2xs hover:border-danger/40 hover:bg-danger/10 transition-colors cursor-pointer"
                                    title="Delete {{ $brand->name }}">
                                    <x-svg.trash-bin class="size-3.5 text-danger" />
                                    <span>Delete</span>
                                </button>
                                @endcan
                            </div>
                        </td>
                        @endcan
                    </tr>
                    @empty
                    <tr>
                        <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="py-12 px-6 text-center">
                            <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                    <x-icon name="box" class="size-7" />
                                </div>
                                <h3 class="text-base font-semibold text-ink">No brands found</h3>
                                <p class="text-xs text-muted mt-1 leading-relaxed">
                                    @if(request('search'))
                                    No brand manufacturer matches your search term <strong class="text-ink">"{{ request('search') }}"</strong>.
                                    @else
                                    Get started by adding your first brand manufacturer to the catalog.
                                    @endif
                                </p>
                                <div class="mt-4 flex items-center gap-2">
                                    @if(request('search'))
                                    <a href="{{ route('brands.index') }}"
                                        class="rounded-xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors shadow-2xs">
                                        Clear Search
                                    </a>
                                    @endif
                                    @can('create', App\Models\Brand::class)
                                    <button type="button" @click="openAdd()"
                                        class="rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                        + Add Brand
                                    </button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination & Footer --}}
        <div class="border-t border-line px-5 py-4 bg-surface">
            {{ $brands->links() }}
        </div>
    </div>

    {{-- Add / Edit Brand Modal Dialog --}}
    <div x-show="modalOpen" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
        @keydown.escape.window="closeModal()">

        {{-- Modal Card Container --}}
        <div class="w-full max-w-lg rounded-2xl border border-line bg-surface shadow-card p-6 sm:p-7 relative my-8"
            @click.stop
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2">

            <form :action="formAction()" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="editMode ? 'PATCH' : 'POST'">
                <input type="hidden" name="_form_mode" :value="editMode ? 'edit' : 'add'">
                <input type="hidden" name="brand_id" :value="brandId ?? ''">

                {{-- Modal Header --}}
                <div class="flex items-start justify-between pb-4 border-b border-line">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                            <template x-if="!editMode">
                                <x-icon name="plus" class="size-5" />
                            </template>
                            <template x-if="editMode">
                                <x-svg.pencil class="size-5" />
                            </template>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-ink"
                                x-text="editMode ? 'Edit brand' : 'Add brand'"></h3>
                            <p class="text-xs text-muted mt-0.5"
                                x-text="editMode ? 'Update brand details and catalog information.' : 'Register a new brand manufacturer in the catalog.'"></p>
                        </div>
                    </div>

                    <button type="button" @click="closeModal()"
                        class="rounded-lg p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                        aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                {{-- Modal Body Inputs --}}
                <div class="space-y-4 py-5">
                    {{-- Brand Name Field --}}
                    <div>
                        <label for="brand-name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Brand name<span class="text-danger">*</span>
                        </label>
                        <input type="text" id="brand-name" name="name" x-model="form.name" required
                            placeholder="e.g. Samsung, Apple, Xiaomi, Vivo"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                        @error('name')
                        <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Brand Short Description / Note Field --}}
                    <div>
                        <label for="brand-note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                            Short description
                        </label>
                        <input type="text" id="brand-note" name="note" x-model="form.note"
                            placeholder="e.g. Flagship & mid-range smartphones and accessories"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('note') border-danger @enderror">
                        @error('note')
                        <p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-line">
                    <button type="button" @click="closeModal()"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Close
                    </button>
                    <button type="submit"
                        class="btn-scale rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-5 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <x-icon name="check" class="size-4" />
                        <span>Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Confirmation Modal Dialog --}}
    <div x-show="deleteModalOpen" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
        @keydown.escape.window="deleteModalOpen = false">

        <div class="w-full max-w-md rounded-2xl border border-line bg-surface shadow-card p-6 sm:p-7 relative my-8"
            @click.stop
            x-show="deleteModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2">

            <div class="flex flex-col items-center text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20 mb-4">
                    <x-svg.trash-bin class="size-7" />
                </div>

                <h3 class="text-lg font-bold text-ink">Delete Brand</h3>
                <p class="text-xs sm:text-sm text-muted mt-1.5 leading-relaxed">
                    Are you sure you want to delete <span class="font-semibold text-ink" x-text="deleteTarget.name"></span>? This record will be archived.
                </p>

                <form :action="deleteTarget.action" method="POST" class="mt-6 flex items-center gap-3 w-full">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleteModalOpen = false"
                        class="flex-1 rounded-xl border border-line bg-surface py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 rounded-xl bg-danger hover:bg-danger/90 text-white py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition-colors cursor-pointer">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    function brandsPage(state) {
        return {
            modalOpen: state.hasErrors,
            editMode: state.mode === 'edit',
            brandId: state.brandId,
            form: {
                name: state.name,
                note: state.note
            },

            deleteModalOpen: false,
            deleteTarget: {
                id: null,
                name: '',
                action: ''
            },

            columnsDropdownOpen: false,
            columns: {
                name: true,
                note: true
            },
            columnList: [{
                    key: 'name',
                    label: 'Brands'
                },
                {
                    key: 'note',
                    label: 'Note'
                }
            ],

            createAction: @js(route('brands.store')),

            formAction() {
                return this.editMode ? @js(url('brands')) + '/' + this.brandId : this.createAction;
            },

            openAdd() {
                this.editMode = false;
                this.brandId = null;
                this.form = {
                    name: '',
                    note: ''
                };
                this.modalOpen = true;
            },

            openEdit(brand) {
                this.editMode = true;
                this.brandId = brand.id;
                this.form = {
                    name: brand.name,
                    note: brand.note ?? ''
                };
                this.modalOpen = true;
            },

            closeModal() {
                this.modalOpen = false;
            },

            confirmDelete(target) {
                this.deleteTarget = target;
                this.deleteModalOpen = true;
            },

            toggleColumn(key) {
                this.columns[key] = !this.columns[key];
            },

            activeColumnsCount() {
                return Object.values(this.columns).filter(Boolean).length;
            },

            activeColumnsQuery() {
                return Object.keys(this.columns)
                    .filter(k => this.columns[k])
                    .join(',');
            },

            exportUrl(format) {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: '{{ request('
                    search ') }}'
                });
                return `/brands/export/${format}?${params.toString()}`;
            },

            openPdf() {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: '{{ request('
                    search ') }}'
                });
                window.open(`/brands/pdf-view?${params.toString()}`, '_blank');
            },

            printTable() {
                const params = new URLSearchParams({
                    columns: this.activeColumnsQuery(),
                    search: '{{ request('
                    search ') }}'
                });
                window.open(`/brands/print-view?${params.toString()}`, '_blank');
            }
        };
    }
</script>
@endsection