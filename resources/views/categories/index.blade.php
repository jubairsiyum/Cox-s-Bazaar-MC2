@extends('layouts.app')

@section('title', 'Categories List')
@section('page_title', 'Categories List')

@section('content')
<div x-data="categoriesPage()" class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Categories List</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">View/Search Categories</span>
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
                <span class="text-gray-700 dark:text-gray-300 font-medium">Categories List</span>
            </div>
            @can('create', App\Models\Category::class)
                <a href="{{ route('categories.create') }}"
                   class="px-4 py-2 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-1.5 hover:opacity-95"
                   style="background-color: #00c0ef;">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Category
                </a>
            @endcan
        </div>
    </div>

    {{-- Main Card --}}
    <div class="card border-t-4 border-t-cyan-500 shadow-sm">
        <div class="card-header border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Categories List</h2>
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Exports, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                {{-- Left: Show entries --}}
                <form method="GET" action="{{ route('categories.index') }}" class="flex items-center gap-2">
                    @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
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

                {{-- Middle: Export Buttons --}}
                <div class="flex items-center gap-1 flex-wrap">
                    <button type="button" @click="copyTable()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: #26c6da;">
                        Copy
                    </button>
                    <a :href="exportUrl('xlsx')"
                       class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                       style="background-color: #26c6da;">
                        Excel
                    </a>
                    <button type="button" @click="openPdf()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: #26c6da;">
                        PDF
                    </button>
                    <button type="button" @click="printTable()"
                            class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                            style="background-color: #26c6da;">
                        Print
                    </button>
                    <a :href="exportUrl('csv')"
                       class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                       style="background-color: #26c6da;">
                        CSV
                    </a>

                    {{-- Columns Visibility Dropdown --}}
                    <div class="relative inline-block text-left">
                        <button type="button" @click.stop="columnsDropdownOpen = !columnsDropdownOpen"
                                class="px-3 py-1.5 rounded text-xs font-semibold text-white shadow-xs transition hover:opacity-90 cursor-pointer flex items-center gap-1"
                                style="background-color: #26c6da;">
                            Columns
                        </button>

                        <div x-show="columnsDropdownOpen" x-cloak
                             @click.outside="columnsDropdownOpen = false"
                             class="absolute left-0 sm:right-0 sm:left-auto mt-1 w-44 rounded shadow-2xl py-1 z-50 text-left border border-blue-400"
                             style="background-color: #2580ab;">
                            <template x-for="col in columnList" :key="col.key">
                                <button type="button" @click="toggleColumn(col.key)"
                                        class="w-full flex items-center justify-between px-3.5 py-1.5 text-xs font-medium text-white transition hover:bg-[#1e6b94] text-left cursor-pointer"
                                        :class="{ 'opacity-50 line-through': !columns[col.key] }">
                                    <span x-text="col.label"></span>
                                    <span x-show="columns[col.key]" class="text-[10px] font-bold">✓</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Right: Search Input --}}
                <form method="GET" action="{{ route('categories.index') }}" class="flex items-center gap-2">
                    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    <label class="text-gray-600 dark:text-gray-400 flex items-center gap-1.5 text-xs sm:text-sm">
                        Search:
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Type to search..."
                               class="py-1 px-2.5 text-xs rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-hidden focus:border-cyan-500">
                    </label>
                </form>
            </div>

            {{-- Table --}}
            <div class="table-wrap border border-gray-200 dark:border-gray-700 rounded-md" style="overflow-y: hidden !important;">
                <table class="data-table w-full text-left" id="categories-table">
                    <thead>
                        <tr style="background-color: #337ab7;">
                            <th class="w-10 text-center p-2.5" style="color: #ffffff !important;">
                                <input type="checkbox" @click="toggleSelectAll()" :checked="allSelected"
                                       class="rounded border-gray-300 text-cyan-600 focus:ring-0">
                            </th>
                            <th x-show="columns.name" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: #ffffff !important;">
                                CATEGORY NAME <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.description" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: #ffffff !important;">
                                DESCRIPTION <span class="opacity-80">⇅</span>
                            </th>
                            <th x-show="columns.status" class="p-2.5 font-bold text-xs tracking-wider cursor-pointer" style="color: #ffffff !important;">
                                STATUS <span class="opacity-80">⇅</span>
                            </th>
                            @can('categories.manage')
                                <th x-show="columns.action" class="p-2.5 font-bold text-xs tracking-wider text-right" style="color: #ffffff !important;">
                                    ACTION
                                </th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @forelse($categories as $category)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
                                <td class="text-center p-2.5">
                                    <input type="checkbox" value="{{ $category->id }}" x-model="selectedCategories"
                                           class="rounded border-gray-300 text-cyan-600 focus:ring-0">
                                </td>
                                <td x-show="columns.name" data-col="name" class="p-2.5 text-gray-900 dark:text-gray-100 font-semibold">
                                    {{ $category->name }}
                                </td>
                                <td x-show="columns.description" data-col="description" class="p-2.5 text-gray-600 dark:text-gray-300">
                                    {{ $category->description ?: '—' }}
                                </td>
                                <td x-show="columns.status" data-col="status" class="p-2.5">
                                    @can('update', $category)
                                        <form method="POST" action="{{ route('categories.toggle-status', $category) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="Click to change status to {{ $category->status === 'active' ? 'Inactive' : 'Active' }}"
                                                    class="cursor-pointer inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-white transition hover:opacity-80 active:scale-95 shadow-xs"
                                                    style="background-color: {{ $category->status === 'active' ? '#00a65a' : '#dd4b39' }};">
                                                {{ ucfirst($category->status) }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold text-white"
                                              style="background-color: {{ $category->status === 'active' ? '#00a65a' : '#dd4b39' }};">
                                            {{ ucfirst($category->status) }}
                                        </span>
                                    @endcan
                                </td>
                                @can('categories.manage')
                                    <td x-show="columns.action" data-col="action" class="p-2.5 text-right whitespace-nowrap">
                                        <button @click.stop="toggleActionMenu($event, {{ json_encode([
                                                    'id' => $category->id,
                                                    'name' => $category->name
                                                ]) }})"
                                                type="button"
                                                class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold text-white rounded shadow-xs hover:opacity-90"
                                                style="background-color: #1e3a5f;">
                                            Action
                                            <svg class="size-3 transition-transform"
                                                 :class="{ 'rotate-180': actionMenuOpen && openActionCategory && openActionCategory.id === {{ $category->id }} }"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td :colspan="1 + Object.keys(columns).filter(k => columns[k]).length" class="p-8 text-center text-gray-500 dark:text-gray-400 font-medium">
                                    No categories found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination & Entry Info --}}
            <div class="mt-4">
                {{ $categories->links() }}
            </div>
        </div>
    </div>

    {{-- Floating Action Dropdown Menu (Outside table to prevent any clipping or scrollbar) --}}
    @can('categories.manage')
    <div x-show="actionMenuOpen" x-cloak
         @click.outside="actionMenuOpen = false"
         @scroll.window="actionMenuOpen = false"
         @resize.window="actionMenuOpen = false"
         class="fixed z-50 w-44 rounded-md bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-black/15 dark:ring-white/10 py-1 border border-gray-200 dark:border-gray-700 text-left"
         :style="{ top: menuTop, left: menuLeft }">
        <template x-if="openActionCategory">
            <div>
                {{-- Option 1: Edit --}}
                <a :href="'/categories/' + openActionCategory.id + '/edit'"
                   class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg class="size-4 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>

                {{-- Option 2: Delete --}}
                <form :action="'/categories/' + openActionCategory.id" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this category?');">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 text-left transition">
                        <svg class="size-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                    </button>
                </form>
            </div>
        </template>
    </div>
    @endcan
