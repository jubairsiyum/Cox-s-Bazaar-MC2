@props([
'performance' => [],
'branchName' => null,
])

@php
$targetAmount = (float) ($performance['target_amount'] ?? 0);
$monthSales = (float) ($performance['month_sales'] ?? 0);
$achievementPct = (float) ($performance['achievement_pct'] ?? 0);
$remainingAmount = (float) ($performance['remaining_amount'] ?? 0);
$todaySales = (float) ($performance['today_sales'] ?? 0);
$yesterdaySales = (float) ($performance['yesterday_sales'] ?? 0);
$dodGrowthPct = (float) ($performance['dod_growth_pct'] ?? 0);
$dodDiffAmount = (float) ($performance['dod_diff_amount'] ?? 0);
$daysRemaining = (int) ($performance['days_remaining'] ?? 0);
$requiredDailySales = (float) ($performance['required_daily_sales'] ?? 0);
$status = $performance['status'] ?? 'not_set';
$isCurrentMonth = $performance['is_current_month'] ?? true;

// Status styling
$statusBadge = match ($status) {
'achieved' => ['label' => 'Target Achieved', 'color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20'],
'on_track' => ['label' => 'On Track', 'color' => 'bg-brand-500/10 text-brand-600 dark:text-brand-400 ring-brand-500/20'],
'behind' => ['label' => 'Needs Push', 'color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/20'],
default => ['label' => 'Target Not Set', 'color' => 'bg-muted/10 text-muted ring-muted/20'],
};

// Day-over-Day growth indicator
$isPositiveDod = $dodDiffAmount >= 0;
$dodColor = $isPositiveDod ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger';
$dodBg = $isPositiveDod ? 'bg-emerald-500/10' : 'bg-danger/10';
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl bg-surface p-6 shadow-card border border-line/60']) }}>
    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2.5">
            <span class="grid size-9 place-items-center rounded-2xl bg-brand-500/10 text-brand-500">
                <x-icon name="target" class="size-4.5" />
            </span>
            <div>
                <h3 class="text-sm sm:text-base font-bold text-ink">
                    Monthly Sales Target
                </h3>
                @if ($branchName)
                <p class="text-[11px] text-muted font-medium">{{ $branchName }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusBadge['color'] }}">
                <span class="size-1.5 rounded-full bg-current"></span>
                {{ $statusBadge['label'] }}
            </span>
            <a href="{{ route('branch-targets.index') }}"
                class="grid size-7 place-items-center rounded-full text-muted hover:text-brand-500 hover:bg-soft transition-colors"
                title="View target details">
                <x-icon name="arrow-up-right" class="size-3.5" />
            </a>
        </div>
    </div>

    {{-- Main Target Metric --}}
    <div class="mt-5 grid sm:grid-cols-2 gap-5 items-center">
        <div>
            <div class="flex items-baseline gap-1.5 text-ink">
                <x-taka class="size-6 sm:size-7 text-ink shrink-0 -mt-0.5" strokeWidth="2.2" />
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums" x-count="{{ $monthSales }}">
                    {{ number_format($monthSales) }}
                </span>
                <span class="text-xs sm:text-sm font-semibold text-muted">
                    / {{ $targetAmount > 0 ? '৳ ' . number_format($targetAmount) : 'Not Set' }}
                </span>
            </div>

            {{-- Progress Bar --}}
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs font-semibold mb-1">
                    <span class="text-muted">Target Filled</span>
                    <span class="text-ink font-bold tabular-nums"><span x-count="{{ $achievementPct }}" data-decimals="1">{{ $achievementPct }}</span>%</span>
                </div>
                <div class="relative h-2.5 w-full overflow-hidden rounded-full bg-soft">
                    <div class="h-full rounded-full transition-all duration-700 ease-out {{ $status === 'achieved' ? 'bg-emerald-500' : ($status === 'behind' ? 'bg-amber-500' : 'bg-brand-500') }}"
                        style="width: {{ min(100, $achievementPct) }}%"></div>
                </div>
            </div>
        </div>

        {{-- DoD Sales Comparison (Today vs Yesterday) & Run Rate --}}
        <div class="space-y-2.5 rounded-2xl bg-soft/50 p-3.5 border border-line/40 text-xs">
            {{-- DoD comparison --}}
            <div class="flex items-center justify-between">
                <span class="text-muted font-medium">Today vs Yesterday:</span>
                <div class="flex items-center gap-1.5 font-bold tabular-nums {{ $dodColor }}">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $dodBg }}">
                        {{ $isPositiveDod ? '+' : '-' }}<span x-count="{{ abs((float) $dodGrowthPct) }}" data-decimals="1">{{ number_format(abs((float) $dodGrowthPct), 1) }}</span>%
                    </span>
                    <span>({{ $isPositiveDod ? '+৳ ' : '-৳ ' }}<span x-count="{{ abs((float) $dodDiffAmount) }}">{{ number_format(abs((float) $dodDiffAmount)) }}</span>)</span>
                </div>
            </div>

            {{-- Today's sales --}}
            <div class="flex items-center justify-between">
                <span class="text-muted font-medium">Today's Sales:</span>
                <span class="font-bold text-ink tabular-nums">৳ <span x-count="{{ $todaySales }}">{{ number_format($todaySales) }}</span></span>
            </div>

            {{-- Run Rate / Daily Required --}}
            @if ($targetAmount > 0 && $remainingAmount > 0 && $isCurrentMonth)
            <div class="flex items-center justify-between pt-1 border-t border-line/40">
                <span class="text-muted font-medium">Daily Required ({{ $daysRemaining }}d left):</span>
                <span class="font-bold text-brand-600 dark:text-brand-400 tabular-nums">
                    ৳ <span x-count="{{ $requiredDailySales }}">{{ number_format($requiredDailySales) }}</span>/day
                </span>
            </div>
            @elseif ($targetAmount > 0 && $remainingAmount <= 0)
                <div class="flex items-center justify-between pt-1 border-t border-line/40 text-emerald-600 font-bold">
                <span>Target Completed!</span>
                <span>🎉 100% Achieved</span>
        </div>
        @else
        <div class="flex items-center justify-between pt-1 border-t border-line/40 text-muted">
            <span>Remaining:</span>
            <span class="font-semibold text-ink">৳ {{ number_format($remainingAmount) }}</span>
        </div>
        @endif
    </div>
</div>
</div>