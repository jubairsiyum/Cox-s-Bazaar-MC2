@extends('layouts.app')

@section('title', 'Method — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Method')

@section('content')
@php
    $currentSearch = (string) request('search', '');
@endphp

<div class="space-y-6">

    {{-- Top Breadcrumb & Executive Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Finance</span>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Transactions</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Method</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Method</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($methods->total()) }} {{ Str::plural('method', $methods->total()) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Reference list behind the Finance "Method" dropdowns (transactions, account transfers, supplier payments). Inactive methods drop out of dropdowns while old entries keep their stored value.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            @if(request()->hasAny(['search', 'type', 'status']))
                <a href="{{ route('payment-methods.index') }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs"
                   title="Reset filters">
                    <x-svg.x class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif

            @can('create', App\Models\PaymentMethod::class)
                <a href="{{ route('payment-methods.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>Add Method</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Method Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Method Directory</h2>
                <p class="text-xs text-muted mt-0.5">
                    @if($methods->total() > 0)
                        Showing <span class="font-semibold text-ink tabular-nums">{{ $methods->firstItem() }}–{{ $methods->lastItem() }}</span> of <span class="font-semibold text-ink tabular-nums">{{ number_format($methods->total()) }}</span> methods
                    @else
                        No methods match the current filters
                    @endif
                </p>
            </div>
        </div>

        {{-- Filter Toolbar --}}
        <div class="px-5 sm:px-6 py-4 border-b border-line bg-canvas/40">
            <form method="GET" action="{{ route('payment-methods.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <div class="relative sm:col-span-2">
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Search</label>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted">
                            <x-icon name="search" class="size-4" />
                        </span>
                        <input type="text" name="search" value="{{ $currentSearch }}"
                               placeholder="Method name or note..."
                               class="w-full rounded-xl border border-line bg-surface pl-10 pr-3 py-2 text-xs font-normal text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Type</label>
                    <select name="type" onchange="this.form.submit()"
                            class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                        <option value="">All types</option>
                        @foreach(\App\Enums\AccountMethod::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label class="block text-[11px] font-semibold text-muted uppercase tracking-wider mb-1.5">Status</label>
                        <select name="status" onchange="this.form.submit()"
                                class="w-full rounded-xl border border-line bg-surface py-2 px-2.5 text-xs font-semibold text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 cursor-pointer">
                            <option value="">All statuses</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <button type="submit"
                            class="btn-scale rounded-xl bg-brand-500 px-3.5 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- Data Table --}}
        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Method Name</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Type</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink whitespace-nowrap">Note</th>
                        <th scope="col" class="py-3.5 px-5 text-center font-bold text-ink whitespace-nowrap">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($methods as $method)
                        <tr class="hover:bg-soft/40 transition-colors duration-150 group">
                            <td class="py-4 px-5 text-start">
                                <span class="text-sm font-semibold text-ink">{{ $method->name }}</span>
                                <span class="block text-xs text-muted">Added {{ $method->created_at?->format('d M Y') ?? 'N/A' }}{{ $method->creator ? ' · ' . $method->creator->name : '' }}</span>
                            </td>
                            <td class="py-4 px-5 text-start">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-soft text-ink ring-1 ring-line">
                                    {{ $method->type->label() }}
                                </span>
                            </td>
                            <td class="py-4 px-5 text-start text-sm text-muted">
                                @if($method->note)
                                    <span class="block max-w-[220px] truncate" title="{{ $method->note }}">{{ $method->note }}</span>
                                @else
                                    <span class="text-xs text-muted/60 italic">—</span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-center">
                                <x-status-badge :status="$method->status" />
                            </td>
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('toggleStatus', $method)
                                        <form method="POST" action="{{ route('payment-methods.toggle-status', $method) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-2.5 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-warning hover:text-warning transition-colors cursor-pointer"
                                                    title="{{ $method->status === 'active' ? 'Deactivate (keeps history, removes from dropdowns)' : 'Reactivate' }}">
                                                <span>{{ $method->status === 'active' ? 'Deactivate' : 'Activate' }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                    @can('update', $method)
                                        <a href="{{ route('payment-methods.edit', $method) }}"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors"
                                           title="Edit {{ $method->name }}">
                                            <x-svg.pencil class="size-3.5 text-muted" />
                                            <span>Edit</span>
                                        </a>
                                    @endcan
                                    @can('delete', $method)
                                        <x-confirm-delete
                                            :action="route('payment-methods.destroy', $method)"
                                            :message="'Delete method ' . $method->name . '? Deletion is blocked while entries still use it — deactivate instead.'"
                                            confirm-text="Delete Method"
                                            label="Delete" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 px-6 text-center">
                                <x-empty-state message="No payment methods found.">
                                    @can('create', App\Models\PaymentMethod::class)
                                        <a href="{{ route('payment-methods.create') }}"
                                           class="mt-3 inline-flex rounded-xl bg-brand-500 hover:bg-brand-600 text-on-brand px-4 py-2 text-xs font-semibold shadow-sm transition-colors">
                                            + Add Method
                                        </a>
                                    @endcan
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="border-t border-line px-5 sm:px-6 py-4 bg-surface">
            {{ $methods->links() }}
        </div>
    </div>
</div>
@endsection
