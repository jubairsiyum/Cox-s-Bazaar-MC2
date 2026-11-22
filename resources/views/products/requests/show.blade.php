@extends('layouts.app')

@section('title', 'Product Request — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Product Requests')

@section('content')
@php
    $canApprove = auth()->user()?->can('approve', $productRequest) && $productRequest->isPending();
    $canReject = auth()->user()?->can('reject', $productRequest) && $productRequest->isPending();
    $canReceive = $productRequest->isApproved() && $productRequest->isInTransit() && auth()->user()?->can('receive', $productRequest);
    $createdProduct = $productRequest->createdProduct;
@endphp

<div class="space-y-6">

    {{-- Executive Header & Action Bar --}}
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
                <a href="{{ route('products.requests.index') }}" class="hover:text-brand-500 transition-colors">Product Requests</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $productRequest->displayName() }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">{{ $productRequest->displayName() }}</h1>
                <x-status-badge :status="$productRequest->status" />
                @if($productRequest->delivery_status)
                    <x-status-badge :status="$productRequest->delivery_status" />
                @endif
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Requested {{ $productRequest->created_at->format('d M Y, h:i A') }} by {{ $productRequest->requester?->name ?? 'a branch user' }} from {{ $productRequest->location?->name ?? 'an unassigned branch' }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap shrink-0">
            @if($canApprove)
                <form method="POST" action="{{ route('products.requests.approve', $productRequest) }}"
                      onsubmit="return confirm('Approve this request and add the product to the catalog?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-success px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Approve &amp; Add Product</span>
                    </button>
                </form>
            @endif

            @if($canReject)
                <a href="#reject-decision"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-2.5 text-xs sm:text-sm font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors">
                    <x-svg.x class="size-4" />
                    <span>Reject</span>
                </a>
            @endif

            @if($canReceive)
                <form method="POST" action="{{ route('products.requests.receive', $productRequest) }}"
                      onsubmit="return confirm('Confirm receipt of this product at your branch?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                        <x-icon name="check" class="size-4" />
                        <span>Receive Product</span>
                    </button>
                </form>
            @endif

            <a href="{{ route('products.requests.index') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors">
                <x-svg.arrow-left class="size-4 text-muted" />
                <span>Back to Requests</span>
            </a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3 items-start">

        {{-- Requested product details (mirrors the Add Product form) --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
                <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="smartphone" class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Requested Product Details</h2>
                        <p class="text-xs text-muted mt-0.5">Exactly what was submitted. Approval adds the product with these details.</p>
                    </div>
                </div>

                <dl class="p-6 grid gap-5 sm:grid-cols-2 text-sm">
                    <div class="sm:col-span-2 flex items-start gap-4">
                        @if($productRequest->photo)
                            <x-thumbnail :src="\Illuminate\Support\Facades\Storage::disk('public')->url($productRequest->photo)" :alt="$productRequest->name . ' photo'" :label="$productRequest->name" size="size-14 rounded-xl" />
                        @elseif($productRequest->brand)
                            <x-brand-logo :name="$productRequest->brand->name" size="default" />
                        @endif
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Product Name</dt>
                            <dd class="mt-1 font-semibold text-ink">{{ $productRequest->name }}</dd>
                        </div>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Brand</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $productRequest->brand?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Category</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $productRequest->category?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Model</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $productRequest->model ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Barcode / IMEI</dt>
                        <dd class="mt-1 font-mono font-semibold text-ink">{{ $productRequest->barcode ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Storage</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $productRequest->storage ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Color</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $productRequest->color ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Purchase Price</dt>
                        <dd class="mt-1 font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($productRequest->purchase_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Sale Price</dt>
                        <dd class="mt-1 font-semibold text-ink tabular-nums">{{ \App\Helpers\Money::format($productRequest->sale_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Opening Quantity</dt>
                        <dd class="mt-1 font-semibold text-ink tabular-nums">{{ number_format($productRequest->quantity) }} {{ $productRequest->unit }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Status on Creation</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ ucfirst($productRequest->product_status) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-muted">Note</dt>
                        <dd class="mt-1 text-ink whitespace-pre-line">{{ $productRequest->note ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Super Admin decision: approve, or reject with a reason --}}
            @if($canReject)
                <div id="reject-decision" class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden scroll-mt-6">
                    <div class="flex items-center gap-3.5 border-b border-line px-6 py-5 bg-canvas/30">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger/10 text-danger ring-1 ring-danger/20">
                            <x-svg.x class="size-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-ink">Reject This Request</h2>
                            <p class="text-xs text-muted mt-0.5">Rejecting adds nothing to the catalog. The reason is shown to the requesting branch.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('products.requests.reject', $productRequest) }}" class="p-6 space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="rejection-reason" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink">
                                Reason<span class="text-danger ml-0.5">*</span>
                            </label>
                            <textarea id="rejection-reason" name="reason" rows="3" required maxlength="500"
                                      placeholder="e.g. Duplicate of an existing model, wrong category, unverified barcode"
                                      class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 transition-all resize-y @error('reason') border-danger @enderror">{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="mt-1.5 text-xs font-medium text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger px-5 py-2.5 text-sm font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                                <x-svg.x class="size-4" />
                                <span>Reject Request</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        {{-- Request status & audit trail --}}
        <div class="space-y-5 xl:sticky xl:top-6">
            <div class="rounded-3xl border border-line bg-surface p-6 shadow-card space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">Request Status</span>
                    <x-status-badge :status="$productRequest->status" />
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Branch</dt>
                        <dd class="font-semibold text-ink text-right">{{ $productRequest->location?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Requested by</dt>
                        <dd class="font-semibold text-ink text-right">{{ $productRequest->requester?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Submitted</dt>
                        <dd class="font-semibold text-ink text-right tabular-nums">{{ $productRequest->created_at->format('d M Y, h:i A') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Decided by</dt>
                        <dd class="font-semibold text-ink text-right">{{ $productRequest->reviewer?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Decided at</dt>
                        <dd class="font-semibold text-ink text-right tabular-nums">{{ $productRequest->reviewed_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    </div>
                    @if($productRequest->isApproved())
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-muted">Delivery</dt>
                            <dd class="font-semibold text-ink text-right">
                                <x-status-badge :status="$productRequest->delivery_status" />
                            </dd>
                        </div>
                        @if($productRequest->isDelivered())
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-muted">Received by</dt>
                                <dd class="font-semibold text-ink text-right">{{ $productRequest->receivedBy?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-muted">Received at</dt>
                                <dd class="font-semibold text-ink text-right tabular-nums">{{ $productRequest->delivered_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        @endif
                    @endif
                </dl>

                @if($productRequest->status->value === 'rejected' && filled($productRequest->rejection_reason))
                    <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 text-xs text-danger">
                        <p class="font-bold uppercase tracking-wider">Rejection reason</p>
                        <p class="mt-1 text-ink">{{ $productRequest->rejection_reason }}</p>
                    </div>
                @endif

                @if($productRequest->status->value === 'approved' && $createdProduct)
                    <div class="rounded-2xl border border-success/30 bg-success/10 p-4 text-xs">
                        <p class="font-bold uppercase tracking-wider text-success">Added to catalog</p>
                        <p class="mt-1 text-ink">
                            <span class="font-semibold">{{ $createdProduct->displayName() }}</span>
                            <span class="font-mono text-muted">· {{ $createdProduct->product_id }}</span>
                        </p>
                        @can('update', $createdProduct)
                            <a href="{{ route('products.edit', $createdProduct) }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-brand-500 hover:underline">
                                <span>Open product</span>
                                <x-icon name="arrow-up-right" class="size-3.5" />
                            </a>
                        @endcan
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
