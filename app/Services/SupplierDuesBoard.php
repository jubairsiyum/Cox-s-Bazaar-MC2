<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Helpers\Money;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Branch-wise dues board of a supplier: which branches (PO locations) owe
 * how much, which have cleared, and the purchase-level detail behind every
 * row. Powers the supplier profile and the "branches cleared / still owing"
 * summary attached to every supplier feed notification.
 */
class SupplierDuesBoard
{
    /**
     * Per-branch breakdown, owing branches first, each row carrying its
     * purchase orders (with their responsible managers and payments loaded).
     *
     * @return Collection<int, array{location_id: int, location_name: string, branch_name: string, grand_total: float, paid_amount: float, due: float, cleared: bool, orders: Collection<int, PurchaseOrder>}>
     */
    public static function branches(Supplier $supplier): Collection
    {
        $orders = self::orders($supplier);

        return $orders
            ->groupBy('location_id')
            ->map(function (Collection $branchOrders, int $locationId): array {
                $grandTotal = round((float) $branchOrders->sum('grand_total'), 2);
                $paid = round((float) $branchOrders->sum('paid_amount'), 2);
                $due = round(max(0, $grandTotal - $paid), 2);

                /** @var PurchaseOrder $first */
                $first = $branchOrders->first();

                return [
                    'location_id' => $locationId,
                    'location_name' => $first->location?->name ?? 'Unknown location',
                    'branch_name' => $first->location?->branch?->name ?? '—',
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paid,
                    'due' => $due,
                    'cleared' => $due <= 0,
                    'orders' => $branchOrders->values(),
                ];
            })
            ->sortBy([['due', 'desc'], ['location_name', 'asc']])
            ->values();
    }

    /**
     * Every live (non-cancelled) purchase order of the supplier with its
     * responsible manager, location and partial payments loaded.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public static function orders(Supplier $supplier): Collection
    {
        return $supplier->purchaseOrders()
            ->with(['location.branch', 'creator', 'payments'])
            ->where('purchase_status', '!=', PurchaseStatus::Cancelled->value)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * "Branches with dues cleared: X, Y — still owing: Z (৳n)." summary built
     * from the board, attached to every payment notification so the supplier
     * always sees which branches cleared and which did not.
     */
    public static function paymentSummary(Supplier $supplier): string
    {
        $cleared = collect();
        $owing = collect();
        $owingTotal = 0.0;

        foreach (self::branches($supplier) as $branch) {
            if ($branch['cleared']) {
                $cleared->push($branch['location_name']);
            } else {
                $owing->push($branch['location_name']);
                $owingTotal += $branch['due'];
            }
        }

        return sprintf(
            'Branches with dues cleared: %s — still owing: %s.',
            $cleared->isNotEmpty() ? $cleared->implode(', ') : 'none',
            $owing->isNotEmpty()
                ? $owing->implode(', ').' ('.Money::format($owingTotal).')'
                : 'none'
        );
    }
}
