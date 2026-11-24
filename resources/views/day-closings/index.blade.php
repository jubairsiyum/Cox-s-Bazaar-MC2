@extends('layouts.app')

@section('title', 'Daily Cash Close — ' . config('app.name', "Cox's Bazaar Mobile Center"))
@section('page_title', 'Daily Cash Close')

@section('content')
<div class="space-y-6">
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
                        <th class="py-3 px-4 text-right">System Cash</th>
                        <th class="py-3 px-4 text-right">Counted Cash</th>
                        <th class="py-3 px-4 text-right">Variance</th>
                        <th class="py-3 px-4">Closed By</th>
                        <th class="py-3 px-4">Status</th>
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
                                {{ $closing->location?->name ?? 'Head Office' }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-ink">
                                ৳{{ number_format((float) $closing->system_cash, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-ink">
                                ৳{{ number_format((float) $closing->counted_cash, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono">
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
                            <td class="py-3.5 px-4">
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
                            <td class="py-3.5 px-4 text-right">
                                @if($isSuperAdmin && $closing->status === 'closed')
                                    <form method="POST" action="{{ route('day-closings.reopen', $closing) }}" class="inline"
                                          onsubmit="return confirm('Are you sure you want to reopen this day? It will unlock transactions for this date.');">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="reopen_reason" value="Super Admin manual audit unlock">
                                        <button type="submit" class="text-xs font-semibold text-danger hover:underline">
                                            Reopen
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted">
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
</div>
@endsection