</div>

<script>
function categoriesPage() {
    return {
        allSelected: false,
        selectedCategories: [],

        actionMenuOpen: false,
        openActionCategory: null,
        menuTop: '0px',
        menuLeft: '0px',

        columnsDropdownOpen: false,
        columns: {
            name: true,
            description: true,
            status: true,
            action: true
        },
        columnList: [
            { key: 'name', label: 'Category Name' },
            { key: 'description', label: 'Description' },
            { key: 'status', label: 'Status' },
            @can('categories.manage') { key: 'action', label: 'Action' } @endcan
        ],

        toggleColumn(key) {
            this.columns[key] = !this.columns[key];
        },

        activeColumnsQuery() {
            return Object.keys(this.columns)
                .filter(k => this.columns[k] && k !== 'action')
                .join(',');
        },

        exportUrl(format) {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            return `/categories/export/${format}?${params.toString()}`;
        },

        openPdf() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            window.open(`/categories/pdf-view?${params.toString()}`, '_blank');
        },

        printTable() {
            const params = new URLSearchParams({
                columns: this.activeColumnsQuery(),
                search: '{{ request('search') }}',
                status: '{{ request('status') }}'
            });
            window.open(`/categories/print-view?${params.toString()}`, '_blank');
        },

        copyTable() {
            const table = document.getElementById('categories-table');
            const printCols = this.columnList.filter(c => this.columns[c.key] && c.key !== 'action');
            let text = printCols.map(c => c.label).join('\t') + '\n';

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(tr => {
                if (tr.querySelector('td[colspan]')) return;
                const rowVals = printCols.map(c => {
                    const td = tr.querySelector(`td[data-col="${c.key}"]`);
                    return td ? td.innerText.trim() : '';
                });
                text += rowVals.join('\t') + '\n';
            });

            navigator.clipboard.writeText(text).then(() => {
                alert('Table data copied to clipboard!');
            });
        },

        toggleActionMenu(event, category) {
            if (this.actionMenuOpen && this.openActionCategory && this.openActionCategory.id === category.id) {
                this.actionMenuOpen = false;
                this.openActionCategory = null;
                return;
            }
            this.openActionCategory = category;
            const rect = event.currentTarget.getBoundingClientRect();
            const menuHeight = 90;
            const spaceBelow = window.innerHeight - rect.bottom;

            if (spaceBelow < menuHeight && rect.top > menuHeight) {
                this.menuTop = (rect.top - menuHeight) + 'px';
            } else {
                this.menuTop = (rect.bottom + 4) + 'px';
            }
            this.menuLeft = Math.max(10, rect.right - 176) + 'px';
            this.actionMenuOpen = true;
        },

        toggleSelectAll() {
            this.allSelected = !this.allSelected;
            const checkboxes = document.querySelectorAll('#categories-table tbody input[type="checkbox"]');
            this.selectedCategories = [];
            if (this.allSelected) {
                checkboxes.forEach(cb => {
                    cb.checked = true;
                    this.selectedCategories.push(cb.value);
                });
            } else {
                checkboxes.forEach(cb => cb.checked = false);
            }
        }
    };
}
</script>
@endsection
