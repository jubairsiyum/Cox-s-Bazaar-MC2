@extends('layouts.app')

@section('title', 'Purchase Dues — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Purchase Dues')

@section('content')
<div class="space-y-6" x-data="purchaseDuesPage()">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Purchasing</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Purchase Dues</span>
            </nav>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Purchase Dues</h1>
            <p class="text-xs sm:text-sm text-muted mt-1">Partially paid purchase invoices and their outstanding dues, by invoice reference. Due payments require manager approval.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('purchase-dues.index') }}" class="rounded-3xl border border-line bg-surface p-5 shadow-card">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice reference, supplier..."
                       class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Payment Request</label>
                <select name="request_status" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    <option value="">All</option>
                    @foreach($requestStatusFilters as $value => $label)
                        <option value="{{ $value }}" @selected(request('request_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($canFilterBranch && auth()->user()?->hasRole('Super Admin'))
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Branch</label>
                    <select name="location_id" class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                        <option value="">All</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(request('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs font-semibold text-on-brand transition-colors cursor-pointer">
                    <x-icon name="search" class="size-4" />
                    Filter
                </button>
                <a href="{{ route('purchase-dues.index') }}" class="btn-scale inline-flex items-center rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs font-semibold text-ink hover:bg-soft transition-colors">
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Purchase Dues Table --}}
    <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
        <div class="max-w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-line bg-canvas/40 text-xs font-semibold uppercase tracking-wider text-muted">
                        <th class="py-3.5 px-5">Invoice #</th>
                        <th class="py-3.5 px-5">Supplier / Branch</th>
                        <th class="py-3.5 px-5 text-right">Grand Total</th>
                        <th class="py-3.5 px-5 text-right">Paid</th>
                        <th class="py-3.5 px-5 text-right">Remaining Due</th>
                        <th class="py-3.5 px-5">Payment Request</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-surface">
                    @forelse($orders as $order)
                        @php
                            $latestRequest = ($requestsByOrder[$order->id] ?? collect())->last();
                            $remainingDue = (float) $order->payment_due;
                        @endphp
                        <tr class="hover:bg-soft/40 transition-colors">
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <a href="{{ route('purchase-invoices.show', $order) }}" class="text-sm font-bold text-brand-500 hover:underline tabular-nums">
                                    {{ $order->reference_no }}
                                </a>
                                <span class="block text-[11px] text-muted">{{ $order->order_date?->format('d/m/Y') ?? '—' }}</span>
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="text-sm font-semibold text-ink">{{ $order->supplier?->name ?? 'Unknown supplier' }}</div>
                                <div class="text-xs text-muted">
                                    {{ $order->location?->name ?? '—' }}
                                    @if($order->location?->branch)
                                        · {{ $order->location->branch->name }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($order->grand_total) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm text-ink tabular-nums">{{ \App\Helpers\Money::format($order->paid_amount) }}</td>
                            <td class="py-3.5 px-5 text-right text-sm font-bold {{ $remainingDue > 0 ? 'text-danger' : 'text-success' }} tabular-nums">
                                {{ \App\Helpers\Money::format($remainingDue) }}
                            </td>
                            <td class="py-3.5 px-5">
                                @if($latestRequest === null)
                                    <x-status-badge status="none" label="No Request" />
                                @elseif($latestRequest->status === \App\Enums\ApprovalStatus::Pending)
                                    <x-status-badge status="awaiting-approval" label="Awaiting Approval" />
                                    <span class="block text-[11px] text-muted mt-1 tabular-nums">
                                        {{ \App\Helpers\Money::format($latestRequest->amount) }}
                                        @if($latestRequest->method) · {{ $latestRequest->methodLabel() }}@endif
                                    </span>
                                @elseif($latestRequest->status === \App\Enums\ApprovalStatus::Approved)
                                    @if((int) $latestRequest->approved_by === (int) $latestRequest->created_by && $latestRequest->approved_by !== null)
                                        <x-status-badge status="completed" label="Completed" />
                                    @else
                                        <x-status-badge status="approved" label="Approved" />
                                    @endif
                                    <span class="block text-[11px] text-muted mt-1 tabular-nums">
                                        {{ \App\Helpers\Money::format($latestRequest->amount) }}
                                        @if($latestRequest->method) · {{ $latestRequest->methodLabel() }}@endif
                                    </span>
                                @else
                                    <x-status-badge status="rejected" label="Rejected" />
                                @endif
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <button type="button"
                                            @click="openCreateModal(@js($order->id), @js($order->reference_no), @js($order->supplier?->name ?? 'Unknown supplier'), @js($remainingDue))"
                                            class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                                        Manage Due
                                    </button>

                                    @if($latestRequest !== null)
                                        @can('update', $latestRequest)
                                            <button type="button"
                                                    @click="openEditModal(@js(route('purchase-dues.update', $latestRequest)), @js($order->reference_no), @js($order->supplier?->name ?? 'Unknown supplier'), @js($remainingDue + (float) $latestRequest->amount), @js((float) $latestRequest->amount), @js($latestRequest->method ?? 'other'), @js($latestRequest->note ?? ''))"
                                                    class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('purchase-dues.destroy', $latestRequest) }}" class="inline"
                                                  onsubmit="return confirm('Cancel this pending due payment request?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-line bg-soft px-2.5 py-1 text-[11px] font-semibold text-danger hover:border-danger transition-colors cursor-pointer">
                                                    Cancel
                                                </button>
                                            </form>
                                        @endcan

                                        @if($latestRequest->status === \App\Enums\ApprovalStatus::Pending)
                                            @can('approve', $latestRequest)
                                                <form method="POST" action="{{ route('purchase-dues.approve', $latestRequest) }}" class="inline"
                                                      onsubmit="return confirm('Approve this due payment? It will be applied to the purchase invoice and the supplier due.')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-success/40 bg-success/10 px-2.5 py-1 text-[11px] font-semibold text-success hover:bg-success/20 transition-colors cursor-pointer">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('purchase-dues.reject', $latestRequest) }}" class="inline"
                                                      onsubmit="return confirm('Reject this due payment request? Nothing will be applied.')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-danger/40 bg-danger/10 px-2.5 py-1 text-[11px] font-semibold text-danger hover:bg-danger/20 transition-colors cursor-pointer">
                                                        Reject
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12">
                                <x-empty-state message="No outstanding purchase dues found." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-line px-5 py-4">
            <x-pagination :paginator="$orders" />
        </div>
    </div>

    {{-- Manage Due Modal (submit / edit a due payment request) --}}
    <div x-show="requestModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-overlay backdrop-blur-xs overflow-y-auto"
         @keydown.escape.window="closeRequestModal()">
        <div class="w-full max-w-md rounded-3xl border border-line bg-surface shadow-card p-6 sm:p-7 relative my-8"
             @click.stop
             x-show="requestModal.open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            <form :action="requestModal.url" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="requestModal.mode === 'edit' ? 'PATCH' : 'POST'">
                <input type="hidden" name="purchase_order_id" :value="requestModal.orderId">

                <div class="flex items-start justify-between pb-4 border-b border-line">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-ink"
                            x-text="requestModal.mode === 'edit' ? 'Edit Due Payment Request' : 'Request Due Payment'"></h3>
                        <p class="text-xs text-muted mt-0.5 tabular-nums" x-text="requestModal.ref"></p>
                    </div>
                    <button type="button" @click="closeRequestModal()"
                            class="rounded-xl p-1.5 text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer"
                            aria-label="Close modal">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                <div class="py-5 space-y-4">
                    <div class="rounded-2xl border border-line bg-soft/60 p-4 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Supplier</span>
                            <span class="font-semibold text-ink" x-text="requestModal.supplier"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted">Remaining Due</span>
                            <span class="font-bold text-danger tabular-nums" x-text="requestModal.due.toFixed(2)"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required
                               x-model="requestModal.amount"
                               placeholder="0.00"
                               class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink tabular-nums placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Method</label>
                        <select name="method" x-model="requestModal.method"
                                class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20">
                            <option x-show="requestModal.method && ! @js(array_keys($methodOptions)).includes(requestModal.method)"
                                    :value="requestModal.method"
                                    x-text="(requestModal.method || '') + ' (stored)'"></option>
                            @foreach($methodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-ink mb-1.5">Note</label>
                        <textarea name="note" rows="2" x-model="requestModal.note"
                                  class="w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-muted shadow-2xs focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20"
                                  placeholder="Optional note..."></textarea>
                    </div>

                    <p class="text-xs text-muted leading-relaxed">
                        Payments recorded by the responsible manager are applied immediately. Staff requests are saved as
                        <span class="font-semibold text-ink">Awaiting Approval</span> and have no financial effect until a
                        responsible manager — never the requester — approves them.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-line">
                    <button type="button" @click="closeRequestModal()"
                            class="btn-scale rounded-xl border border-line bg-surface px-4 py-2 text-xs sm:text-sm font-semibold text-ink hover:bg-soft transition-colors cursor-pointer">
                        Close
                    </button>
                    <button type="submit"
                            class="btn-scale rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2 text-xs sm:text-sm font-semibold text-on-brand shadow-sm transition-colors cursor-pointer inline-flex items-center gap-1.5">
                        <span x-text="requestModal.mode === 'edit' ? 'Save Changes' : 'Submit Request'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function purchaseDuesPage() {
    return {
        requestModal: {
            open: false,
            url: @js(route('purchase-dues.store')),
            mode: 'create',
            ref: '',
            supplier: '',
            due: 0,
            orderId: '',
            amount: '',
            method: @js(array_key_first($methodOptions) ?? 'cash'),
            note: ''
        },
        openCreateModal(orderId, ref, supplier, due) {
            Object.assign(this.requestModal, {
                open: true,
                url: @js(route('purchase-dues.store')),
                mode: 'create',
                ref: ref,
                supplier: supplier,
                due: Number(due),
                orderId: orderId,
                amount: '',
                method: @js(array_key_first($methodOptions) ?? 'cash'),
                note: ''
            });
        },
        openEditModal(url, ref, supplier, due, amount, method, note) {
            Object.assign(this.requestModal, {
                open: true,
                url: url,
                mode: 'edit',
                ref: ref,
                supplier: supplier,
                due: Number(due),
                orderId: '',
                amount: Number(amount).toFixed(2),
                method: method,
                note: note
            });
        },
        closeRequestModal() {
            this.requestModal.open = false;
        }
    };
}
</script>
@endsection
