<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LocationType;
use App\Enums\SalesInvoiceStatus;
use App\Models\BranchTarget;
use App\Models\Location;
use App\Models\SalesInvoice;
use Carbon\Carbon;

class TargetService
{
    /**
     * Compute comprehensive monthly target and Day-over-Day performance metrics for a specific branch.
     *
     * @return array<string, mixed>
     */
    public static function getBranchPerformance(int $locationId, ?string $month = null): array
    {
        $targetMonth = $month ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('!Y-m', $targetMonth);
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();
        $yesterdayStart = today()->subDay()->startOfDay();
        $yesterdayEnd = today()->subDay()->endOfDay();

        $rangeStart = $startOfMonth->lt($yesterdayStart) ? $startOfMonth : $yesterdayStart;
        $rangeEnd = $endOfMonth->gt($todayEnd) ? $endOfMonth : $todayEnd;

        $targetRecord = BranchTarget::where('location_id', $locationId)
            ->where('target_month', $targetMonth)
            ->first();

        $salesAgg = SalesInvoice::query()
            ->where('location_id', $locationId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereBetween('invoice_date', [$rangeStart->toDateTimeString(), $rangeEnd->toDateTimeString()])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as month_sales,
                 COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as today_sales,
                 COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as yesterday_sales',
                [
                    $startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString(),
                    $todayStart->toDateTimeString(), $todayEnd->toDateTimeString(),
                    $yesterdayStart->toDateTimeString(), $yesterdayEnd->toDateTimeString(),
                ]
            )
            ->first();

        return self::buildBranchPerformancePayload(
            locationId: $locationId,
            targetMonth: $targetMonth,
            monthDate: $monthDate,
            targetRecord: $targetRecord,
            monthSales: (float) ($salesAgg->month_sales ?? 0),
            todaySales: (float) ($salesAgg->today_sales ?? 0),
            yesterdaySales: (float) ($salesAgg->yesterday_sales ?? 0),
            dailySales: self::getDailySalesForMonth($locationId, $targetMonth),
        );
    }

