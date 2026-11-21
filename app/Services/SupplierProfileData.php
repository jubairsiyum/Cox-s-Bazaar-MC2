<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Helpers\Money;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierOpeningPayment;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Every section of the supplier profile, scoped to the viewer.
 *
 * A supplier login sees all of its own rows across branches. Staff with
 * cross-branch access (Super Admin) see all branches too. Branch-restricted
 * staff see only their own branch's orders, dues, payments, returns, requests
 * and feed entries, and never the supplier-level ledger (opening, purchase due
 * and return due balances, supplier-level payments), since those are not
 * stamped with a branch and would expose other branches' dues.
 *
 * @phpstan-type BranchRow array{location_id: int, location_name: string, branch_name: string, grand_total: float, paid_amount: float, due: float, cleared: bool, orders: Collection<int, PurchaseOrder>, returned: float}
 * @phpstan-type OrderRow array{order: PurchaseOrder, branch_status: array{0: string, 1: string}, supplier_status: array{0: string, 1: string}, lines: Collection<int, array<string, mixed>>, quantity: int, subtotal: float, tax_amount: float, grand_total: float, paid: float, due: float, cancelled: bool, deleted: bool, delivered: bool, awaiting_acceptance: bool}
 */
class SupplierProfileData
{
    /**
     * Number of feed entries shown on the profile.
     */
    protected const FEED_LIMIT = 50;

    /**
     * The supplier portal's sections, one page each: label, description and the partial that renders it.
     *
     * @var array<string, array{label: string, description: string, partial: string}>
     */
    public const SECTIONS = [
        'details' => ['label' => 'Overview', 'description' => 'Your supplier summary, identity, contact details, address and ledger balances.', 'partial' => 'details'],
        'dues' => ['label' => 'Branch Dues', 'description' => 'Which branches owe you money and which have cleared. Expand a branch for its order-level breakdown.', 'partial' => 'dues'],
        'orders' => ['label' => 'Purchase Orders', 'description' => 'Every purchase order placed with you, with its lines, payments and status.', 'partial' => 'orders'],
        'payments' => ['label' => 'Payments', 'description' => 'Payments received against invoices and supplier-level dues.', 'partial' => 'payments'],
        'returns' => ['label' => 'Returns', 'description' => 'Goods returned to you, with their value and the return due they add once approved.', 'partial' => 'returns'],
        'requests' => ['label' => 'Payment Requests', 'description' => 'Ask a branch that owes you money to settle its dues, and track the requests you have sent.', 'partial' => 'payment-requests'],
        'activity' => ['label' => 'Activity', 'description' => 'Notifications and activity recorded against your supplier profile.', 'partial' => 'feed'],
    ];

    private readonly bool $restricted;

    /**
     * @var Collection<int, PurchaseReturn>|null
     */
    private ?Collection $returnRecords = null;

    private function __construct(private readonly Supplier $supplier, private readonly User $viewer)
    {
        $ownsSupplier = $supplier->user_id !== null && (int) $supplier->user_id === (int) $viewer->id;

        $this->restricted = ! $ownsSupplier && BranchScope::isRestricted($viewer);
    }

