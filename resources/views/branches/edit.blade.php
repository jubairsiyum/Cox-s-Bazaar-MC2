@extends('layouts.app')

@section('title', 'Edit Location — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Warehouses')

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
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('warehouses.index') }}" class="hover:text-brand-500 transition-colors">Warehouses</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Edit Location</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Edit Location</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    {{ $location->name }}
                </span>
                <x-status-badge :status="$location->status" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Update warehouse or outlet metadata without disrupting existing transfer records.</p>
        </div>

        <a href="{{ route('warehouses.index') }}"
           class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors shrink-0">
            <x-svg.arrow-left class="size-4" />
            <span>Back to Warehouses</span>
        </a>
    </div>

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

    {{-- Main Form Card --}}
    <div class="rounded-3xl border border-line bg-surface p-6 sm:p-8 shadow-card">
        <div class="flex items-center gap-3 pb-5 mb-6 border-b border-line">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                <x-svg.pencil class="size-5" />
            </div>
            <div>
                <h2 class="text-base font-bold text-ink">Location Profile</h2>
                <p class="text-xs text-muted mt-0.5">Location ID #{{ $location->id }} @if($location->code) · Code {{ $location->code }} @endif</p>
            </div>
        </div>

        <form method="POST" action="{{ route('warehouses.update', $location) }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Location Name --}}
                <div>
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Location Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $location->name) }}" required
                           placeholder="e.g. Main Warehouse, Brand Shop - Cox's Bazar"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('name') border-danger @enderror">
                    @error('name')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location Code --}}
                <div>
                    <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Location Code
                    </label>
                    <input type="text" id="code" name="code" value="{{ old('code', $location->code) }}"
                           placeholder="e.g. WH01 (optional, unique)"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('code') border-danger @enderror">
                    @error('code')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Type <span class="text-danger">*</span>
                    </label>
                    <select id="type" name="type" required
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('type') border-danger @enderror">
                        <option value="">Please Select</option>
                        @foreach(\App\Enums\LocationType::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $location->type->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Status
                    </label>
                    <select id="status" name="status"
                            class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all cursor-pointer @error('status') border-danger @enderror">
                        <option value="active" @selected(old('status', $location->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $location->status) === 'inactive')>Inactive</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Address --}}
                <div>
                    <label for="address" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Address
                    </label>
                    <input type="text" id="address" name="address" value="{{ old('address', $location->address) }}"
                           placeholder="Street address (optional)"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('address') border-danger @enderror">
                    @error('address')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Phone
                    </label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $location->phone) }}"
                           placeholder="Contact number (optional)"
                           class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all @error('phone') border-danger @enderror">
                    @error('phone')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Note --}}
                <div class="md:col-span-2">
                    <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">
                        Note
                    </label>
                    <textarea id="note" name="note" rows="3"
                              placeholder="Additional details about this location (optional)"
                              class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-none @error('note') border-danger @enderror">{{ old('note', $location->note) }}</textarea>
                    @error('note')
                        <p class="mt-1 text-xs font-medium text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Footer Actions --}}
            <div class="pt-5 border-t border-line flex items-center justify-end gap-3">
                <a href="{{ route('warehouses.index') }}"
                   class="btn-scale rounded-2xl border border-line bg-surface px-5 py-2.5 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-6 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
