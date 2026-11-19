@extends('layouts.app')

@section('title', 'Add Category — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Category')

@section('content')
<div x-data="{ name: @js((string) old('name', '')), description: @js((string) old('description', '')) }" class="space-y-6">

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
                <a href="{{ route('categories.index') }}" class="hover:text-brand-500 transition-colors">Categories List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Category</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Category</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="plus" class="size-3.5" />
                    <span>Add/Update Category</span>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Create a new product category to group devices and accessories across inventory and sales.</p>
        </div>

        <a href="{{ route('categories.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0 self-start sm:self-auto">
            <x-svg.arrow-left class="size-4 text-muted" />
            <span>Back to Categories</span>
        </a>
    </div>

    {{-- Validation Alert Banner --}}
    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-danger/30 bg-danger/10 px-5 py-4 text-danger">
            <x-icon name="alert-triangle" class="size-5 shrink-0 mt-0.5" />
            <div class="text-xs sm:text-sm">
                <p class="font-bold">Please Enter Valid Data</p>
                <p class="mt-0.5 opacity-90">Review the highlighted fields below and correct any validation errors before saving.</p>
            </div>
        </div>
    @endif

    {{-- Responsive 2-Column Form Layout --}}
    <div class="grid gap-6 lg:grid-cols-3 items-start">

        {{-- Main Form Card --}}
        <div class="lg:col-span-2 rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                    <x-icon name="box" class="size-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-ink">Category Details</h2>
                    <p class="text-xs text-muted mt-0.5">Define a clear, recognizable name and optional summary for your catalog.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('categories.store') }}" class="p-6 space-y-5">
                @csrf

                {{-- Category Name --}}
                <div>
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-2">
                        Category Name<span class="text-danger ml-0.5">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           x-model="name"
                           value="{{ old('name') }}"
                           required
                           autofocus
                           placeholder="e.g. Smartphones, Phone Cases & Covers, Power Banks"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger focus:border-danger focus:ring-danger/20 @enderror">
                    @error('name')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-ink">
                            Description
                        </label>
                        <span class="text-[11px] text-muted">Optional</span>
                    </div>
                    <textarea id="description"
                              name="description"
                              x-model="description"
                              rows="4"
                              placeholder="Briefly describe the types of items grouped under this category..."
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-y @error('description') border-danger focus:border-danger focus:ring-danger/20 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Footer Action Bar --}}
                <div class="pt-5 border-t border-line flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ route('categories.index') }}"
                       class="btn-scale inline-flex items-center justify-center rounded-xl border border-line bg-surface px-5 py-2.5 text-sm font-semibold text-ink hover:bg-soft transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="btn-scale inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Save Category</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Right Sidebar: Live Preview & Tips --}}
        <div class="space-y-5">
            {{-- Live Card Preview --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Catalog Preview</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-0.5 text-[11px] font-semibold text-success ring-1 ring-success/20">
                        <span class="size-1.5 rounded-full bg-success"></span>
                        Active
                    </span>
                </div>
                <div class="rounded-2xl border border-line bg-canvas/40 p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 text-xs font-bold uppercase"
                             x-text="(name.trim() || 'CT').slice(0, 2).toUpperCase()">
                            CT
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-ink truncate" x-text="name.trim() || 'New Category Name'">
                                New Category Name
                            </p>
                            <p class="text-[11px] text-muted mt-0.5">Auto-assigned code on save</p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted leading-relaxed"
                       x-text="description.trim() || 'Add a short description so staff can classify products consistently.'"></p>
                </div>
            </div>

            {{-- Helpful Guidelines --}}
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-soft text-brand-500 ring-1 ring-line">
                        <x-icon name="sparkles" class="size-4" />
                    </div>
                    <h3 class="text-sm font-bold text-ink">Best Practices</h3>
                </div>
                <ul class="space-y-2.5 text-xs text-muted leading-relaxed">
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Keep names concise and unique (e.g., <strong class="text-ink">Smartphones</strong>, <strong class="text-ink">Chargers & Adapters</strong>).</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>New categories default to <strong class="text-success">Active</strong> and are immediately available in the product catalog.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="size-1.5 rounded-full bg-brand-500 mt-1.5 shrink-0"></span>
                        <span>Re-entering the exact name of an archived category automatically restores it.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