    /**
     * Profile data for the whole profile (staff page) or, with a section key,
     * only the data that section's page renders.
     *
     * @return array<string, mixed>
     */
    public static function for(Supplier $supplier, User $viewer, ?string $section = null): array
    {
        return (new self($supplier, $viewer))->build($section);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(?string $section): array
    {
        $wants = fn (string ...$sections): bool => $section === null || in_array($section, $sections, true);

        $branches = $wants('details', 'dues', 'requests') ? $this->dueBranches() : collect();
        $orders = $wants('details', 'orders') ? $this->orderRows() : collect();
        $returns = $wants('details', 'returns') ? $this->returnSection() : [];

        $data = [];

        if ($wants('details')) {
            $data['details'] = $this->details();
            $data['summary'] = $this->summary($branches, $orders, $returns);
        }

        if ($wants('dues')) {
            $data['branches'] = $branches;
        }

        if ($wants('orders')) {
            $data['orders'] = $orders;
        }

        if ($wants('payments')) {
            $data['payments'] = $this->paymentSection();
        }

        if ($wants('returns')) {
            $data['returns'] = $returns;
        }

        if ($wants('requests')) {
            $data['paymentRequests'] = $this->paymentRequests();
            $data['requestBranches'] = $this->requestBranches($branches);
        }

        if ($wants('activity')) {
            $data['feed'] = $this->feed();
        }

        return $data;
    }

    /**
     * Supplier identity, contact and address details, plus the supplier ledger
     * balances for viewers allowed to see the whole supplier.
     *
     * @return array{identity: list<array{label: string, value: ?string}>, contact: list<array{label: string, value: ?string}>, address: ?string, ledger: list<array{label: string, value: string}>|null}
     */
    private function details(): array
    {
        $supplier = $this->supplier;

        return [
            'identity' => [
                ['label' => 'Supplier Code', 'value' => $supplier->supplier_id],
                ['label' => 'Name', 'value' => $supplier->name],
                ['label' => 'Tax / TIN', 'value' => $supplier->tax_number],
            ],
            'contact' => [
                ['label' => 'Mobile', 'value' => $supplier->mobile],
                ['label' => 'Telephone', 'value' => $supplier->tel_no],
                ['label' => 'Email', 'value' => $supplier->email],
            ],
            'address' => collect([$supplier->address, $supplier->city, $supplier->state, $supplier->postcode, $supplier->country])
                ->filter()
                ->implode(', ') ?: null,
            'ledger' => $this->restricted ? null : [
                ['label' => 'Opening Balance', 'value' => Money::format($supplier->opening_balance)],
                ['label' => 'Purchase Due (ledger)', 'value' => Money::format($supplier->purchase_due)],
                ['label' => 'Purchase Return Due (ledger)', 'value' => Money::format($supplier->purchase_return_due)],
            ],
        ];
    }

    /**
     * Branch-wise dues: the dues board (live orders only) narrowed to the
     * viewer, each branch carrying the approved value returned to it.
     *
     * @return Collection<int, BranchRow>
     */
    private function dueBranches(): Collection
    {
        $returnedByLocation = $this->returnRecords()
            ->filter(fn (PurchaseReturn $return): bool => $return->approval_status === ApprovalStatus::Approved)
            ->groupBy('location_id')
            ->map(fn (Collection $returns): float => round((float) $returns->sum('grand_total'), 2));

        return SupplierDuesBoard::branches($this->supplier)
            ->when($this->restricted, fn (Collection $rows) => $rows
                ->filter(fn (array $row): bool => BranchScope::canAccess($this->viewer, $row['location_id']))
                ->values())
            ->map(fn (array $row): array => $row + [
                'returned' => (float) $returnedByLocation->get((int) $row['location_id'], 0.0),
            ])
            ->values();
    }

    /**
     * Every purchase order of the supplier, cancelled ones included (listed as
     * cancelled, but excluded from dues), newest first.
     *
     * @return Collection<int, OrderRow>
     */
    private function orderRows(): Collection
    {
        return PurchaseOrder::query()
            ->withTrashed()
            ->where('supplier_id', $this->supplier->id)
            ->when($this->restricted, fn (Builder $orders) => BranchScope::scopeQuery($orders, $this->viewer))
            ->with(['location.branch', 'creator', 'items.unit.product', 'payments'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseOrder $order): array => $this->orderRow($order))
            ->values();
    }

    /**
     * @return OrderRow
     */
    private function orderRow(PurchaseOrder $order): array
    {
        $cancelled = $order->purchase_status === PurchaseStatus::Cancelled;
        $grandTotal = round((float) $order->grand_total, 2);
        $paid = round((float) $order->paid_amount, 2);

        return [
            'order' => $order,
            'branch_status' => $order->managerDisplayStatus(),
            'supplier_status' => $order->supplierDisplayStatus(),
            'lines' => $order->items->map(fn (PurchaseOrderItem $item): array => $this->lineRow($item))->values(),
            'quantity' => (int) $order->items->sum('quantity'),
            'subtotal' => round((float) $order->subtotal, 2),
            'tax_amount' => round((float) $order->tax_amount, 2),
            'grand_total' => $grandTotal,
            'paid' => $paid,
            'due' => round(max(0, $grandTotal - $paid), 2),
            'cancelled' => $cancelled,
            'deleted' => $order->trashed(),
            'delivered' => $order->supplier_status === SupplierOrderStatus::Delivered,
            'awaiting_acceptance' => ! $cancelled && $order->supplier_status === SupplierOrderStatus::Pending,
        ];
    }

    /**
     * One purchase line. Purchase lines carry no discount column, so the
     * discount shown is the gap between quantity x rate and the stored subtotal.
     *
     * @return array{product: string, quantity: int, rate: float, discount: float, subtotal: float}
     */
    private function lineRow(PurchaseOrderItem $item): array
    {
        $gross = round((float) $item->quantity * (float) $item->unit_price, 2);
        $subtotal = round((float) $item->subtotal, 2);

        return [
            'product' => $item->unit?->displayName() ?? 'Unknown product',
            'quantity' => (int) $item->quantity,
            'rate' => round((float) $item->unit_price, 2),
            'discount' => round(max(0, $gross - $subtotal), 2),
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Every payment made against the supplier, newest first: invoice payments
     * (with their branch and order) and, for viewers allowed the whole
     * supplier, supplier-level due and return-due payments.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, invoice_total: float, supplier_total: float, total: float}
     */
    private function paymentSection(): array
    {
        $invoicePayments = PurchasePayment::query()
            ->whereHas('purchaseOrder', fn (Builder $orders) => $orders
                ->where('supplier_id', $this->supplier->id)
                ->when($this->restricted, fn (Builder $scoped) => BranchScope::scopeQuery($scoped, $this->viewer)))
            ->with(['purchaseOrder.location', 'creator'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchasePayment $payment): array => [
                'kind' => 'Invoice payment',
                'date' => $payment->payment_date ?? $payment->created_at,
                'recorded_at' => $payment->created_at,
                'amount' => round((float) $payment->amount, 2),
                'method' => $payment->methodLabel() ?? 'Unspecified method',
                'reference' => $payment->note,
                'branch' => $payment->purchaseOrder?->location?->name ?? '—',
                'order' => $payment->purchaseOrder?->reference_no ?? '—',
                'recorded_by' => $payment->creator?->name ?? 'Unknown user',
                'status_key' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'counted' => $payment->status === ApprovalStatus::Approved,
            ]);

        $supplierPayments = $this->restricted
            ? collect()
            : $this->supplier->openingPayments()
                ->with('creator')
                ->orderByDesc('id')
                ->get()
                ->map(fn (SupplierOpeningPayment $payment): array => [
                    'kind' => str_contains(strtolower((string) $payment->payment_note), 'return')
                        ? 'Return due payment'
                        : 'Supplier due payment',
                    'date' => $payment->payment_date,
                    'recorded_at' => $payment->created_at,
                    'amount' => round((float) $payment->amount, 2),
                    'method' => self::paymentTypeLabel($payment->payment_type),
                    'reference' => $payment->payment_note,
                    'branch' => '—',
                    'order' => '—',
                    'recorded_by' => $payment->creator?->name ?? 'Unknown user',
                    'status_key' => 'approved',
                    'status_label' => 'Recorded',
                    'counted' => true,
                ]);

        $rows = $invoicePayments
            ->concat($supplierPayments)
            ->sortByDesc(fn (array $row): int => $row['recorded_at']->getTimestamp())
            ->values();

        $invoiceTotal = round((float) $invoicePayments->where('counted', true)->sum('amount'), 2);
        $supplierTotal = round((float) $supplierPayments->sum('amount'), 2);

        return [
            'rows' => $rows,
            'invoice_total' => $invoiceTotal,
            'supplier_total' => $supplierTotal,
            'total' => round($invoiceTotal + $supplierTotal, 2),
        ];
    }

    /**
     * Every purchase return to the supplier, with its lines, value and the
     * return due it adds once approved.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, count: int, value_total: float, refunded_total: float, due_total: float, quantity_total: int}
     */
    private function returnSection(): array
    {
        $rows = $this->returnRecords()
            ->map(fn (PurchaseReturn $return): array => $this->returnRow($return))
            ->values();

        $counted = $rows->where('counted', true);

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'value_total' => round((float) $counted->sum('value'), 2),
            'refunded_total' => round((float) $counted->sum('refunded'), 2),
            'due_total' => round((float) $counted->sum('due_added'), 2),
            'quantity_total' => (int) $counted->sum('quantity'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function returnRow(PurchaseReturn $return): array
    {
        $counted = $return->approval_status === ApprovalStatus::Approved;
        $value = round((float) $return->grand_total, 2);
        $refunded = round((float) $return->paid_amount, 2);

        return [
            'date' => $return->return_date,
            'reference' => $return->reference_no,
            'branch' => $return->location?->name ?? '—',
            'order' => $return->purchaseOrder?->reference_no ?? '—',
            'lines' => $return->items->map(fn (PurchaseReturnItem $item): array => [
                'product' => $item->unit?->displayName() ?? 'Unknown product',
                'quantity' => (int) $item->quantity,
                'rate' => round((float) $item->unit_price, 2),
                'subtotal' => round((float) $item->subtotal, 2),
            ])->values(),
            'quantity' => (int) $return->items->sum('quantity'),
            'value' => $value,
            'refunded' => $refunded,
            'due_added' => $counted ? round(max(0, $value - $refunded), 2) : 0.0,
            'status_key' => $return->approval_status->value,
            'status_label' => $return->approval_status->label(),
            'counted' => $counted,
            'recorded_by' => $return->creator?->name ?? 'Unknown user',
        ];
    }

    /**
     * Payment requests sent to the supplier's branches, newest first.
     *
     * @return Collection<int, SupplierPaymentRequest>
     */
    private function paymentRequests(): Collection
    {
        return SupplierPaymentRequest::query()
            ->where('supplier_id', $this->supplier->id)
            ->when($this->restricted, fn (Builder $requests) => BranchScope::scopeQuery($requests, $this->viewer))
            ->with(['location', 'purchaseOrder', 'creator', 'actioner', 'payment'])
            ->latest('id')
            ->get();
    }

    /**
     * Branches that owe money (the only ones a request may target), each with
     * the purchase orders of that branch that still have a due, for the
     * request form's dependent selects.
     *
     * @param  Collection<int, BranchRow>  $branches
     * @return Collection<int, array{id: int, name: string, due: float, label: string, orders: list<array{id: int, label: string, due: float}>}>
     */
    private function requestBranches(Collection $branches): Collection
    {
        return $branches
            ->filter(fn (array $branch): bool => $branch['due'] > 0)
            ->map(fn (array $branch): array => [
                'id' => $branch['location_id'],
                'name' => $branch['location_name'],
                'due' => $branch['due'],
                'label' => $branch['location_name'].' — due '.Money::format($branch['due']),
                'orders' => $branch['orders']
                    ->filter(fn (PurchaseOrder $order): bool => $order->payment_due > 0)
                    ->map(fn (PurchaseOrder $order): array => [
                        'id' => $order->id,
                        'label' => $order->reference_no.' — due '.Money::format($order->payment_due),
                        'due' => (float) $order->payment_due,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();
    }

    /**
     * The supplier's notification and activity feed, limited for branch users
     * to their own branch's entries.
     *
     * @return Collection<int, DatabaseNotification>
     */
    private function feed(): Collection
    {
        return $this->supplier->notifications()
            ->when($this->restricted, fn ($feed) => $feed->whereIn(
                'data->location_id',
                BranchScope::accessibleLocationIds($this->viewer) ?? []
            ))
            ->latest()
            ->limit(self::FEED_LIMIT)
            ->get();
    }

    /**
     * Headline numbers. Live orders only (cancelled orders carry no due);
     * open orders are live orders not yet delivered.
     *
     * @param  Collection<int, BranchRow>  $branches
     * @param  Collection<int, OrderRow>  $orders
     * @param  array<string, mixed>  $returns
     * @return array<string, float|int>
     */
    private function summary(Collection $branches, Collection $orders, array $returns): array
    {
        $liveOrders = $orders->reject(fn (array $row): bool => $row['cancelled']);

        return [
            'purchased' => round((float) $liveOrders->sum('grand_total'), 2),
            'paid' => round((float) $liveOrders->sum('paid'), 2),
            'due' => round((float) $branches->sum('due'), 2),
            'returned' => (float) $returns['value_total'],
            'live_orders' => $liveOrders->count(),
            'open_orders' => $liveOrders->reject(fn (array $row): bool => $row['delivered'])->count(),
            'branches_with_dues' => $branches->where('cleared', false)->count(),
            'branch_count' => $branches->count(),
        ];
    }

    /**
     * Purchase returns to the supplier visible to the viewer, loaded once.
     *
     * @return Collection<int, PurchaseReturn>
     */
    private function returnRecords(): Collection
    {
        return $this->returnRecords ??= PurchaseReturn::query()
            ->where('supplier_id', $this->supplier->id)
            ->when($this->restricted, fn (Builder $returns) => BranchScope::scopeQuery($returns, $this->viewer))
            ->with(['items.unit.product', 'purchaseOrder', 'location', 'creator'])
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Label for a supplier-level payment type: a payment method enum value
     * maps to its label, anything else (a stored method name) shows as-is.
     */
    private static function paymentTypeLabel(?string $type): string
    {
        if (blank($type)) {
            return 'Unspecified method';
        }

        return PaymentMethod::tryFrom(mb_strtolower($type))?->label() ?? $type;
    }
}
