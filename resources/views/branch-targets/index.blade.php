@extends('layouts.app')

@section('title', 'Monthly Sales Targets — ' . config('app.name', 'Cox\'s Bazaar Mobile Center'))
@section('page_title', 'Monthly Sales Targets')

@php
    $prevMonth = $activeDate->copy()->subMonth()->format('Y-m');
    $nextMonth = $activeDate->copy()->addMonth()->format('Y-m');
    $currentMonth = now()->format('Y-m');
@endphp

@section('content')
<div class="space-y-6" x-data="targetManager()">

    {{-- ── Flash Notifications ── --}}
    @if (session('status'))
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-xs sm:text-sm font-semibold text-emerald-600 dark:text-emerald-400">
            <div class="flex items-center gap-2.5">
                <x-icon name="check" class="size-4 shrink-0" />
                <span>{{ session('status') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:opacity-75">
                &times;
            </button>
        </div>
    @endif

    {{-- ── Top Navigation & Month Switcher Bar ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-1">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="grid size-9 place-items-center rounded-2xl bg-brand-500/10 text-brand-500">
                    <x-icon name="target" class="size-4.5" />
                </span>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
                    Monthly Sales Targets
                </h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-3 py-1 text-xs font-semibold text-muted ring-1 ring-line">
                    {{ $activeDate->format('F Y') }}
                </span>
            </div>
            <p class="mt-1 text-xs sm:text-sm text-muted">
                @if ($isSuperAdmin)
                    Set, monitor, and manage monthly sales quotas across all distribution outlets and brand shops.
                @else
                    Track your branch sales quota pacing, Day-over-Day achievement, and daily required run-rate.
                @endif
            </p>
        </div>

        {{-- Month Selector & Action Controls --}}
        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
            {{-- Month Switcher Group --}}
            <div class="inline-flex items-center rounded-full border border-line bg-surface p-1 shadow-2xs">
                <a href="{{ route('branch-targets.index', ['month' => $prevMonth]) }}"
                   class="grid size-7.5 place-items-center rounded-full text-muted hover:bg-soft hover:text-ink transition-colors"
                   title="Previous Month ({{ $activeDate->copy()->subMonth()->format('M Y') }})">
                    <x-icon name="chevron" class="size-3.5 rotate-90" />
                </a>

                <span class="px-3 text-xs font-bold text-ink select-none">
                    {{ $activeDate->format('M Y') }}
                </span>

                <a href="{{ route('branch-targets.index', ['month' => $nextMonth]) }}"
                   class="grid size-7.5 place-items-center rounded-full text-muted hover:bg-soft hover:text-ink transition-colors"
                   title="Next Month ({{ $activeDate->copy()->addMonth()->format('M Y') }})">
                    <x-icon name="chevron" class="size-3.5 -rotate-90" />
                </a>

                @if ($activeMonth !== $currentMonth)
                    <a href="{{ route('branch-targets.index', ['month' => $currentMonth]) }}"
                       class="ml-1 rounded-full bg-soft px-2.5 py-1 text-[11px] font-semibold text-brand-500 hover:bg-brand-500/10 transition-colors">
                        Current Month
                    </a>
                @endif
            </div>

            {{-- Super Admin: Set Target Button --}}
            @if ($isSuperAdmin)
                <button type="button"
                        @click="openModal()"
                        class="btn-scale inline-flex items-center gap-2 rounded-full bg-brand-500 px-4 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                    <x-icon name="plus" class="size-3.5" />
                    <span>Set Target</span>
                </button>
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
         SUPER ADMIN CONSOLIDATED VIEW
    ════════════════════════════════════════════════════════════════════════ --}}
    @if ($isSuperAdmin && $overview)
        {{-- KPI Cards --}}
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 items-stretch">
            {{-- 1. Total Network Target --}}
            <div class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between border border-line/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-semibold text-ink/80">Network Target</h3>
                    <span class="grid size-8 sm:size-9 place-items-center rounded-2xl bg-soft text-brand-500">
                        <x-icon name="target" class="size-4" />
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-ink leading-tight flex items-center gap-1">
                        <x-taka class="size-6 sm:size-7 text-ink shrink-0 -mt-0.5" strokeWidth="2.2" />
                        <span class="tabular-nums">{{ number_format($overview['total_target']) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        {{ $overview['branches']->where('target_amount', '>', 0)->count() }} of {{ $overview['branches']->count() }} Outlets configured
                    </p>
                </div>
            </div>

            {{-- 2. Total Achieved Sales --}}
            <div class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between border border-line/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-semibold text-ink/80">Achieved Sales (MTD)</h3>
                    <span class="grid size-8 sm:size-9 place-items-center rounded-2xl bg-soft text-emerald-600">
                        <x-icon name="cart" class="size-4" />
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-ink leading-tight flex items-center gap-1">
                        <x-taka class="size-6 sm:size-7 text-ink shrink-0 -mt-0.5" strokeWidth="2.2" />
                        <span class="tabular-nums">{{ number_format($overview['total_sales']) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        Remaining: ৳ {{ number_format($overview['remaining_amount']) }}
                    </p>
                </div>
            </div>

            {{-- 3. Overall Target Progress --}}
            <div class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between border border-line/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-semibold text-ink/80">Overall Progress</h3>
                    <span class="grid size-8 sm:size-9 place-items-center rounded-2xl bg-soft text-brand-500">
                        <x-icon name="trend-up" class="size-4" />
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-baseline justify-between">
                        <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-ink leading-tight tabular-nums">
                            {{ $overview['achievement_pct'] }}%
                        </p>
                        <span class="text-xs font-semibold text-muted">Filled</span>
                    </div>
                    <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-soft">
                        <div class="h-full rounded-full bg-brand-500 transition-all duration-500"
                             style="width: {{ min(100, $overview['achievement_pct']) }}%"></div>
                    </div>
                </div>
            </div>

            {{-- 4. DoD Growth --}}
            @php
                $isNetworkDodUp = $overview['dod_growth_pct'] >= 0;
            @endphp
            <div class="relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card flex flex-col justify-between border border-line/50">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs sm:text-sm font-semibold text-ink/80">Day-over-Day Sales</h3>
                    <span class="grid size-8 sm:size-9 place-items-center rounded-2xl bg-soft {{ $isNetworkDodUp ? 'text-emerald-600' : 'text-danger' }}">
                        <x-icon name="arrow-up-right" class="size-4 {{ $isNetworkDodUp ? '' : 'rotate-90' }}" />
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-baseline gap-2">
                        <p class="text-2xl sm:text-[26px] font-bold tracking-tight {{ $isNetworkDodUp ? 'text-emerald-600' : 'text-danger' }} tabular-nums">
                            {{ $isNetworkDodUp ? '+' : '-' }}{{ number_format(abs((float) $overview['dod_growth_pct']), 1) }}%
                        </p>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        Today: ৳ {{ number_format($overview['total_today_sales']) }} vs Yesterday: ৳ {{ number_format($overview['total_yesterday_sales']) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Branch Targets Breakdown Table --}}
        <div class="rounded-3xl border border-line bg-surface shadow-card overflow-hidden">
            <div class="p-6 border-b border-line flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-base font-bold text-ink">Branch Quotas & Pacing Breakdown</h2>
                    <p class="text-xs text-muted mt-0.5">Real-time sales target performance for {{ $activeDate->format('F Y') }}</p>
                </div>

                {{-- Status Pills Filter / Summary --}}
                <div class="flex items-center gap-2 text-xs flex-wrap">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 font-semibold text-emerald-600">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {{ $overview['achieved_branches_count'] }} Achieved
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-2.5 py-1 font-semibold text-brand-600">
                        <span class="size-1.5 rounded-full bg-brand-500"></span>
                        {{ $overview['on_track_branches_count'] }} On Track
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-1 font-semibold text-amber-600">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        {{ $overview['behind_branches_count'] }} Needs Push
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-line bg-soft/40 text-[11px] font-semibold uppercase tracking-wider text-muted">
                            <th class="py-3.5 px-6">Branch / Outlet</th>
                            <th class="py-3.5 px-4 text-right">Target (৳)</th>
                            <th class="py-3.5 px-4 text-right">Achieved (৳)</th>
                            <th class="py-3.5 px-6">Progress (% Filled)</th>
                            <th class="py-3.5 px-4 text-right">Today vs Yesterday</th>
                            <th class="py-3.5 px-4 text-right">Daily Run-Rate</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($overview['branches'] as $row)
                            @php
                                $loc = $row['location'];
                                $rowStatus = $row['status'];
                                $isRowDodUp = $row['dod_diff_amount'] >= 0;
                            @endphp
                            <tr class="hover:bg-soft/30 transition-colors">
                                {{-- Branch Info --}}
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-soft text-brand-500">
                                            <x-icon name="store" class="size-4" />
                                        </span>
                                        <div>
                                            <p class="font-bold text-ink">{{ $loc->name }}</p>
                                            <p class="text-[11px] text-muted">{{ $loc->code }} · {{ ucfirst(str_replace('_', ' ', $loc->type?->value ?? 'Outlet')) }}</p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Target --}}
                                <td class="py-4 px-4 text-right font-bold text-ink tabular-nums">
                                    @if ($row['target_amount'] > 0)
                                        ৳ {{ number_format($row['target_amount']) }}
                                    @else
                                        <span class="text-muted font-normal italic">Not set</span>
                                    @endif
                                </td>

                                {{-- Achieved --}}
                                <td class="py-4 px-4 text-right font-bold text-ink tabular-nums">
                                    ৳ {{ number_format($row['month_sales']) }}
                                </td>

                                {{-- Progress --}}
                                <td class="py-4 px-6 min-w-[170px]">
                                    <div class="flex items-center justify-between text-[11px] font-semibold mb-1">
                                        <span class="text-muted tabular-nums">{{ $row['achievement_pct'] }}%</span>
                                        <span class="text-muted">৳ {{ number_format($row['remaining_amount']) }} left</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-soft">
                                        <div class="h-full rounded-full transition-all duration-500 {{ $rowStatus === 'achieved' ? 'bg-emerald-500' : ($rowStatus === 'behind' ? 'bg-amber-500' : 'bg-brand-500') }}"
                                             style="width: {{ min(100, $row['achievement_pct']) }}%"></div>
                                    </div>
                                </td>

                                {{-- DoD --}}
                                <td class="py-4 px-4 text-right tabular-nums">
                                    <div class="inline-flex items-center gap-1 font-semibold {{ $isRowDodUp ? 'text-emerald-600' : 'text-danger' }}">
                                        <span>{{ $isRowDodUp ? '↑' : '↓' }} {{ $isRowDodUp ? '+' : '-' }}{{ number_format(abs((float) $row['dod_growth_pct']), 1) }}%</span>
                                    </div>
                                    <p class="text-[10px] text-muted">Today: ৳ {{ number_format($row['today_sales']) }}</p>
                                </td>

                                {{-- Required Run-Rate --}}
                                <td class="py-4 px-4 text-right tabular-nums">
                                    @if ($row['target_amount'] > 0 && $row['remaining_amount'] > 0 && $row['days_remaining'] > 0)
                                        <p class="font-bold text-brand-600 dark:text-brand-400">
                                            ৳ {{ number_format($row['required_daily_sales']) }}/d
                                        </p>
                                        <p class="text-[10px] text-muted">{{ $row['days_remaining'] }} days left</p>
                                    @elseif ($row['target_amount'] > 0 && $row['remaining_amount'] <= 0)
                                        <span class="text-emerald-600 font-bold">Goal Met</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td class="py-4 px-4 text-center">
                                    @if ($rowStatus === 'achieved')
                                        <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 ring-1 ring-inset ring-emerald-500/20">
                                            Achieved
                                        </span>
                                    @elseif ($rowStatus === 'on_track')
                                        <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                            On Track
                                        </span>
                                    @elseif ($rowStatus === 'behind')
                                        <span class="inline-flex items-center rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-600 ring-1 ring-inset ring-amber-500/20">
                                            Needs Push
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-soft px-2.5 py-0.5 text-[11px] font-semibold text-muted ring-1 ring-inset ring-line">
                                            Not Set
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="py-4 px-6 text-right">
                                    <button type="button"
                                            @click="editTarget({{ (int) $loc->id }}, @js($loc->name), {{ (float) $row['target_amount'] }}, @js($row['target_record']?->notes ?? ''))"
                                            class="btn-scale inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink hover:border-brand-500 hover:text-brand-500 transition-colors cursor-pointer">
                                        <x-icon name="plus" class="size-3" />
                                        <span>{{ $row['target_amount'] > 0 ? 'Edit' : 'Set' }}</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-muted">
                                    No active branches or locations found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════════════════════
         BRANCH MANAGER / RESTRICTED SCOPE VIEW
    ════════════════════════════════════════════════════════════════════════ --}}
    @else
        @if (! $assignedLocation || ! $branchPerformance)
            <div class="rounded-3xl border border-warning/30 bg-warning/10 p-6 text-warning flex items-start gap-4">
                <x-icon name="alert-triangle" class="size-6 shrink-0 mt-0.5 text-warning" />
                <div>
                    <h3 class="font-bold text-ink text-base">No Branch Assigned</h3>
                    <p class="mt-1 text-xs sm:text-sm text-muted">
                        Your account is currently not assigned to a specific branch or outlet. Please contact the Super Admin to assign your location under User Management.
                    </p>
                </div>
            </div>
        @else
            {{-- Branch Manager Performance Dashboard --}}
            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Left: Main Target Card (spans 2 cols) --}}
                <div class="lg:col-span-2">
                    <x-dashboard.monthly-target-card
                        :performance="$branchPerformance"
                        :branchName="$assignedLocation->name"
                    />

                    {{-- Calendar & Pacing Details --}}
                    <div class="mt-6 rounded-3xl border border-line bg-surface p-6 shadow-card">
                        <h3 class="text-sm font-bold text-ink mb-4">Monthly Target Pacing & Milestone Schedule</h3>
                        <div class="grid sm:grid-cols-3 gap-4">
                            <div class="rounded-2xl bg-soft/50 p-4 border border-line/40">
                                <span class="text-xs text-muted font-medium">Days Passed</span>
                                <p class="text-xl font-bold text-ink mt-1 tabular-nums">
                                    {{ $branchPerformance['days_passed'] }} / {{ $branchPerformance['days_in_month'] }} days
                                </p>
                                <p class="text-[11px] text-muted mt-1">{{ $branchPerformance['days_remaining'] }} days remaining</p>
                            </div>

                            <div class="rounded-2xl bg-soft/50 p-4 border border-line/40">
                                <span class="text-xs text-muted font-medium">Current Average Run-Rate</span>
                                <p class="text-xl font-bold text-ink mt-1 tabular-nums">
                                    ৳ {{ number_format($branchPerformance['current_daily_average']) }}
                                </p>
                                <p class="text-[11px] text-muted mt-1">per day sold so far</p>
                            </div>

                            <div class="rounded-2xl bg-soft/50 p-4 border border-line/40">
                                <span class="text-xs text-muted font-medium">Required Pace (To Hit Target)</span>
                                <p class="text-xl font-bold text-brand-600 dark:text-brand-400 mt-1 tabular-nums">
                                    ৳ {{ number_format($branchPerformance['required_daily_sales']) }}
                                </p>
                                <p class="text-[11px] text-muted mt-1">needed per remaining day</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Day-over-Day Deep Dive & Quick Tips --}}
                <div class="space-y-6">
                    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
                        <div class="flex items-center justify-between pb-3 border-b border-line">
                            <h3 class="text-sm font-bold text-ink">Day-over-Day Sales</h3>
                            <span class="text-xs text-muted">Comparison</span>
                        </div>

                        <div class="mt-4 space-y-4 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-muted">Today's Sales:</span>
                                <span class="font-bold text-ink text-sm tabular-nums">৳ {{ number_format($branchPerformance['today_sales']) }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-muted">Yesterday's Sales:</span>
                                <span class="font-bold text-ink text-sm tabular-nums">৳ {{ number_format($branchPerformance['yesterday_sales']) }}</span>
                            </div>

                            <div class="rounded-2xl p-4 {{ $branchPerformance['dod_diff_amount'] >= 0 ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-danger/10 text-danger' }}">
                                <div class="flex items-center gap-2 font-bold text-sm">
                                    <x-icon name="arrow-up-right" class="size-4.5 {{ $branchPerformance['dod_diff_amount'] >= 0 ? '' : 'rotate-90' }}" />
                                    <span>
                                        {{ $branchPerformance['dod_diff_amount'] >= 0 ? '+' : '-' }}{{ number_format(abs((float) $branchPerformance['dod_growth_pct']), 1) }}% Growth
                                    </span>
                                </div>
                                <p class="text-xs mt-1 font-normal opacity-90">
                                    {{ $branchPerformance['dod_diff_amount'] >= 0 ? 'Sales increased by' : 'Sales dropped by' }}
                                    ৳ {{ number_format(abs($branchPerformance['dod_diff_amount'])) }} compared to yesterday.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Actions for Manager --}}
                    <div class="rounded-3xl border border-line bg-surface p-6 shadow-card">
                        <h3 class="text-sm font-bold text-ink mb-3">Branch Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="{{ route('pos.index') }}"
                               class="flex items-center justify-between rounded-xl p-3 bg-soft/50 hover:bg-soft text-xs font-semibold text-ink transition-colors">
                                <span class="flex items-center gap-2">
                                    <x-icon name="cart" class="size-4 text-brand-500" />
                                    <span>Open POS Terminal</span>
                                </span>
                                <x-icon name="chevron" class="size-3 -rotate-90 text-muted" />
                            </a>

                            <a href="{{ route('sales-orders.create') }}"
                               class="flex items-center justify-between rounded-xl p-3 bg-soft/50 hover:bg-soft text-xs font-semibold text-ink transition-colors">
                                <span class="flex items-center gap-2">
                                    <x-icon name="plus" class="size-4 text-brand-500" />
                                    <span>Create Sales Order</span>
                                </span>
                                <x-icon name="chevron" class="size-3 -rotate-90 text-muted" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════
         SUPER ADMIN TARGET MODAL (Alpine.js)
    ════════════════════════════════════════════════════════════════════════ --}}
    @if ($isSuperAdmin)
        <div x-show="isModalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="closeModal()">
            {{-- Backdrop --}}
            <div x-show="isModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-ink/40 backdrop-blur-xs"
                 @click="closeModal()"></div>

            {{-- Modal Content --}}
            <div x-show="isModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-md overflow-hidden rounded-3xl bg-surface border border-line p-6 shadow-2xl z-10">

                <div class="flex items-center justify-between pb-4 border-b border-line">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-brand-500/10 text-brand-500">
                            <x-icon name="target" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-ink truncate"
                                x-text="form.branch_name ? ((form.target_amount > 0 ? 'Edit Target: ' : 'Set Target: ') + form.branch_name) : (form.target_amount > 0 ? 'Edit Sales Target' : 'Set Monthly Sales Target')"></h3>
                            <p class="text-[11px] text-muted font-medium" x-show="form.branch_name" x-text="'Target Month: ' + form.target_month"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeModal()" class="grid size-7 shrink-0 place-items-center rounded-full text-muted hover:bg-soft hover:text-ink">
                        &times;
                    </button>
                </div>

                <form method="POST" action="{{ route('branch-targets.store') }}" class="mt-5 space-y-4 text-xs">
                    @csrf

                    {{-- Branch Selection --}}
                    <div>
                        <label for="location_id" class="block font-semibold text-ink mb-1">Target Branch / Outlet</label>
                        <select id="location_id" name="location_id" x-model="form.location_id" required
                                class="w-full rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs text-ink focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <option value="">Select a branch</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Target Month --}}
                    <div>
                        <label for="target_month" class="block font-semibold text-ink mb-1">Target Month</label>
                        <input type="month" id="target_month" name="target_month" x-model="form.target_month" required
                               class="w-full rounded-2xl border border-line bg-surface px-3.5 py-2.5 text-xs text-ink focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                    </div>

                    {{-- Target Amount in BDT --}}
                    <div>
                        <label for="target_amount" class="block font-semibold text-ink mb-1">Target Sales Quota (৳ BDT)</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-muted">৳</span>
                            <input type="number" id="target_amount" name="target_amount" x-model="form.target_amount"
                                   step="1000" min="0" required placeholder="e.g. 500000"
                                   class="w-full rounded-2xl border border-line bg-surface pl-8 pr-3.5 py-2.5 text-xs font-bold text-ink tabular-nums focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                        </div>

                        {{-- Quick Presets --}}
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                            <span class="text-[10px] text-muted">Quick Presets:</span>
                            <button type="button" @click="form.target_amount = 200000" class="rounded-lg bg-soft px-2 py-0.5 text-[10px] font-semibold text-ink hover:bg-brand-500/10 hover:text-brand-500">৳ 2L</button>
                            <button type="button" @click="form.target_amount = 500000" class="rounded-lg bg-soft px-2 py-0.5 text-[10px] font-semibold text-ink hover:bg-brand-500/10 hover:text-brand-500">৳ 5L</button>
                            <button type="button" @click="form.target_amount = 1000000" class="rounded-lg bg-soft px-2 py-0.5 text-[10px] font-semibold text-ink hover:bg-brand-500/10 hover:text-brand-500">৳ 10L</button>
                            <button type="button" @click="form.target_amount = 2000000" class="rounded-lg bg-soft px-2 py-0.5 text-[10px] font-semibold text-ink hover:bg-brand-500/10 hover:text-brand-500">৳ 20L</button>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label for="notes" class="block font-semibold text-ink mb-1">Notes / Instructions (Optional)</label>
                        <textarea id="notes" name="notes" x-model="form.notes" rows="2" placeholder="e.g. Eid campaign promotional sales quota"
                                  class="w-full rounded-2xl border border-line bg-surface px-3.5 py-2 text-xs text-ink focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"></textarea>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-line">
                        <button type="button" @click="closeModal()"
                                class="rounded-full border border-line px-4 py-2 text-xs font-semibold text-muted hover:bg-soft hover:text-ink transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="btn-scale rounded-full bg-brand-500 px-5 py-2 text-xs font-semibold text-on-brand shadow-sm hover:bg-brand-600 transition-colors cursor-pointer">
                            Save Target
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>

<script>
function targetManager() {
    return {
        isModalOpen: false,
        form: {
            location_id: '',
            branch_name: '',
            target_month: '{{ $activeMonth }}',
            target_amount: '',
            notes: ''
        },
        openModal() {
            this.form.location_id = '';
            this.form.branch_name = '';
            this.form.target_amount = '';
            this.form.notes = '';
            this.form.target_month = '{{ $activeMonth }}';
            this.isModalOpen = true;
        },
        editTarget(locationId, locationName, targetAmount, notes) {
            this.form.location_id = String(locationId);
            this.form.branch_name = locationName || '';
            this.form.target_month = '{{ $activeMonth }}';
            this.form.target_amount = targetAmount > 0 ? targetAmount : '';
            this.form.notes = notes || '';
            this.isModalOpen = true;
        },
        closeModal() {
            this.isModalOpen = false;
        }
    };
}
</script>
@endsection
