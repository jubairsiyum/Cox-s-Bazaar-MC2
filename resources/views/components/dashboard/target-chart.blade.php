@props([
'isSuperAdmin' => false,
'branchPerformance' => null,
'networkOverview' => null,
'branchName' => null,
])

@php
$activeDate = now();
$monthName = $activeDate->format('F Y');

if ($isSuperAdmin && $networkOverview) {
$targetAmount = (float) ($networkOverview['total_target'] ?? 0);
$achievedSales = (float) ($networkOverview['total_sales'] ?? 0);
$remainingAmount = (float) ($networkOverview['remaining_amount'] ?? 0);
$achievementPct = (float) ($networkOverview['achievement_pct'] ?? 0);
$todaySales = (float) ($networkOverview['total_today_sales'] ?? 0);
$yesterdaySales = (float) ($networkOverview['total_yesterday_sales'] ?? 0);
$dodGrowthPct = (float) ($networkOverview['dod_growth_pct'] ?? 0);
$branches = $networkOverview['branches'] ?? collect();
$configuredCount = $branches->where('target_amount', '>', 0)->count();
$totalCount = $branches->count();

$status = $targetAmount <= 0 ? 'not_set' : ($achievedSales>= $targetAmount ? 'achieved' : ($achievementPct >= 50 ? 'on_track' : 'behind'));
    $title = 'Network Sales Target';
    $subtitle = "Consolidated target across {$totalCount} outlets · {$monthName}";
    } else {
    $perf = $branchPerformance ?? [];
    $targetAmount = (float) ($perf['target_amount'] ?? 0);
    $achievedSales = (float) ($perf['month_sales'] ?? 0);
    $remainingAmount = (float) ($perf['remaining_amount'] ?? 0);
    $achievementPct = (float) ($perf['achievement_pct'] ?? 0);
    $todaySales = (float) ($perf['today_sales'] ?? 0);
    $yesterdaySales = (float) ($perf['yesterday_sales'] ?? 0);
    $dodGrowthPct = (float) ($perf['dod_growth_pct'] ?? 0);
    $daysRemaining = (int) ($perf['days_remaining'] ?? 0);
    $requiredDailySales = (float) ($perf['required_daily_sales'] ?? 0);
    $status = $perf['status'] ?? 'not_set';
    $title = 'Monthly Sales Target';
    $subtitle = ($branchName ? $branchName . ' · ' : '') . $monthName;
    $branches = collect();
    }

    $isDodPositive = $todaySales >= $yesterdaySales;
    $dodDiff = abs($todaySales - $yesterdaySales);

    // Donut math for SVG (radius = 54, circumference = 2 * pi * 54 ≈ 339.29)
    $radius = 54;
    $circumference = 2 * M_PI * $radius; // ~339.292
    $clampedPct = min(100.0, max(0.0, $achievementPct));
    $achievedDash = ($clampedPct / 100.0) * $circumference;
    $remainingDash = $circumference - $achievedDash;

    // Status styling
    $statusConfig = match ($status) {
    'achieved' => ['label' => 'Target Met', 'color' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-emerald-500/20', 'bar' => '#10b981'],
    'on_track' => ['label' => 'On Track', 'color' => 'bg-brand-500/10 text-brand-600 dark:text-brand-400 ring-brand-500/20', 'bar' => '#465fff'],
    'behind' => ['label' => 'Needs Push', 'color' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-amber-500/20', 'bar' => '#f59e0b'],
    default => ['label' => 'Not Set', 'color' => 'bg-muted/10 text-muted ring-muted/20', 'bar' => '#94a3b8'],
    };
    @endphp

    <section {{ $attributes->merge(['class' => 'h-full flex flex-col justify-between rounded-3xl bg-surface p-6 shadow-card border border-line/60']) }}
        x-data="{ activeSlice: null, view: 'pie' }">

        {{-- Card Header --}}
        <div>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-2xl bg-brand-500/10 text-brand-500">
                        <x-icon name="target" class="size-4.5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base font-bold text-ink truncate">{{ $title }}</h3>
                        <p class="text-[11px] text-muted truncate">{{ $subtitle }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $statusConfig['color'] }}">
                        <span class="size-1.5 rounded-full bg-current"></span>
                        {{ $statusConfig['label'] }}
                    </span>
                    <a href="{{ route('branch-targets.index') }}"
                        class="grid size-7.5 place-items-center rounded-full text-muted hover:bg-soft hover:text-brand-500 transition-colors"
                        title="View target details">
                        <x-icon name="arrow-up-right" class="size-3.5" />
                    </a>
                </div>
            </div>

            {{-- Main Chart & Metrics Grid --}}
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-12 gap-5 items-center">

                {{-- Left: Visual Donut / Pie Chart (5 cols) with Motion Gauge --}}
                <div class="sm:col-span-5 flex flex-col items-center justify-center relative">
                    <div class="relative size-44 sm:size-48 flex items-center justify-center">
                        <svg class="size-full -rotate-90 transform" viewBox="0 0 140 140">
                            {{-- Background Track --}}
                            <circle
                                cx="70"
                                cy="70"
                                r="{{ $radius }}"
                                pathLength="100"
                                stroke="currentColor"
                                stroke-width="14"
                                fill="transparent"
                                class="text-soft dark:text-soft/60" />

                            {{-- Achieved Arc with Motion Gauge Animation --}}
                            @if ($targetAmount > 0 && $achievedSales > 0)
                            <circle
                                cx="70"
                                cy="70"
                                r="{{ $radius }}"
                                pathLength="100"
                                stroke="{{ $statusConfig['bar'] }}"
                                stroke-width="14"
                                fill="transparent"
                                stroke-linecap="round"
                                class="gauge cursor-pointer hover:opacity-90 transition-opacity"
                                style="--target-pct: {{ min(100, max(0, round($achievementPct, 1))) }}; stroke-dasharray: {{ min(100, max(0, round($achievementPct, 1))) }} 100;"
                                stroke-dasharray="{{ min(100, max(0, round($achievementPct, 1))) }} 100"
                                @mouseenter="activeSlice = 'achieved'"
                                @mouseleave="activeSlice = null" />
                            @endif
                        </svg>

                        {{-- Donut Center Info with Gauge-Label Entrance --}}
                        <div class="gauge-label absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none select-none">
                            <template x-if="activeSlice === 'achieved'">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Achieved</span>
                                    <p class="text-base sm:text-lg font-extrabold text-ink tabular-nums">
                                        ৳ {{ $achievedSales >= 100000 ? rtrim(rtrim(number_format($achievedSales / 100000, 1), '0'), '.') . 'L' : number_format($achievedSales) }}
                                    </p>
                                </div>
                            </template>

                            <template x-if="activeSlice === 'remaining'">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Remaining</span>
                                    <p class="text-base sm:text-lg font-extrabold text-ink tabular-nums">
                                        ৳ {{ $remainingAmount >= 100000 ? rtrim(rtrim(number_format($remainingAmount / 100000, 1), '0'), '.') . 'L' : number_format($remainingAmount) }}
                                    </p>
                                </div>
                            </template>

                            <template x-if="!activeSlice">
                                <div>
                                    <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink tabular-nums leading-none">
                                        <span x-count="{{ $achievementPct }}" data-decimals="1">{{ $achievementPct }}</span>%
                                    </p>
                                    <span class="text-[11px] font-semibold text-muted mt-1 block">Completed</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Interactive Legend --}}
                    <div class="mt-3 flex items-center justify-center gap-4 text-xs">
                        <button type="button"
                            @mouseenter="activeSlice = 'achieved'"
                            @mouseleave="activeSlice = null"
                            class="inline-flex items-center gap-1.5 text-muted hover:text-ink transition-colors cursor-pointer">
                            <span class="size-2.5 rounded-full" style="background-color: {{ $statusConfig['bar'] }}"></span>
                            <span class="font-medium">Achieved</span>
                        </button>
                        <button type="button"
                            @mouseenter="activeSlice = 'remaining'"
                            @mouseleave="activeSlice = null"
                            class="inline-flex items-center gap-1.5 text-muted hover:text-ink transition-colors cursor-pointer">
                            <span class="size-2.5 rounded-full bg-soft dark:bg-muted/40"></span>
                            <span class="font-medium">Remaining</span>
                        </button>
                    </div>
                </div>

                {{-- Right: Numerical Breakdown & Day-over-Day Stats (7 cols) --}}
                <div class="sm:col-span-7 space-y-3">
                    {{-- Quota vs Achieved Row --}}
                    <div class="rounded-2xl bg-soft/40 p-3.5 border border-line/50 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-muted font-medium">Monthly Target:</span>
                            <span class="font-bold text-ink tabular-nums">
                                @if ($targetAmount > 0)
                                ৳ <span x-count="{{ $targetAmount }}">{{ number_format($targetAmount) }}</span>
                                @else
                                <span class="text-muted font-normal italic">Not configured</span>
                                @endif
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted font-medium">Month-to-Date Sales:</span>
                            <span class="font-bold text-ink tabular-nums">
                                ৳ <span x-count="{{ $achievedSales }}">{{ number_format($achievedSales) }}</span>
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-line/40">
                            <span class="text-muted font-medium">Remaining Quota:</span>
                            <span class="font-bold {{ $remainingAmount > 0 ? 'text-ink' : 'text-emerald-600' }} tabular-nums">
                                @if ($targetAmount > 0 && $remainingAmount <= 0)
                                    Goal Achieved! 🎉
                                    @else
                                    ৳ <span x-count="{{ $remainingAmount }}">{{ number_format($remainingAmount) }}</span>
                            @endif
                            </span>
                        </div>
                    </div>

                    {{-- Day-over-Day Growth Pill --}}
                    <div class="rounded-2xl bg-soft/40 p-3.5 border border-line/50 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-muted font-medium">Today vs Yesterday:</span>
                            <div class="inline-flex items-center gap-1 font-bold tabular-nums {{ $isDodPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger' }}">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isDodPositive ? 'bg-emerald-500/10' : 'bg-danger/10' }}">
                                    {{ $isDodPositive ? '↑ +' : '↓ -' }}<span x-count="{{ abs((float) $dodGrowthPct) }}" data-decimals="1">{{ number_format(abs((float) $dodGrowthPct), 1) }}</span>%
                                </span>
                                <span>({{ $isDodPositive ? '+৳ ' : '-৳ ' }}<span x-count="{{ abs((float) $dodDiff) }}">{{ number_format(abs((float) $dodDiff)) }}</span>)</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-muted">
                            <span>Today: ৳ <span x-count="{{ $todaySales }}">{{ number_format($todaySales) }}</span></span>
                            <span>Yesterday: ৳ <span x-count="{{ $yesterdaySales }}">{{ number_format($yesterdaySales) }}</span></span>
                        </div>
                    </div>

                    {{-- Run-Rate Pace or Outlet Distribution --}}
                    @if (! $isSuperAdmin && isset($requiredDailySales) && $remainingAmount > 0 && $daysRemaining > 0)
                    <div class="flex items-center justify-between rounded-xl bg-brand-500/5 px-3 py-2 border border-brand-500/15 text-xs">
                        <div class="flex items-center gap-1.5 text-brand-600 dark:text-brand-400 font-semibold">
                            <x-icon name="sparkles" class="size-3.5" />
                            <span>Required Pace:</span>
                        </div>
                        <span class="font-extrabold text-brand-600 dark:text-brand-400 tabular-nums">
                            ৳ <span x-count="{{ $requiredDailySales }}">{{ number_format($requiredDailySales) }}</span>/day ({{ $daysRemaining }}d left)
                        </span>
                    </div>
                    @elseif ($isSuperAdmin)
                    <div class="flex items-center justify-between rounded-xl bg-soft/30 px-3 py-2 border border-line/40 text-xs">
                        <span class="text-muted font-medium">Configured Outlets:</span>
                        <span class="font-bold text-ink tabular-nums">
                            {{ $configuredCount }} of {{ $totalCount }} active branches
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Daily Sales Activity Area Chart (TailAdmin Line Chart 5 Design) --}}
            @php
                $dailyData = ($isSuperAdmin && $networkOverview)
                    ? ($networkOverview['daily_sales'] ?? [])
                    : ($perf['daily_sales'] ?? []);
                $dailyDays = $dailyData['days'] ?? [];
                $maxDailyVal = (float) ($dailyData['max_sales'] ?? 0);
                $shortMonth = $activeDate->format('M');

                $trajectoryCategories = [];
                $trajectorySeriesData = [];
                foreach ($dailyDays as $d) {
                    $trajectoryCategories[] = $shortMonth . ' ' . str_pad((string) $d['day'], 2, '0', STR_PAD_LEFT);
                    $trajectorySeriesData[] = round((float) $d['sales'], 2);
                }

                $trajectoryConfig = [
                    'categories' => $trajectoryCategories,
                    'series' => [
                        [
                            'name' => 'Daily Sales',
                            'data' => $trajectorySeriesData,
                        ],
                    ],
                    'monthName' => $monthName,
                    'maxSales' => $maxDailyVal,
                ];
            @endphp

            @if (!empty($dailyDays))
                <div class="mt-5 pt-4 border-t border-line/60">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-ink">Daily Sales Trajectory</span>
                            <span class="text-[11px] text-muted font-medium">({{ $monthName }})</span>
                        </div>

                        <div class="text-[11px] font-semibold text-muted tabular-nums">
                            Peak Day: <strong class="text-ink font-bold">৳ {{ number_format($maxDailyVal) }}</strong>
                        </div>
                    </div>

                    {{-- ApexCharts Smooth Gradient Area Chart (Line Chart 5) --}}
                    <div class="w-full">
                        <div data-dashboard-chart="daily-sales-trajectory" data-config='@json($trajectoryConfig)'></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Card Footer --}}
        <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3 text-xs">
            <span class="text-muted truncate">
                @if ($isSuperAdmin)
                Network target tracking across all branches
                @else
                Pacing calculation based on posted sales
                @endif
            </span>
            <a href="{{ route('branch-targets.index') }}" class="font-semibold text-brand-500 hover:underline shrink-0 inline-flex items-center gap-1">
                <span>{{ $isSuperAdmin ? 'Manage All Targets' : 'View Breakdown' }}</span>
                <x-icon name="arrow-up-right" class="size-3" />
            </a>
        </div>

    </section>