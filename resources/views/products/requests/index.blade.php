@extends('layouts.app')

@section('title', 'Product Requests — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Product Requests')

@section('content')
@php
    $statusLink = fn (string $status) => route('products.requests.index', array_filter(['status' => $status]));
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
                <span class="text-muted">Master Data</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('products.index') }}" class="hover:text-brand-500 transition-colors">Products List</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Product Requests</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Product Requests</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ number_format($statusCounts['all']) }} {{ Str::plural('request', $statusCounts['all']) }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">New products asked for by branches. Only the Super Admin can approve or reject them.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('products.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4 text-muted" />
                <span>Back to Products</span>
            </a>
            @can('create', App\Models\ProductRequest::class)
                <a href="{{ route('products.requests.create') }}"
                   class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors">
                    <x-icon name="plus" class="size-4" />
                    <span>New Product Request</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Requests Directory Shell --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-line px-5 sm:px-6 py-4 sm:py-5 bg-surface">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-ink">Request Log</h2>
                <p class="text-xs text-muted mt-0.5">
                    Showing {{ number_format($requests->count()) }} of {{ number_format($requests->total()) }} {{ Str::plural('request', $requests->total()) }}
                </p>
            </div>

            {{-- Status Filter Pills --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach ([
                    '' => ['label' => 'All', 'count' => $statusCounts['all'], 'tone' => 'text-ink'],
                    'pending' => ['label' => 'Awaiting Approval', 'count' => $statusCounts['pending'], 'tone' => 'text-warning'],
                    'approved' => ['label' => 'Approved', 'count' => $statusCounts['approved'], 'tone' => 'text-success'],
                    'rejected' => ['label' => 'Rejected', 'count' => $statusCounts['rejected'], 'tone' => 'text-danger'],
                ] as $value => $pill)
                    <a href="{{ $statusLink($value) }}"
                       class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all {{ $currentStatus === $value ? 'bg-brand-500 text-on-brand shadow-xs' : 'bg-soft text-muted hover:text-ink' }}">
                        <span>{{ $pill['label'] }}</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] tabular-nums {{ $currentStatus === $value ? 'bg-on-brand/20 text-on-brand' : 'bg-surface ' . $pill['tone'] }}">
                            {{ number_format($pill['count']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="table-wrap max-w-full overflow-x-auto">
            <table class="data-table w-full text-left border-collapse" id="product-requests-table">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold text-muted tracking-wider uppercase">
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Product</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Branch</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Requested By</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Date</th>
                        <th scope="col" class="py-3.5 px-5 text-start font-bold text-ink">Status</th>
                        <th scope="col" class="py-3.5 px-5 text-end font-bold text-ink">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($requests as $productRequest)
                        <tr class="hover:bg-soft/40 transition-colors duration-150">
                            <td class="py-4 px-5 text-start">
                                <a href="{{ route('products.requests.show', $productRequest) }}" class="text-sm font-semibold text-ink hover:text-brand-500 transition-colors">
                                    {{ $productRequest->displayName() }}
                                </a>
                                <div class="text-[11px] text-muted mt-0.5">
                                    {{ $productRequest->brand?->name ?? 'Unbranded' }} · {{ $productRequest->category?->name ?? 'Uncategorized' }}
                                </div>
                            </td>
                            <td class="py-4 px-5 text-start whitespace-nowrap text-sm text-ink">
                                {{ $productRequest->location?->name ?? '—' }}
                            </td>
                            <td class="py-4 px-5 text-start whitespace-nowrap text-sm text-ink">
                                {{ $productRequest->requester?->name ?? '—' }}
                            </td>
                            <td class="py-4 px-5 text-start whitespace-nowrap text-sm text-muted tabular-nums">
                                {{ $productRequest->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="py-4 px-5 text-start whitespace-nowrap">
                                <x-status-badge :status="$productRequest->status" />
                                @if($productRequest->status->value === 'approved' && $productRequest->createdProduct)
                                    <div class="text-[11px] text-muted mt-1 font-mono">{{ $productRequest->createdProduct->product_id }}</div>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-end whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @can('approve', $productRequest)
                                        <form method="POST" action="{{ route('products.requests.approve', $productRequest) }}"
                                              onsubmit="return confirm('Approve this request and add the product to the catalog?');" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-success px-3 py-1.5 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                                                <x-icon name="check" class="size-3.5" />
                                                <span>Approve</span>
                                            </button>
                                        </form>
                                        <a href="{{ route('products.requests.show', $productRequest) }}#reject-decision"
                                           class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-danger/30 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors">
                                            <x-svg.x class="size-3.5" />
                                            <span>Reject</span>
                                        </a>
                                    @endcan
                                    <a href="{{ route('products.requests.show', $productRequest) }}"
                                       class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                                        <span>View</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-6 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center text-center">
                                    <div class="flex size-14 items-center justify-center rounded-2xl bg-soft text-brand-500 ring-1 ring-line mb-3.5">
                                        <x-icon name="smartphone" class="size-7" />
                                    </div>
                                    <h3 class="text-base font-semibold text-ink">No product requests</h3>
                                    <p class="text-xs text-muted mt-1 leading-relaxed">
                                        Products your branch asks for will be listed here with their approval status.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="px-5 sm:px-6 py-4 border-t border-line">
                <x-pagination :paginator="$requests" />
            </div>
        @endif
    </div>
</div>
@endsection
