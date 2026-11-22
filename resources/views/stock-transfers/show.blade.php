@extends('layouts.app')

@section('title', 'Stock Transfer ' . $transfer->reference_no . ' — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Stock Transfers')

@section('content')
@php
    $transferStatus = $transfer->displayStatusFor(auth()->user());
    $itemsSubtotal = $transfer->items->sum('subtotal');
    $totalUnits = $transfer->items->sum('quantity');
    $canDecide = in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit, \App\Enums\StockTransferStatus::Requested], true);
    $canApprove = auth()->user()?->can('approve', $transfer) && $canDecide;
    $canReject = auth()->user()?->can('reject', $transfer) && $canDecide;
    $canCancel = auth()->user()?->can('cancel', $transfer) && in_array($transfer->status, [\App\Enums\StockTransferStatus::Draft, \App\Enums\StockTransferStatus::InTransit], true);
@endphp

<style>
    @media print {
        header, aside, nav, .no-print { display: none !important; }
        main { padding: 0 !important; }
    }
</style>

<div x-data="{ confirmModal: { open: false, kind: 'approve' } }" class="space-y-6">

    {{-- Executive Header & Action Bar --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between no-print">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Inventory</span>
                <span class="text-muted/60">/</span>
                <a href="{{ route('stock-transfers.index') }}" class="hover:text-brand-500 transition-colors">Stock Transfers</a>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">{{ $transfer->reference_no }}</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Transfer Invoice</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-500 ring-1 ring-brand-500/20 tabular-nums">
                    {{ $transfer->reference_no }}
                </span>
                <x-status-badge :status="$transferStatus['value']" :label="$transferStatus['label']" />
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">
                Dispatched {{ $transfer->transfer_date->format('d M Y, h:i A') }} · {{ number_format($totalUnits) }} {{ Str::plural('unit', $totalUnits) }} across {{ $transfer->items->count() }} {{ Str::plural('line', $transfer->items->count()) }}
            </p>
        </div>

        {{-- Action Bar --}}
        <div class="flex items-center gap-2 flex-wrap shrink-0">
            @if($canApprove)
                <button type="button"
                        @click="confirmModal = { open: true, kind: 'approve' }"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-2xl bg-success px-4 py-2.5 text-xs sm:text-sm font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                    <x-icon name="check" class="size-4" />
                    <span>Accept / Receive</span>
                </button>
            @endif

            @if($canReject)
                <button type="button"
                        @click="confirmModal = { open: true, kind: 'reject' }"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-danger/30 bg-danger/10 px-4 py-2.5 text-xs sm:text-sm font-semibold text-danger hover:bg-danger hover:text-on-brand transition-colors cursor-pointer">
                    <x-svg.x class="size-4" />
                    <span>Reject</span>
                </button>
            @endif

            @if($canCancel)
                <button type="button"
                        @click="confirmModal = { open: true, kind: 'cancel' }"
                        class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer">
                    <x-svg.x class="size-4" />
                    <span>Cancel Transfer</span>
                </button>
            @endif

            @if($transfer->created_by === auth()->id() && auth()->user()?->can('withdraw', $transfer))
                <form method="POST" action="{{ route('stock-transfers.withdraw', $transfer) }}"
                      onsubmit="return confirm('Withdraw this stock request? The source will not need to decide on it.')">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer">
                        <x-svg.x class="size-4" />
                        <span>Withdraw Request</span>
                    </button>
                </form>
            @endif

            @can('update', $transfer)
                <a href="{{ route('stock-transfers.edit', $transfer) }}"
                   class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:border-brand-500 hover:text-brand-500 hover:bg-soft transition-colors">
                    <x-svg.pencil class="size-4 text-muted" />
                    <span>Edit</span>
                </a>
            @endcan

            <button type="button" onclick="window.print()"
                    class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-ink shadow-2xs hover:bg-soft hover:text-brand-500 transition-colors cursor-pointer">
                <svg class="size-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print Invoice</span>
            </button>

            <a href="{{ route('stock-transfers.index') }}"
               class="btn-scale inline-flex items-center gap-1.5 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted hover:text-ink hover:bg-soft transition-colors shadow-2xs">
                <x-svg.arrow-left class="size-4" />
                <span>Back</span>
            </a>
        </div>
    </div>

    @if($transfer->is_request)
        <div class="rounded-2xl border border-brand-500/25 bg-brand-500/5 p-4 text-sm text-ink no-print">
            <p>
                <span class="font-bold">Stock request.</span>
                {{ $transfer->toLocation?->name ?? 'This branch' }} asked {{ $transfer->fromLocation?->name ?? 'the source location' }} for these products.
                @if($transfer->status === \App\Enums\StockTransferStatus::Requested)
                    Stock moves only once {{ $transfer->fromLocation?->name ?? 'the source' }} approves it.
                @endif
            </p>
            @if($transfer->status === \App\Enums\StockTransferStatus::Rejected && filled($transfer->rejection_reason))
                <p class="mt-2 text-xs font-semibold text-danger">Rejection reason: {{ $transfer->rejection_reason }}</p>
            @endif
        </div>
    @endif

    {{-- Route Visualization + Approval Audit Grid --}}
    <div class="grid gap-5 lg:grid-cols-12">
        {{-- Origin -> Destination Route Card --}}
        <div class="lg:col-span-7 rounded-3xl border border-line bg-surface p-6 shadow-card flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2 pb-4 border-b border-line">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20">
                        <x-icon name="truck" class="size-4" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-ink">Transfer Route</h2>
                        <p class="text-[11px] text-muted">Ref {{ $transfer->reference_no }} · {{ $transfer->transfer_date->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
                <x-status-badge :status="$transferStatus['value']" :label="$transferStatus['label']" />
            </div>

            <div class="mt-5 grid grid-cols-1 sm:grid-cols-11 gap-3 items-center">
                {{-- Origin --}}
                <div class="sm:col-span-5 rounded-2xl border border-line bg-soft/60 p-4">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-muted">Location (From)</span>
                    <div class="mt-1.5 flex items-center gap-2">
                        <x-icon name="warehouse" class="size-4 shrink-0 text-brand-500" />
                        <span class="text-sm font-bold text-ink">{{ $transfer->fromLocation?->name ?? '—' }}</span>
                    </div>
                    @if($transfer->fromLocation?->code)
                        <span class="mt-1 inline-block text-[11px] text-muted">Code: {{ $transfer->fromLocation->code }}</span>
                    @endif
                </div>

                {{-- Flow Arrow --}}
                <div class="sm:col-span-1 flex items-center justify-center">
                    <div class="flex size-9 items-center justify-center rounded-full bg-brand-500/10 text-brand-500 ring-1 ring-brand-500/20 rotate-90 sm:rotate-0">
                        <x-svg.arrow-right class="size-4" />
                    </div>
                </div>

                {{-- Destination --}}
                <div class="sm:col-span-5 rounded-2xl border border-brand-500/25 bg-brand-500/5 p-4">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-brand-500">Location (To)</span>
                    <div class="mt-1.5 flex items-center gap-2">
                        <x-icon name="store" class="size-4 shrink-0 text-brand-500" />
                        <span class="text-sm font-bold text-ink">{{ $transfer->toLocation?->name ?? '—' }}</span>
                    </div>
                    @if($transfer->toLocation?->code)
                        <span class="mt-1 inline-block text-[11px] text-muted">Code: {{ $transfer->toLocation->code }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Approval Audit & Dispatch Metadata --}}
        <div class="lg:col-span-5 rounded-3xl border border-line bg-surface p-6 shadow-card">
            <div class="flex items-center gap-2.5 pb-4 border-b border-line">
                <div class="flex size-9 items-center justify-center rounded-xl bg-soft text-brand-500 ring-1 ring-line">
                    <x-icon name="roles" class="size-4" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-ink">Approval & Audit</h2>
                    <p class="text-[11px] text-muted">Sign-off tracking for sender & receiver</p>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 text-xs">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Approval Status</dt>
                    <dd class="mt-1 font-bold text-ink">
                        @if($transfer->approved_at)
                            <span class="inline-flex items-center gap-1 text-success">
                                <x-icon name="check" class="size-3.5" />
                                Approved
                            </span>
                        @elseif($transfer->status === \App\Enums\StockTransferStatus::Rejected)
                            <span class="inline-flex items-center gap-1 text-danger">
                                <x-svg.x class="size-3.5" />
                                Rejected
                            </span>
                        @elseif($transfer->status === \App\Enums\StockTransferStatus::Cancelled)
                            <span class="inline-flex items-center gap-1 text-danger">
                                <x-svg.x class="size-3.5" />
                                Cancelled
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-warning">
                                Awaiting Approval
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Sent By</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $transfer->creator?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Approved / Received By</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $transfer->approver?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Approved At</dt>
                    <dd class="mt-1 font-semibold text-ink tabular-nums">{{ $transfer->approved_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Line Items & Cost Breakdown --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4 bg-surface">
            <div>
                <h2 class="text-base font-bold text-ink">Transferred Items</h2>
                <p class="text-xs text-muted mt-0.5">Stock Transfer {{ $transfer->reference_no }} · {{ $transfer->items->count() }} {{ Str::plural('product', $transfer->items->count()) }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-ink border border-line tabular-nums">
                {{ number_format($totalUnits) }} total {{ Str::plural('unit', $totalUnits) }}
            </span>
        </div>

        <div class="table-wrap max-w-full overflow-x-auto border-0 rounded-none">
            <table class="data-table w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-bold text-muted uppercase tracking-wider">
                        <th class="py-3.5 px-5 w-12 text-center">#</th>
                        <th class="py-3.5 px-5">Product</th>
                        <th class="py-3.5 px-5 text-end">Quantity</th>
                        <th class="py-3.5 px-5 text-end">Unit Price</th>
                        <th class="py-3.5 px-5 text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($transfer->items as $index => $item)
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-4 px-5 text-xs font-semibold text-muted text-center tabular-nums">{{ $index + 1 }}</td>
                            <td class="py-4 px-5">
                                <div class="text-sm font-semibold text-ink">{{ $item->unit?->displayName() ?? 'Unknown product' }}</div>
                                @if($item->unit?->unit)
                                    <span class="mt-0.5 inline-flex items-center rounded-md bg-soft px-1.5 py-0.5 text-[11px] font-medium text-muted border border-line">
                                        {{ $item->unit->unit }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums">{{ number_format($item->quantity) }}</td>
                            <td class="py-4 px-5 text-end text-sm text-muted tabular-nums money whitespace-nowrap">{{ \App\Helpers\Money::format($item->unit_price) }}</td>
                            <td class="py-4 px-5 text-end text-sm font-bold text-ink tabular-nums money whitespace-nowrap">{{ \App\Helpers\Money::format($item->subtotal) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 px-6 text-center text-sm font-medium text-muted">
                                No products on this stock transfer.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cost Breakdown & Notes Footer --}}
        <div class="border-t border-line bg-canvas/30 px-6 py-5 grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
            <div class="md:col-span-7">
                @if($transfer->additional_notes)
                    <div class="rounded-2xl border border-line bg-surface p-4">
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Additional Notes</span>
                        <p class="text-sm text-ink/90 leading-relaxed">{{ $transfer->additional_notes }}</p>
                    </div>
                @else
                    <p class="text-xs text-muted italic">No additional transfer notes recorded.</p>
                @endif
            </div>

            <div class="md:col-span-5 rounded-2xl border border-line bg-surface p-4 space-y-2.5 text-sm">
                <div class="flex items-center justify-between text-muted">
                    <span>Items Subtotal</span>
                    <span class="font-semibold text-ink tabular-nums money">{{ \App\Helpers\Money::format($itemsSubtotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-muted">
                    <span>Shipping Charges</span>
                    <span class="font-semibold text-ink tabular-nums money">{{ \App\Helpers\Money::format($transfer->shipping_charges) }}</span>
                </div>
                <div class="pt-2.5 border-t border-line flex items-center justify-between">
                    <span class="font-bold text-ink">Total Amount</span>
                    <span class="text-lg font-extrabold text-brand-500 tabular-nums money">{{ \App\Helpers\Money::format($transfer->total_amount) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Accept / Reject / Cancel Confirmation Modal --}}
    <div x-show="confirmModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-overlay backdrop-blur-xs no-print"
         @keydown.escape.window="confirmModal.open = false">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6" @click.stop>
            <div class="flex items-start gap-3.5">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl ring-1"
                     :class="confirmModal.kind === 'approve' ? 'bg-success/10 text-success ring-success/20' : (confirmModal.kind === 'cancel' ? 'bg-soft text-muted ring-line' : 'bg-danger/10 text-danger ring-danger/20')">
                    <template x-if="confirmModal.kind === 'approve'">
                        <x-icon name="check" class="size-5" />
                    </template>
                    <template x-if="confirmModal.kind !== 'approve'">
                        <x-icon name="alert-triangle" class="size-5" />
                    </template>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-ink"
                        x-text="confirmModal.kind === 'approve' ? 'Accept and receive transfer?' : (confirmModal.kind === 'cancel' ? 'Cancel this transfer?' : 'Reject this transfer?')"></h3>
                    <p class="mt-1.5 text-xs text-muted leading-relaxed">
                        <template x-if="confirmModal.kind === 'approve'">
                            @if($transfer->is_request)
                                <span>Approving <strong class="text-ink">{{ $transfer->reference_no }}</strong> sends {{ number_format($totalUnits) }} {{ Str::plural('unit', $totalUnits) }} from <strong class="text-ink">{{ $transfer->fromLocation?->name }}</strong> to <strong class="text-ink">{{ $transfer->toLocation?->name }}</strong>. The source's stock must cover the request.</span>
                            @else
                                <span>Receiving <strong class="text-ink">{{ $transfer->reference_no }}</strong> posts {{ number_format($totalUnits) }} {{ Str::plural('unit', $totalUnits) }} to <strong class="text-ink">{{ $transfer->toLocation?->name }}</strong> and marks it Delivered for the sender.</span>
                            @endif
                        </template>
                        <template x-if="confirmModal.kind === 'reject'">
                            @if($transfer->is_request)
                                <span>Rejecting <strong class="text-ink">{{ $transfer->reference_no }}</strong> tells <strong class="text-ink">{{ $transfer->toLocation?->name }}</strong> that <strong class="text-ink">{{ $transfer->fromLocation?->name }}</strong> will not send this stock. No stock moves.</span>
                            @else
                                <span>Rejecting <strong class="text-ink">{{ $transfer->reference_no }}</strong> marks it Rejected and returns any dispatched stock to <strong class="text-ink">{{ $transfer->fromLocation?->name }}</strong>.</span>
                            @endif
                        </template>
                        <template x-if="confirmModal.kind === 'cancel'">
                            <span>Cancelling <strong class="text-ink">{{ $transfer->reference_no }}</strong> notifies the receiving side and returns any dispatched stock to <strong class="text-ink">{{ $transfer->fromLocation?->name }}</strong>.</span>
                        </template>
                    </p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                <button type="button" @click="confirmModal.open = false"
                        class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                    Close
                </button>
                @if($canApprove)
                    <form x-show="confirmModal.kind === 'approve'" method="POST" action="{{ route('stock-transfers.approve', $transfer) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-success px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                            <x-icon name="check" class="size-3.5" />
                            <span>Accept / Receive</span>
                        </button>
                    </form>
                @endif
                @if($canReject)
                    <form x-show="confirmModal.kind === 'reject'" method="POST" action="{{ route('stock-transfers.reject', $transfer) }}">
                        @csrf
                        @method('PATCH')
                        @if($transfer->is_request)
                            <label for="rejection-reason" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink">Reason <span class="text-danger">*</span></label>
                            <textarea id="rejection-reason" name="reason" rows="2" required maxlength="500"
                                      placeholder="Why can this stock not be sent?"
                                      class="mb-3 w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 resize-none"></textarea>
                        @endif
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl bg-danger px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:opacity-95 transition-opacity cursor-pointer">
                            <x-svg.x class="size-3.5" />
                            <span>Confirm Rejection</span>
                        </button>
                    </form>
                @endif
                @if($canCancel)
                    <form x-show="confirmModal.kind === 'cancel'" method="POST" action="{{ route('stock-transfers.cancel', $transfer) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="btn-scale inline-flex items-center gap-1.5 rounded-xl border border-line bg-soft px-4 py-2 text-xs font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                            <x-svg.x class="size-3.5" />
                            <span>Confirm Cancellation</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

</div>

@if(request('print'))
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
@endif
@endsection
