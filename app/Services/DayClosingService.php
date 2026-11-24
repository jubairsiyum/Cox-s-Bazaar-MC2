<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountEntryStatus;
use App\Enums\LocationType;
use App\Enums\SalesInvoiceStatus;
use App\Exceptions\DayClosedException;
use App\Models\AccountTransfer;
use App\Models\DayClosing;
use App\Models\Expense;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DayClosingService
{
    /**
     * Check whether a specific date is closed for a given branch location.
     */
    public static function isDayClosed(int $locationId, CarbonInterface|string $date): bool
    {
        return DayClosing::isDateClosed($locationId, $date);
    }

    /**
     * Guard: throw an exception if the date has already been closed.
     * Prevents backdated sales, invoices, and expense tampering.
     *
     * @throws DayClosedException
     */
    public static function assertDayNotClosed(int $locationId, CarbonInterface|string $date): void
    {
        $dateString = is_string($date) ? $date : $date->toDateString();

        if (self::isDayClosed($locationId, $dateString)) {
            $locationName = Location::query()->whereKey($locationId)->value('name');
            throw DayClosedException::forDate($dateString, $locationName);
        }
    }

    /**
     * Compute the system cash figures for a branch on a given date.
     *
     * @return array{
     *     opening_balance: string,
     *     cash_sales: string,
     *     cash_collections: string,
     *     cash_expenses: string,
     *     cash_remittances: string,
     *     system_cash: string
     * }
     */
    public static function calculateSummary(int $locationId, CarbonInterface|string $date): array
    {
        $dateString = is_string($date) ? $date : $date->toDateString();

        // 1. Opening Balance: Previous day's counted cash or 0.00
        $previousClosing = DayClosing::query()
            ->where('location_id', $locationId)
            ->whereDate('closing_date', '<', $dateString)
            ->where('status', 'closed')
            ->orderByDesc('closing_date')
            ->first();

        $openingBalance = $previousClosing ? (string) $previousClosing->counted_cash : '0.00';

        // 2. Cash Sales on this date
        $cashSales = (string) SalesInvoice::query()
            ->where('location_id', $locationId)
            ->whereDate('invoice_date', $dateString)
            ->where('status', '!=', SalesInvoiceStatus::Voided)
            ->sum('paid_amount');

        $cashSales = MoneyMath::clean($cashSales);

        // 3. Cash Expenses on this date (if expense table exists)
        $cashExpenses = '0.00';
        if (class_exists(Expense::class)) {
            $expenseSum = Expense::query()
                ->where('location_id', $locationId)
                ->whereDate('expense_date', $dateString)
                ->sum('amount');
            $cashExpenses = MoneyMath::clean((string) $expenseSum);
        }

        // 4. Completed Cash Remittances from this branch to HQ on this date
        $cashRemittances = (string) AccountTransfer::query()
            ->where('location_id', $locationId)
            ->whereDate('transfer_date', $dateString)
            ->where('status', AccountEntryStatus::Completed)
            ->sum('amount');

        $cashRemittances = MoneyMath::clean($cashRemittances);

        // System expected cash = opening + sales - expenses - remittances
        $inflows = MoneyMath::add($openingBalance, $cashSales);
        $outflows = MoneyMath::add($cashExpenses, $cashRemittances);
        $systemCash = MoneyMath::max('0.00', MoneyMath::sub($inflows, $outflows));

        return [
            'opening_balance' => $openingBalance,
            'cash_sales' => $cashSales,
            'cash_collections' => '0.00',
            'cash_expenses' => $cashExpenses,
            'cash_remittances' => $cashRemittances,
            'system_cash' => $systemCash,
        ];
    }

    /**
     * Close the business day for a branch.
     */
    public static function closeDay(
        int $locationId,
        CarbonInterface|string $date,
        string|float $countedCash,
        User $user,
        ?string $note = null
    ): DayClosing {
        $dateString = is_string($date) ? $date : $date->toDateString();
        $counted = MoneyMath::clean((string) $countedCash);

        $summary = self::calculateSummary($locationId, $dateString);
        $systemCash = $summary['system_cash'];

        $variance = MoneyMath::sub($counted, $systemCash);
        $absVariance = (float) abs((float) $variance);

        $varianceStatus = match (true) {
            $absVariance == 0.0 => 'exact',
            $absVariance <= 100.0 => 'minor',
            default => 'significant',
        };

        return DB::transaction(function () use (
            $locationId,
            $dateString,
            $summary,
            $counted,
            $variance,
            $varianceStatus,
            $user,
            $note
        ) {
            return DayClosing::updateOrCreate(
                [
                    'location_id' => $locationId,
                    'closing_date' => $dateString,
                ],
                [
                    'opening_balance' => $summary['opening_balance'],
                    'cash_sales' => $summary['cash_sales'],
                    'cash_collections' => $summary['cash_collections'],
                    'cash_expenses' => $summary['cash_expenses'],
                    'cash_remittances' => $summary['cash_remittances'],
                    'system_cash' => $summary['system_cash'],
                    'counted_cash' => $counted,
                    'variance' => $variance,
                    'variance_status' => $varianceStatus,
                    'status' => 'closed',
                    'note' => $note,
                    'closed_by' => $user->id,
                    'reopened_by' => null,
                    'reopened_at' => null,
                    'reopen_reason' => null,
                ]
            );
        });
    }

    /**
     * Reopen a closed day (Super Admin only).
     */
    public static function reopenDay(DayClosing $closing, User $user, string $reason): void
    {
        $closing->update([
            'status' => 'reopened',
            'reopened_by' => $user->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ]);
    }

    /**
     * Compute the Payable to HQ summary for an outlet/branch based on daily cash close
     * and remittances transferred to HQ.
     *
     * @return array{
     *     cash_close_amount: float,
     *     transferred_to_hq: float,
     *     pending_transferred: float,
     *     left_amount: float,
     *     progress_pct: int,
     *     is_all_clear: bool,
     *     closing_date: ?string,
     *     caption: string
     * }
     */
    public static function getPayableToHqSummary(?int $locationId): array
    {
        if ($locationId === null) {
            return [
                'cash_close_amount' => 0.0,
                'transferred_to_hq' => 0.0,
                'pending_transferred' => 0.0,
                'left_amount' => 0.0,
                'progress_pct' => 100,
                'is_all_clear' => true,
                'closing_date' => null,
                'caption' => 'All clear · No branch assigned',
            ];
        }

        $today = now()->toDateString();

        // 1. Check if today's business day has been closed
        $todayClosing = DayClosing::query()
            ->where('location_id', $locationId)
            ->whereDate('closing_date', $today)
            ->where('status', 'closed')
            ->first();

        // 2. Fetch the latest closed day (today or most recent previous day)
        $latestClosing = $todayClosing ?? DayClosing::query()
            ->where('location_id', $locationId)
            ->where('status', 'closed')
            ->orderByDesc('closing_date')
            ->first();

        if ($latestClosing === null) {
            return [
                'cash_close_amount' => 0.0,
                'transferred_to_hq' => 0.0,
                'pending_transferred' => 0.0,
                'left_amount' => 0.0,
                'progress_pct' => 100,
                'is_all_clear' => true,
                'closing_date' => null,
                'caption' => 'All clear · No cash close recorded',
            ];
        }

        $closingDate = $latestClosing->closing_date->toDateString();
        $countedCash = (float) $latestClosing->counted_cash;
        $systemCash = (float) $latestClosing->system_cash;
        $baseCash = $countedCash > 0 ? $countedCash : $systemCash;
        $remittedBeforeClose = (float) $latestClosing->cash_remittances;
        $totalCashClose = round($baseCash + $remittedBeforeClose, 2);

        // Query transfers to Central HQ / warehouse / shared accounts on or after the closing date
        $transfersBaseQuery = AccountTransfer::query()
            ->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhereHas('fromAccount', fn ($fq) => $fq->where('location_id', $locationId));
            })
            ->whereDate('transfer_date', '>=', $closingDate)
            ->whereHas('toAccount', function ($tq) use ($locationId) {
                $tq->whereNull('location_id')
                    ->orWhere('location_id', '!=', $locationId)
                    ->orWhereHas('location', fn ($l) => $l->where('type', LocationType::Warehouse));
            });

        $completedTransfers = (float) (clone $transfersBaseQuery)
            ->where('status', AccountEntryStatus::Completed)
            ->sum('amount');

        $pendingTransfers = (float) (clone $transfersBaseQuery)
            ->where('status', AccountEntryStatus::Pending)
            ->sum('amount');

        $transferredToHq = round(max($completedTransfers, $remittedBeforeClose), 2);
        $leftAmount = max(0.0, round($totalCashClose - $transferredToHq, 2));

        $isAllClear = $totalCashClose <= 0 || $leftAmount <= 0.001;
        $progressPct = $isAllClear
            ? 100
            : (int) max(0, min(99, round(($transferredToHq / max(1.0, $totalCashClose)) * 100)));

        $formattedCloseDate = $latestClosing->closing_date->format('d M');
        $dateLabel = ($closingDate === $today) ? 'Today' : $formattedCloseDate;

        if ($isAllClear) {
            $caption = $totalCashClose > 0
                ? 'All clear · ৳'.number_format($transferredToHq).' remitted to HQ'
                : 'All clear · ৳0 payable to HQ';
        } else {
            $caption = '৳'.number_format($transferredToHq).' remitted of ৳'.number_format($totalCashClose).' ('.$dateLabel.')';
            if ($pendingTransfers > 0) {
                $caption .= ' · ৳'.number_format($pendingTransfers).' pending approval';
            }
        }

        return [
            'cash_close_amount' => $totalCashClose,
            'transferred_to_hq' => $transferredToHq,
            'pending_transferred' => $pendingTransfers,
            'left_amount' => $leftAmount,
            'progress_pct' => $progressPct,
            'is_all_clear' => $isAllClear,
            'closing_date' => $closingDate,
            'caption' => $caption,
        ];
    }

    /**
     * Compute the network-wide Payable/Receivable summary across all branch outlets for Super Admin.
     *
     * @return array{
     *     total_closings: float,
     *     total_sent_to_hq: float,
     *     total_receivable: float,
     *     progress_pct: int,
     *     is_all_clear: bool,
     *     branches_count: int,
     *     today_sent_to_hq: float,
     *     caption: string
     * }
     */
    public static function getNetworkPayableSummary(): array
    {
        $branchLocations = Location::query()
            ->where('status', 'active')
            ->where('type', '!=', LocationType::Warehouse)
            ->get(['id', 'name']);

        $totalClosings = 0.0;
        $totalSent = 0.0;
        $totalReceivable = 0.0;
        $today = now()->toDateString();
        $closedCount = 0;

        foreach ($branchLocations as $loc) {
            $summary = self::getPayableToHqSummary($loc->id);
            $totalClosings += $summary['cash_close_amount'];
            $totalSent += $summary['transferred_to_hq'];
            $totalReceivable += $summary['left_amount'];

            if (self::isDayClosed($loc->id, $today)) {
                $closedCount++;
            }
        }

        $todaySentToHq = (float) AccountTransfer::query()
            ->whereDate('transfer_date', $today)
            ->where('status', AccountEntryStatus::Completed)
            ->whereHas('toAccount', function ($tq) {
                $tq->whereNull('location_id')
                    ->orWhereHas('location', fn ($l) => $l->where('type', LocationType::Warehouse));
            })
            ->sum('amount');

        $isAllClear = $totalClosings <= 0 || $totalReceivable <= 0.001;
        $progressPct = $isAllClear
            ? 100
            : (int) max(0, min(99, round(($totalSent / max(1.0, $totalClosings)) * 100)));

        $unclosedCount = $branchLocations->count() - $closedCount;

        if ($isAllClear) {
            $caption = $totalClosings > 0
                ? 'All clear · ৳'.number_format($totalSent).' remitted across all branches'
                : 'All clear · ৳0 receivable from branches';
        } else {
            $caption = '৳'.number_format($totalSent).' remitted of ৳'.number_format($totalClosings);
            if ($unclosedCount > 0) {
                $caption .= ' · '.$unclosedCount.' '.Str::plural('branch', $unclosedCount).' pending close';
            }
        }

        return [
            'total_closings' => round($totalClosings, 2),
            'total_closing_amount' => round($totalClosings, 2),
            'total_sent_to_hq' => round($totalSent, 2),
            'total_receivable' => round($totalReceivable, 2),
            'progress_pct' => $progressPct,
            'is_all_clear' => $isAllClear,
            'branches_count' => $branchLocations->count(),
            'closed_branches_count' => $closedCount,
            'unclosed_branches_count' => $unclosedCount,
            'today_sent_to_hq' => round($todaySentToHq, 2),
            'caption' => $caption,
        ];
    }

    /**
     * Get branch daily closing compliance report for a specific date (defaults to today).
     * Lists every active retail branch with their status (Closed vs Pending Closing),
     * today's cash sales, counted cash, and remitted amount.
     *
     * @return array{
     *     date: string,
     *     total_branches: int,
     *     closed_count: int,
     *     unclosed_count: int,
     *     compliance_pct: int,
     *     branches: array<int, array{
     *         location_id: int,
     *         name: string,
     *         code: ?string,
     *         type: string,
     *         is_closed: bool,
     *         closing: ?DayClosing,
     *         today_sales: float,
     *         today_cash_sales: float,
     *         counted_cash: float,
     *         remitted_amount: float,
     *         status_label: string
     *     }>
     * }
     */
    public static function getTodayBranchCompliance(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $branches = Location::query()
            ->where('status', 'active')
            ->where('type', '!=', LocationType::Warehouse)
            ->orderBy('name')
            ->get();

        $records = [];
        $closedCount = 0;

        foreach ($branches as $branch) {
            $closing = DayClosing::query()
                ->with(['closedBy'])
                ->where('location_id', $branch->id)
                ->whereDate('closing_date', $date)
                ->where('status', 'closed')
                ->first();

            $isClosed = $closing !== null;
            if ($isClosed) {
                $closedCount++;
            }

            // Today's posted sales at this branch
            $todaySales = (float) SalesInvoice::query()
                ->where('location_id', $branch->id)
                ->whereDate('invoice_date', $date)
                ->where('status', SalesInvoiceStatus::Posted)
                ->sum('grand_total');

            // Today's cash collected on sales
            $todayPaid = (float) SalesInvoice::query()
                ->where('location_id', $branch->id)
                ->whereDate('invoice_date', $date)
                ->where('status', '!=', SalesInvoiceStatus::Voided)
                ->sum('paid_amount');

            // Completed remittances from this branch to HQ
            $remitted = (float) AccountTransfer::query()
                ->where('location_id', $branch->id)
                ->whereDate('transfer_date', $date)
                ->where('status', AccountEntryStatus::Completed)
                ->whereHas('toAccount', function ($tq) {
                    $tq->whereNull('location_id')
                        ->orWhereHas('location', fn ($l) => $l->where('type', LocationType::Warehouse));
                })
                ->sum('amount');

            $records[] = [
                'location_id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'type' => $branch->type?->value ?? (string) $branch->type,
                'is_closed' => $isClosed,
                'closing' => $closing,
                'today_sales' => round($todaySales, 2),
                'today_cash_sales' => round($todayPaid, 2),
                'counted_cash' => $isClosed ? (float) $closing->counted_cash : 0.0,
                'remitted_amount' => round($remitted, 2),
                'status_label' => $isClosed ? 'Closed & Locked' : 'Pending Closing',
            ];
        }

        $total = $branches->count();
        $unclosedCount = $total - $closedCount;
        $compliancePct = $total > 0 ? (int) round(($closedCount / $total) * 100) : 100;

        return [
            'date' => $date,
            'total_branches' => $total,
            'closed_count' => $closedCount,
            'unclosed_count' => $unclosedCount,
            'compliance_pct' => $compliancePct,
            'branches' => $records,
        ];
    }
}