    /**
     * Get network-wide consolidated target tracking overview for Super Admin
     * using aggregated GROUP BY queries across all branches.
     *
     * @return array<string, mixed>
     */
    public static function getNetworkOverview(?string $month = null): array
    {
        $targetMonth = $month ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('!Y-m', $targetMonth);
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $todayStart = today()->startOfDay();
        $todayEnd = today()->endOfDay();
        $yesterdayStart = today()->subDay()->startOfDay();
        $yesterdayEnd = today()->subDay()->endOfDay();

        $rangeStart = $startOfMonth->lt($yesterdayStart) ? $startOfMonth : $yesterdayStart;
        $rangeEnd = $endOfMonth->gt($todayEnd) ? $endOfMonth : $todayEnd;

        $locations = Location::query()
            ->where('status', 'active')
            ->where('type', '!=', LocationType::Warehouse)
            ->orderBy('name')
            ->get();

        $locationIds = $locations->pluck('id')->all();

        $targetsByLocation = $locationIds === []
            ? collect()
            : BranchTarget::query()
                ->where('target_month', $targetMonth)
                ->whereIn('location_id', $locationIds)
                ->get()
                ->keyBy('location_id');

        $salesByLocation = $locationIds === []
            ? collect()
            : SalesInvoice::query()
                ->whereIn('location_id', $locationIds)
                ->where('status', SalesInvoiceStatus::Posted)
                ->whereBetween('invoice_date', [$rangeStart->toDateTimeString(), $rangeEnd->toDateTimeString()])
                ->groupBy('location_id')
                ->selectRaw(
                    'location_id,
                     COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as month_sales,
                     COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as today_sales,
                     COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN grand_total ELSE 0 END), 0) as yesterday_sales',
                    [
                        $startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString(),
                        $todayStart->toDateTimeString(), $todayEnd->toDateTimeString(),
                        $yesterdayStart->toDateTimeString(), $yesterdayEnd->toDateTimeString(),
                    ]
                )
                ->get()
                ->keyBy('location_id');

        $branchPerformances = $locations->map(function (Location $loc) use ($targetMonth, $monthDate, $targetsByLocation, $salesByLocation): array {
            $salesRow = $salesByLocation->get($loc->id);
            $perf = self::buildBranchPerformancePayload(
                locationId: $loc->id,
                targetMonth: $targetMonth,
                monthDate: $monthDate,
                targetRecord: $targetsByLocation->get($loc->id),
                monthSales: (float) ($salesRow->month_sales ?? 0),
                todaySales: (float) ($salesRow->today_sales ?? 0),
                yesterdaySales: (float) ($salesRow->yesterday_sales ?? 0),
            );
            $perf['location'] = $loc;

            return $perf;
        });

        $totalNetworkTarget = (float) $branchPerformances->sum('target_amount');
        $totalNetworkSales = (float) $branchPerformances->sum('month_sales');
        $totalNetworkTodaySales = (float) $branchPerformances->sum('today_sales');
        $totalNetworkYesterdaySales = (float) $branchPerformances->sum('yesterday_sales');

        $networkAchievementPct = $totalNetworkTarget > 0.0
            ? round(($totalNetworkSales / $totalNetworkTarget) * 100, 1)
            : 0.0;

        $networkRemaining = max(0.0, round($totalNetworkTarget - $totalNetworkSales, 2));

        if ($totalNetworkYesterdaySales > 0.0) {
            $networkDodGrowthPct = round((($totalNetworkTodaySales - $totalNetworkYesterdaySales) / $totalNetworkYesterdaySales) * 100, 1);
        } else {
            $networkDodGrowthPct = $totalNetworkTodaySales > 0.0 ? 100.0 : 0.0;
        }

        return [
            'target_month' => $targetMonth,
            'branches' => $branchPerformances,
            'total_target' => $totalNetworkTarget,
            'total_sales' => $totalNetworkSales,
            'total_today_sales' => $totalNetworkTodaySales,
            'total_yesterday_sales' => $totalNetworkYesterdaySales,
            'achievement_pct' => $networkAchievementPct,
            'remaining_amount' => $networkRemaining,
            'dod_growth_pct' => $networkDodGrowthPct,
            'achieved_branches_count' => $branchPerformances->where('status', 'achieved')->count(),
            'on_track_branches_count' => $branchPerformances->where('status', 'on_track')->count(),
            'behind_branches_count' => $branchPerformances->where('status', 'behind')->count(),
            'unassigned_targets_count' => $branchPerformances->where('status', 'not_set')->count(),
            'daily_sales' => self::getDailySalesForMonth(null, $targetMonth),
        ];
    }

    /**
     * Get day-by-day sales for the target month (1 to 28/30/31) using SQL GROUP BY.
     *
     * @return array<string, mixed>
     */
    public static function getDailySalesForMonth(?int $locationId = null, ?string $month = null): array
    {
        $targetMonth = $month ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('!Y-m', $targetMonth);
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();
        $daysInMonth = (int) $monthDate->daysInMonth;
        $isCurrentMonth = $monthDate->isCurrentMonth();
        $todayDay = $isCurrentMonth ? (int) now()->day : -1;

        $query = SalesInvoice::query()
            ->where('sales_invoices.status', SalesInvoiceStatus::Posted)
            ->whereBetween('sales_invoices.invoice_date', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);

        if ($locationId !== null) {
            $query->where('sales_invoices.location_id', $locationId);
        } else {
            $query->join('locations', 'locations.id', '=', 'sales_invoices.location_id')
                ->whereNull('locations.deleted_at')
                ->where('locations.type', '!=', LocationType::Warehouse->value);
        }

        $dailyRows = $query
            ->selectRaw('DATE(sales_invoices.invoice_date) as sale_day, COALESCE(SUM(sales_invoices.grand_total), 0) as total_sales')
            ->groupBy('sale_day')
            ->get();

        $salesByDay = [];
        foreach ($dailyRows as $row) {
            if ($row->sale_day) {
                $d = (int) Carbon::parse((string) $row->sale_day)->day;
                $salesByDay[$d] = ($salesByDay[$d] ?? 0.0) + (float) $row->total_sales;
            }
        }

        $days = [];
        $maxSales = 0.0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $sales = (float) ($salesByDay[$d] ?? 0.0);
            if ($sales > $maxSales) {
                $maxSales = $sales;
            }
            $isToday = $isCurrentMonth && ($d === $todayDay);
            $isFuture = $isCurrentMonth && ($d > $todayDay);

            $days[] = [
                'day' => $d,
                'sales' => $sales,
                'is_today' => $isToday,
                'is_future' => $isFuture,
            ];
        }

        return [
            'days' => $days,
            'max_sales' => $maxSales,
            'days_in_month' => $daysInMonth,
            'days_passed' => $isCurrentMonth ? min($todayDay, $daysInMonth) : $daysInMonth,
        ];
    }

    /**
     * Build the normalized target pacing payload from aggregated figures.
     *
     * @param  array<string, mixed>|null  $dailySales
     * @return array<string, mixed>
     */
    private static function buildBranchPerformancePayload(
        int $locationId,
        string $targetMonth,
        Carbon $monthDate,
        ?BranchTarget $targetRecord,
        float $monthSales,
        float $todaySales,
        float $yesterdaySales,
        ?array $dailySales = null,
    ): array {
        $targetAmount = $targetRecord ? (float) $targetRecord->target_amount : 0.0;

        $achievementPct = $targetAmount > 0.0
            ? round(($monthSales / $targetAmount) * 100, 1)
            : 0.0;

        $remainingAmount = max(0.0, round($targetAmount - $monthSales, 2));

        $dodDiffAmount = round($todaySales - $yesterdaySales, 2);
        if ($yesterdaySales > 0.0) {
            $dodGrowthPct = round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1);
        } else {
            $dodGrowthPct = $todaySales > 0.0 ? 100.0 : 0.0;
        }

        $daysInMonth = (int) $monthDate->daysInMonth;
        $isCurrentMonth = $monthDate->isCurrentMonth();
        $daysPassed = $isCurrentMonth ? min(now()->day, $daysInMonth) : $daysInMonth;
        $daysRemaining = $isCurrentMonth ? max(0, $daysInMonth - now()->day) : 0;

        $requiredDailySales = $daysRemaining > 0
            ? round($remainingAmount / $daysRemaining, 2)
            : 0.0;

        $currentDailyAverage = $daysPassed > 0
            ? round($monthSales / $daysPassed, 2)
            : 0.0;

        if ($targetAmount <= 0.0) {
            $status = 'not_set';
        } elseif ($monthSales >= $targetAmount) {
            $status = 'achieved';
        } else {
            $expectedPacingPct = ($daysPassed / $daysInMonth) * 100;
            $status = $achievementPct >= ($expectedPacingPct * 0.85) ? 'on_track' : 'behind';
        }

        $payload = [
            'location_id' => $locationId,
            'target_month' => $targetMonth,
            'target_record' => $targetRecord,
            'target_amount' => $targetAmount,
            'month_sales' => $monthSales,
            'achievement_pct' => $achievementPct,
            'remaining_amount' => $remainingAmount,
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'dod_growth_pct' => $dodGrowthPct,
            'dod_diff_amount' => $dodDiffAmount,
            'days_in_month' => $daysInMonth,
            'days_passed' => $daysPassed,
            'days_remaining' => $daysRemaining,
            'required_daily_sales' => $requiredDailySales,
            'current_daily_average' => $currentDailyAverage,
            'status' => $status,
            'is_current_month' => $isCurrentMonth,
        ];

        if ($dailySales !== null) {
            $payload['daily_sales'] = $dailySales;
        }

        return $payload;
    }
}
