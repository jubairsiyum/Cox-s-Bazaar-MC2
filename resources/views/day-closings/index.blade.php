@extends('layouts.app')

@section('title', 'Daily Cash Close — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Daily Cash Close')

@section('content')
<div class="space-y-6" x-data="{
    detailModalOpen: false,
    selectedClosing: null,
    viewDetails(data) {
        this.selectedClosing = data;
        this.detailModalOpen = true;
    },
    closeModal() {
        this.detailModalOpen = false;
        this.selectedClosing = null;
    }
}">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-muted mb-2" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-brand-500 transition-colors">
                    <x-icon name="dashboard" class="size-3.5" />
                    <span>Home</span>
                </a>
                <span class="text-muted/60">/</span>
                <span class="text-muted">Finance</span>
                <span class="text-muted/60">/</span>
                <span class="font-medium text-ink">Daily Cash Close</span>
            </nav>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">Daily Cash Close &amp; Period Lock</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-500 ring-1 ring-brand-500/20">
                    Audit &amp; Anti-Tamper Control
                </span>
            </div>
            <p class="text-xs sm:text-sm text-muted mt-1">Once a branch closes its day, backdated transactions for that date are locked. Only Super Admin can reopen with a logged reason.</p>
        </div>

        @if($isTodayClosed ?? false)
            <div class="inline-flex items-center gap-2 rounded-2xl border border-line bg-surface px-4 py-2.5 text-xs sm:text-sm font-semibold text-muted shadow-2xs shrink-0 cursor-not-allowed select-none opacity-80"
                 title="Today's business day is already closed and locked.">
                <x-icon name="lock" class="size-4 text-emerald-500" />
                <span>Today's Cash Locked</span>
            </div>
        @else
            <a href="{{ route('day-closings.create') }}"
               class="btn-scale inline-flex items-center gap-2 rounded-2xl bg-brand-500 px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition-colors shrink-0">
                <x-icon name="lock" class="size-4" />
                <span>Close Today's Cash</span>
            </a>
        @endif
    </div>

    @if(isset($compliance) && ($isSuperAdmin || !empty($compliance['branches'])))
        {{-- Today's Branch Closing Compliance Tracker --}}
        <div class="rounded-3xl border border-line bg-surface p-5 sm:p-6 shadow-card space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-line pb-4">
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-base sm:text-lg font-bold text-ink">Today's Branch Closing Status</h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $compliance['unclosed_count'] === 0 ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20' : 'bg-warning/10 text-warning ring-1 ring-warning/20' }}">
                            {{ $compliance['closed_count'] }} of {{ $compliance['total_branches'] }} Branches Closed ({{ $compliance['compliance_pct'] }}%)
                        </span>
                    </div>
                    <p class="text-xs text-muted mt-0.5">Every branch must complete its daily cash close at shift end. Unclosed branches require immediate management follow-up.</p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs font-medium text-muted">Closing Date: <strong class="text-ink">{{ now()->format('d M Y') }}</strong></span>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($compliance['branches'] as $b)
                    <div class="rounded-2xl border p-4 transition-all {{ $b['is_closed'] ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-warning/30 bg-warning/5' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs font-bold text-ink block truncate">{{ $b['name'] }}</span>
                                <span class="text-[11px] text-muted uppercase tracking-wider">{{ $b['code'] ?? 'Branch' }}</span>
                            </div>
                            @if($b['is_closed'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-2xs font-bold bg-emerald-500/10 text-emerald-600">
                                    <x-svg.check class="size-3" />
                                    Closed
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-2xs font-bold bg-warning/15 text-amber-700 dark:text-amber-400 animate-pulse">
                                    ⚠️ Pending Close
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 pt-3 border-t border-line/60 space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted">Today's Sales:</span>
                                <span class="font-semibold text-ink">৳{{ number_format($b['today_sales']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Counted Cash:</span>
                                <span class="font-semibold text-ink">৳{{ number_format($b['counted_cash']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted">Remitted to HQ:</span>
                                <span class="font-semibold text-emerald-600">৳{{ number_format($b['remitted_amount']) }}</span>
                            </div>
                        </div>

                        <div class="mt-3 pt-2 text-[11px] flex items-center justify-between">
                            @if($b['is_closed'])
                                <span class="text-muted truncate" title="Closed by {{ $b['closing']?->closedBy?->name ?? 'Staff' }}">
                                    by {{ $b['closing']?->closedBy?->name ?? 'Staff' }}
                                </span>
                                <span class="text-muted tabular-nums">
                                    {{ $b['closing']?->created_at?->format('h:i A') }}
                                </span>
                            @else
                                <span class="text-amber-700 dark:text-amber-400 font-medium">Shift open</span>
                                @can('create', App\Models\DayClosing::class)
                                    <a href="{{ route('day-closings.create', ['location_id' => $b['location_id']]) }}"
                                       class="font-semibold text-brand-500 hover:underline">
                                        Close Now →
                                    </a>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Closings Table Card --}}
    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm">
                <thead>
                    <tr class="border-b border-line text-xs uppercase tracking-wider text-muted">
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Branch</th>
                        <th class="py-3 px-4 text-right">Cash Sales</th>
                        <th class="py-3 px-4 text-right">Remitted to HQ</th>
                        <th class="py-3 px-4 text-right">Cash in Drawer</th>
                        <th class="py-3 px-4 text-right">Variance</th>
                        <th class="py-3 px-4">Closed By</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse($closings as $closing)
                        <tr class="hover:bg-soft/50 transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-ink whitespace-nowrap">
                                {{ $closing->closing_date->format('d M Y') }}
                            </td>
                            <td class="py-3.5 px-4 text-ink font-medium">
                                <span class="font-semibold text-ink block">{{ $closing->location?->name ?? 'Head Office' }}</span>
                                <span class="text-2xs text-muted uppercase tracking-wider">{{ $closing->location?->code ?? 'Branch' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums font-semibold text-ink">
                                ৳{{ number_format((float) $closing->cash_sales, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums font-semibold text-emerald-600">
                                ৳{{ number_format((float) $closing->cash_remittances, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums font-semibold text-ink">
                                ৳{{ number_format((float) $closing->counted_cash, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right tabular-nums">
                                @php
                                    $var = (float) $closing->variance;
                                @endphp
                                @if($var == 0.0)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600">
                                        Exact (৳0.00)
                                    </span>
                                @elseif(abs($var) <= 100.0)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600">
                                        Minor ({{ $var > 0 ? '+' : '' }}৳{{ number_format($var, 2) }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600">
                                        Significant ({{ $var > 0 ? '+' : '' }}৳{{ number_format($var, 2) }})
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-muted">
                                {{ $closing->closedBy?->name ?? 'Staff' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($closing->status === 'closed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-2xs font-semibold bg-brand-500/10 text-brand-500">
                                        🔒 Locked
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-2xs font-semibold bg-amber-500/10 text-amber-600">
                                        🔓 Reopened
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button"
                                            @click="viewDetails({{ json_encode([
                                                'id' => $closing->id,
                                                'date' => $closing->closing_date->format('d M Y'),
                                                'branch' => $closing->location?->name ?? 'Head Office',
                                                'branch_code' => $closing->location?->code ?? '',
                                                'opening_balance' => number_format((float) $closing->opening_balance, 2),
                                                'cash_sales' => number_format((float) $closing->cash_sales, 2),
                                                'cash_collections' => number_format((float) $closing->cash_collections, 2),
                                                'cash_expenses' => number_format((float) $closing->cash_expenses, 2),
                                                'cash_remittances' => number_format((float) $closing->cash_remittances, 2),
                                                'system_cash' => number_format((float) $closing->system_cash, 2),
                                                'counted_cash' => number_format((float) $closing->counted_cash, 2),
                                                'variance' => number_format((float) $closing->variance, 2),
                                                'variance_status' => $closing->variance_status,
                                                'status' => $closing->status,
                                                'closed_by' => $closing->closedBy?->name ?? 'Staff',
                                                'closed_at' => $closing->created_at?->format('h:i A, d M Y'),
                                                'reopened_by' => $closing->reopenedBy?->name,
                                                'reopened_at' => $closing->reopened_at?->format('h:i A, d M Y'),
                                                'reopen_reason' => $closing->reopen_reason,
                                                'note' => $closing->note,
                                            ]) }})"
                                            class="btn-scale inline-flex items-center gap-1 rounded-xl bg-soft border border-line px-2.5 py-1 text-xs font-semibold text-ink hover:text-brand-500 hover:border-brand-500/30 transition-colors cursor-pointer"
                                            title="View Cash Close Details">
                                        <x-icon name="enquiries" class="size-3.5" />
                                        <span>Details</span>
                                    </button>

                                    @if($isSuperAdmin && $closing->status === 'closed' && $closing->closing_date->isToday())
                                        <form method="POST" action="{{ route('day-closings.reopen', $closing) }}" class="inline"
                                              onsubmit="return confirm('Are you sure you want to reopen today\'s cash close? It will unlock transactions for today.');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="reopen_reason" value="Super Admin shift unlock">
                                            <button type="submit" class="text-xs font-semibold text-danger hover:underline cursor-pointer">
                                                Reopen
                                            </button>
                                        </form>
                                    @elseif($closing->status === 'closed')
                                        <span class="inline-flex items-center gap-1 text-2xs font-semibold text-muted bg-soft px-2 py-0.5 rounded-full select-none" title="Historical period lock is permanent.">
                                            <x-icon name="lock" class="size-3 text-muted" />
                                            <span>Finalized</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-muted">
                                No day closing records found. Close today's cash above to begin daily lock records.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($closings->hasPages())
            <div class="mt-4 pt-4 border-t border-line">
                {{ $closings->links() }}
            </div>
        @endif
    </div>

    {{-- ── Daily Cash Close Audit Details Modal ── --}}
    <div x-show="detailModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="closeModal()">
        {{-- Backdrop --}}
        <div x-show="detailModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-ink/40 backdrop-blur-xs"
             @click="closeModal()"></div>

        {{-- Modal Content --}}
        <div x-show="detailModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-2xl overflow-hidden rounded-3xl bg-surface border border-line p-6 sm:p-7 shadow-2xl z-10 max-h-[90vh] overflow-y-auto">

            <template x-if="selectedClosing">
                <div class="space-y-6">
                    {{-- Modal Header --}}
                    <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
                        <div>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h3 class="text-lg sm:text-xl font-bold text-ink" x-text="selectedClosing.branch"></h3>
                                <span class="rounded-full bg-soft px-2.5 py-0.5 text-2xs font-semibold text-muted uppercase tracking-wider" x-text="selectedClosing.branch_code"></span>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-2xs font-bold"
                                      :class="selectedClosing.status === 'closed' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600'">
                                    <span x-text="selectedClosing.status === 'closed' ? '🔒 Closed & Period Locked' : '🔓 Reopened'"></span>
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                Closing Date: <strong class="text-ink" x-text="selectedClosing.date"></strong> · Recorded: <span x-text="selectedClosing.closed_at"></span>
                            </p>
                        </div>
                        <button type="button" @click="closeModal()" class="p-1 rounded-xl text-muted hover:text-ink hover:bg-soft transition-colors cursor-pointer" aria-label="Close modal">
                            <x-svg.x class="size-5" />
                        </button>
                    </div>

                    {{-- 4 Core Flow Metrics --}}
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted mb-2.5">Daily Cash Activity Summary</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3.5 rounded-2xl border border-line bg-surface shadow-2xs">
                                <span class="text-2xs font-semibold text-muted uppercase tracking-wider block">Cash Sales</span>
                                <span class="text-base sm:text-lg font-bold tabular-nums text-ink mt-0.5 block" x-text="'৳' + selectedClosing.cash_sales"></span>
                            </div>
                            <div class="p-3.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 shadow-2xs">
                                <span class="text-2xs font-semibold text-emerald-600 uppercase tracking-wider block">Remitted to HQ</span>
                                <span class="text-base sm:text-lg font-bold tabular-nums text-emerald-600 mt-0.5 block" x-text="'৳' + selectedClosing.cash_remittances"></span>
                            </div>
                            <div class="p-3.5 rounded-2xl border border-line bg-surface shadow-2xs">
                                <span class="text-2xs font-semibold text-muted uppercase tracking-wider block">Opening Balance</span>
                                <span class="text-base sm:text-lg font-bold tabular-nums text-ink mt-0.5 block" x-text="'৳' + selectedClosing.opening_balance"></span>
                            </div>
                            <div class="p-3.5 rounded-2xl border border-line bg-surface shadow-2xs">
                                <span class="text-2xs font-semibold text-rose-500 uppercase tracking-wider block">Cash Expenses</span>
                                <span class="text-base sm:text-lg font-bold tabular-nums text-rose-500 mt-0.5 block" x-text="'৳' + selectedClosing.cash_expenses"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Drawer Closing Reconciliation --}}
                    <div class="rounded-2xl border border-line bg-soft/40 p-4 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted">Drawer Closing Reconciliation</h4>
                        <div class="space-y-2 text-xs sm:text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted">Expected System Balance (Inflows − Remittances − Expenses):</span>
                                <span class="font-bold tabular-nums text-ink" x-text="'৳' + selectedClosing.system_cash"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-line/60 pt-2">
                                <span class="font-medium text-ink">Physically Counted Cash in Till:</span>
                                <span class="font-extrabold tabular-nums text-ink text-sm sm:text-base" x-text="'৳' + selectedClosing.counted_cash"></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-line/60 pt-2">
                                <span class="font-medium text-ink">Closing Cash Variance:</span>
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold"
                                          :class="selectedClosing.variance_status === 'exact' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600'">
                                        <span x-text="(parseFloat(selectedClosing.variance) === 0 ? '✓ Exact Match (' : (parseFloat(selectedClosing.variance) > 0 ? '+৳' : '-৳')) + selectedClosing.variance + (parseFloat(selectedClosing.variance) === 0 ? ')' : ' discrepancy')"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Audit Trail & Notes --}}
                    <div class="rounded-2xl border border-line bg-surface p-4 space-y-2 text-xs">
                        <h4 class="text-2xs font-bold uppercase tracking-wider text-muted">Audit & Verification</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <span class="text-muted block">Closed By:</span>
                                <strong class="text-ink font-semibold" x-text="selectedClosing.closed_by"></strong>
                            </div>
                            <div>
                                <span class="text-muted block">Timestamp:</span>
                                <span class="text-ink" x-text="selectedClosing.closed_at"></span>
                            </div>
                        </div>
                        <template x-if="selectedClosing.note">
                            <div class="pt-2 border-t border-line/60">
                                <span class="text-muted block">Closing Notes:</span>
                                <p class="text-ink mt-0.5 italic" x-text="selectedClosing.note"></p>
                            </div>
                        </template>
                        <template x-if="selectedClosing.status === 'reopened'">
                            <div class="pt-2 border-t border-line/60 bg-amber-500/5 p-2.5 rounded-xl">
                                <span class="text-amber-700 dark:text-amber-400 font-bold block">Reopened By Super Admin:</span>
                                <p class="text-ink mt-0.5" x-text="selectedClosing.reopened_by + ' on ' + selectedClosing.reopened_at"></p>
                                <p class="text-muted text-2xs mt-0.5" x-text="'Reason: ' + selectedClosing.reopen_reason"></p>
                            </div>
                        </template>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-between pt-2 border-t border-line">
                        <a href="{{ route('account-transfers.index') }}"
                           class="text-xs font-semibold text-brand-500 hover:underline">
                            View Remittances →
                        </a>
                        <button type="button" @click="closeModal()"
                                class="btn-scale rounded-2xl bg-brand-500 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                            Done
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
