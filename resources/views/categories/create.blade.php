@extends('layouts.app')

@section('title', 'Add Category')
@section('page_title', 'Category')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-baseline gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Category</h1>
                <span class="text-sm text-gray-500 dark:text-gray-400">Add/Update Category</span>
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
            <a href="{{ route('categories.index') }}" class="hover:text-brand-500">Categories List</a>
            <span>&rsaquo;</span>
            <span class="text-gray-700 dark:text-gray-300 font-medium">Category</span>
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
            <form method="POST" action="{{ route('categories.store') }}">
                @csrf

                <div class="max-w-3xl space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-2">
                        <label for="name"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right">
                            Category Name<span class="text-red-500 font-bold">*</span>
                        </label>
                        <div class="sm:col-span-2">
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. Smartphones, Phone Cases & Covers">
                            @error('name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 items-start gap-2">
                        <label for="description"
                            class="text-sm font-semibold text-gray-700 dark:text-gray-300 sm:text-right pt-2">
                            Description
                        </label>
                        <div class="sm:col-span-2">
                            <textarea id="description" name="description" rows="3"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Category description (optional)">{{ old('description') }}</textarea>
                            @error('description')
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
                    <a href="{{ route('categories.index') }}"
                        class="px-8 py-2.5 rounded-md text-white font-medium text-sm transition-all shadow-sm flex items-center gap-2"
                        style="background-color: #f39c12;">
                        Close
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
