<?php

declare(strict_types=1);

namespace App\Services;

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
        $monthDate = Carbon::createFromFormat('Y-m', $targetMonth);
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $targetRecord = BranchTarget::where('location_id', $locationId)
            ->where('target_month', $targetMonth)
            ->first();

        $targetAmount = $targetRecord ? (float) $targetRecord->target_amount : 0.0;

        // Month-to-date posted sales
        $monthSales = (float) SalesInvoice::query()
            ->where('location_id', $locationId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
            ->sum('grand_total');

        $achievementPct = $targetAmount > 0.0
            ? round(($monthSales / $targetAmount) * 100, 1)
            : 0.0;

        $remainingAmount = max(0.0, round($targetAmount - $monthSales, 2));

        // Today and Yesterday sales
        $todaySales = (float) SalesInvoice::query()
            ->where('location_id', $locationId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereDate('invoice_date', today())
            ->sum('grand_total');

        $yesterdaySales = (float) SalesInvoice::query()
            ->where('location_id', $locationId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereDate('invoice_date', today()->subDay())
            ->sum('grand_total');

        $dodDiffAmount = round($todaySales - $yesterdaySales, 2);
        if ($yesterdaySales > 0.0) {
            $dodGrowthPct = round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100, 1);
        } else {
            $dodGrowthPct = $todaySales > 0.0 ? 100.0 : 0.0;
        }

        // Calendar pacing calculations
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

        // Pacing status determination
        if ($targetAmount <= 0.0) {
            $status = 'not_set';
        } elseif ($monthSales >= $targetAmount) {
            $status = 'achieved';
        } else {
            $expectedPacingPct = ($daysPassed / $daysInMonth) * 100;
            $status = $achievementPct >= ($expectedPacingPct * 0.85) ? 'on_track' : 'behind';
        }

        return [
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
            'daily_sales' => self::getDailySalesForMonth($locationId, $targetMonth),
        ];
    }

    /**
     * Get network-wide consolidated target tracking overview for Super Admin.
     *
     * @return array<string, mixed>
     */
    public static function getNetworkOverview(?string $month = null): array
    {
        $targetMonth = $month ?? now()->format('Y-m');

        $locations = Location::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $branchPerformances = $locations->map(function (Location $loc) use ($targetMonth) {
            $perf = self::getBranchPerformance($loc->id, $targetMonth);
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
     * Get day-by-day sales for the target month (1 to 28/30/31).
     *
     * @return array<string, mixed>
     */
    public static function getDailySalesForMonth(?int $locationId = null, ?string $month = null): array
    {
        $targetMonth = $month ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('Y-m', $targetMonth);
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();
        $daysInMonth = (int) $monthDate->daysInMonth;
        $isCurrentMonth = $monthDate->isCurrentMonth();
        $todayDay = $isCurrentMonth ? (int) now()->day : -1;

        $query = SalesInvoice::query()
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $invoices = $query->get(['invoice_date', 'grand_total']);
        $salesByDay = [];
        foreach ($invoices as $inv) {
            if ($inv->invoice_date) {
                $d = (int) $inv->invoice_date->format('j');
                $salesByDay[$d] = ($salesByDay[$d] ?? 0.0) + (float) $inv->grand_total;
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
}
