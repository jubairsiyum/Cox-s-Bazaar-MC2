<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesInvoiceStatus;
use App\Models\Location;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every section of a branch's operational profile.
 *
 * Aggregates the stock ledger, transfers, sales, returns, staff and recent
 * activity for a single {@see Location} (branch) into the shape rendered by
 * `resources/views/branches/show.blade.php`. Reads only, and already scoped
 * to the branch by the controller (see BranchController::show), so the
 * figures here are exactly that branch's rows.
 */
class BranchProfileData
{
    /**
     * How many recent sales invoices and stock movements the page lists.
     */
    protected const RECENT_LIMIT = 10;

    protected const ACTIVITY_LIMIT = 20;

    /**
     * Build the branch profile payload.
     *
     * @return array{
     *     summary: array<string, float|int>,
     *     stock: Collection<int, array<string, mixed>>,
     *     incomingTransfers: Collection<int, StockTransfer>,
     *     outgoingTransfers: Collection<int, StockTransfer>,
     *     salesByDay: Collection<int, array<string, mixed>>,
     *     recentInvoices: Collection<int, SalesInvoice>,
     *     salesReturns: Collection<int, SalesReturn>,
     *     purchaseReturns: Collection<int, PurchaseReturn>,
     *     staff: Collection<int, User>,
     *     movements: Collection<int, StockMovement>
     * }
     */
    public static function for(Location $location, ?User $viewer): array
    {
        return (new self($location, $viewer))->build();
    }

    private function __construct(
        private readonly Location $location,
        private readonly ?User $viewer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $stock = $this->stockRows();
        $incoming = $this->transfers('incoming');
        $outgoing = $this->transfers('outgoing');
        $postedInvoices = $this->postedInvoices();
        $salesReturns = $this->salesReturns();
        $purchaseReturns = $this->purchaseReturns();

        return [
            'summary' => $this->summary($stock, $incoming, $outgoing, $postedInvoices, $salesReturns),
            'stock' => $stock,
            'incomingTransfers' => $incoming,
            'outgoingTransfers' => $outgoing,
            'salesByDay' => $this->salesByDay($postedInvoices),
            'recentInvoices' => $postedInvoices->take(self::RECENT_LIMIT)->values(),
            'salesReturns' => $salesReturns,
            'purchaseReturns' => $purchaseReturns,
            'staff' => $this->location->users()->with('roles')->orderBy('name')->get(),
            'movements' => $this->movements(),
        ];
    }

    /**
     * Products (units) with stock at this branch, derived from the ledger.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stockRows(): Collection
    {
        $quantities = array_filter(
            StockService::quantitiesByUnitAt($this->location->id),
            fn (int $quantity): bool => $quantity > 0
        );

        if ($quantities === []) {
            return collect();
        }

        return Unit::query()
            ->with('product')
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->map(fn (Unit $unit): array => [
                'unit' => $unit,
                'product_name' => $unit->product?->name ?? 'Unknown product',
                'variant' => collect([$unit->product?->storage, $unit->product?->color])
                    ->filter()
                    ->implode(' / '),
                'unit_of_measure' => $unit->unit,
                'quantity' => $quantities[$unit->id] ?? 0,
            ])
            ->values();
    }

    /**
     * Transfers where this branch is the destination (incoming) or the source
     * (outgoing), newest first.
     *
     * @return Collection<int, StockTransfer>
     */
    private function transfers(string $direction): Collection
    {
        $relation = $direction === 'incoming' ? 'incomingTransfers' : 'outgoingTransfers';

        return $this->location->{$relation}()
            ->with(['fromLocation', 'toLocation', 'creator'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Posted sales invoices at this branch, newest first.
     *
     * @return Collection<int, SalesInvoice>
     */
    private function postedInvoices(): Collection
    {
        return $this->location->salesInvoices()
            ->with(['customer'])
            ->where('status', SalesInvoiceStatus::Posted)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, SalesReturn>
     */
    private function salesReturns(): Collection
    {
        return $this->location->salesReturns()
            ->with(['salesInvoice', 'customer'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, PurchaseReturn>
     */
    private function purchaseReturns(): Collection
    {
        return $this->location->purchaseReturns()
            ->with(['supplier', 'purchaseOrder'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Posted sales grouped by day, newest day first.
     *
     * @param  Collection<int, SalesInvoice>  $invoices
     * @return Collection<int, array{date: Carbon, count: int, total: float}>
     */
    private function salesByDay(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (SalesInvoice $invoice): string => $invoice->invoice_date->toDateString())
            ->map(fn (Collection $day): array => [
                'date' => $day->first()->invoice_date,
                'count' => $day->count(),
                'total' => round((float) $day->sum('grand_total'), 2),
            ])
            ->sortByDesc(fn (array $row): string => $row['date']->toDateString())
            ->values();
    }

    /**
     * Latest ledger entries recorded at this branch.
     *
     * @return Collection<int, StockMovement>
     */
    private function movements(): Collection
    {
        return $this->location->stockMovements()
            ->with(['unit.product', 'creator'])
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();
    }

    /**
     * Headline numbers for the summary cards.
     *
     * @param  Collection<int, array<string, mixed>>  $stock
     * @param  Collection<int, StockTransfer>  $incoming
     * @param  Collection<int, StockTransfer>  $outgoing
     * @param  Collection<int, SalesInvoice>  $postedInvoices
     * @param  Collection<int, SalesReturn>  $salesReturns
     * @return array<string, float|int>
     */
    private function summary(
        Collection $stock,
        Collection $incoming,
        Collection $outgoing,
        Collection $postedInvoices,
        Collection $salesReturns,
    ): array {
        return [
            'product_count' => $stock->count(),
            'total_quantity' => (int) $stock->sum('quantity'),
            'incoming_transfers' => $incoming->count(),
            'outgoing_transfers' => $outgoing->count(),
            'total_sales' => round((float) $postedInvoices->sum('grand_total'), 2),
            'sales_returns_count' => $salesReturns->count(),
            'sales_returns_value' => round((float) $salesReturns->sum('refund_amount'), 2),
        ];
    }
}
