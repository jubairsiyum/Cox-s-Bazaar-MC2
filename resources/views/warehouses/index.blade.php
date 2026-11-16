@extends('layouts.app')

@section('title', 'Warehouses')
@section('page_title', 'Warehouses')

@section('content')
@php
    $sortLink = fn (string $col) => route('warehouses.index', array_filter([
        'search' => request('search'),
        'per_page' => request('per_page'),
        'sort' => $col,
        'direction' => $sort === $col && $direction === 'asc' ? 'desc' : 'asc',
    ]));
@endphp

<div class="relative">
    {{-- Header Bar --}}
    <div class="mb-4 flex flex-wrap items-baseline gap-2">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Warehouses</h1>
        <span class="text-sm text-gray-500 dark:text-gray-400">Manage business locations (warehouses, brand shops, SR placements) used by stock transfers</span>
    </div>

    {{-- Main Card --}}
    <div class="card shadow-sm">
        <div class="card-header flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">All your locations</h2>
            @can('create', App\Models\Location::class)
                <a href="{{ route('warehouses.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95 cursor-pointer"
                   style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </a>
            @endcan
        </div>

        <div class="card-body p-4">
            {{-- Toolbar: Show entries, Search --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                <form method="GET" action="{{ route('warehouses.index') }}" class="flex items-center gap-2">
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

                <form method="GET" action="{{ route('warehouses.index') }}" class="flex items-center gap-2">
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
                <table class="data-table w-full text-left" id="warehouses-table">
                    <thead>
                        <tr class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-3">
                                <a href="{{ $sortLink('name') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Name
                                    <span class="text-xs opacity-70">{{ $sort === 'name' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3">
                                <a href="{{ $sortLink('code') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Code
                                    <span class="text-xs opacity-70">{{ $sort === 'code' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3">
                                <a href="{{ $sortLink('type') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Type
                                    <span class="text-xs opacity-70">{{ $sort === 'type' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3">
                                <a href="{{ $sortLink('status') }}" class="flex items-center gap-1 text-sm font-bold text-gray-800 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                    Status
                                    <span class="text-xs opacity-70">{{ $sort === 'status' ? ($direction === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                                </a>
                            </th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Address</th>
                            <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Phone</th>
                            @can('warehouses.manage')
                                <th class="p-3 text-sm font-bold text-gray-800 dark:text-gray-100">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                        @forelse($locations as $location)
                            <tr class="odd:bg-gray-50 dark:odd:bg-gray-800/40 hover:bg-gray-100 dark:hover:bg-gray-800/70 transition">
                                <td class="p-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $location->name }}
                                    @if($location->note)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-normal">{{ $location->note }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $location->code ?: '—' }}</td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $location->type->label() }}</td>
                                <td class="p-3"><x-status-badge :status="$location->status" /></td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $location->address ?: '—' }}</td>
                                <td class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $location->phone ?: '—' }}</td>
                                @can('warehouses.manage')
                                    <td class="p-3 text-xs whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @can('update', $location)
                                                <a href="{{ route('warehouses.edit', $location) }}"
                                                   class="inline-flex items-center gap-1 rounded-full border border-indigo-500/60 px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 transition hover:bg-indigo-50 dark:hover:bg-indigo-500/10 cursor-pointer">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Edit
                                                </a>
                                            @endcan
                                            @can('delete', $location)
                                                <form method="POST" action="{{ route('warehouses.destroy', $location) }}"
                                                      onsubmit="return confirm('Are you sure you want to delete this location?');">
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
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm font-medium">
                                    No locations found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $locations->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
