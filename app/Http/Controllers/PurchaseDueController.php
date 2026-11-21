<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseStatus;
use App\Helpers\Money;
use App\Http\Requests\PurchaseDue\StorePurchaseDueRequest;
use App\Http\Requests\PurchaseDue\UpdatePurchaseDueRequest;
use App\Models\Branch;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\ManagerLookup;
use App\Services\NotificationService;
use App\Services\PurchaseOrderWorkflow;
use App\Services\SalesScope;
use App\Services\SupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Purchase Dues" — tracks the remaining dues of partially paid purchase
 * invoices by reference and lets staff request due payments.
 *
 * A manager must never be asked to approve their own task: when the acting
 * user is the responsible approver for the purchase's outlet/branch (or no
 * other responsible user exists to ask), the payment is applied immediately
 * and recorded as completed directly. Genuine segregation of duties remains
 * for staff-level requests, which go to the outlet/branch manager (or Super
 * Admin) for approval — the approver is never the requester themselves.
 */
class PurchaseDueController extends Controller
{
    /**
     * Payment request statuses the list can filter by.
     *
     * @var array<string, string>
     */
    protected const REQUEST_STATUS_FILTERS = [
        'none' => 'No Request',
        'pending' => 'Awaiting Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    /**
     * List the partially paid purchase invoices with their due balances and
     * latest due payment request.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchasePayment::class);

        $orders = $this->dueQuery($request)->paginate(25)->withQueryString();

        $requestsByOrder = PurchasePayment::query()
            ->whereIn('purchase_order_id', $orders->getCollection()->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('purchase_order_id');

        return view('purchase-dues.index', [
            'orders' => $orders,
            'requestsByOrder' => $requestsByOrder,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'requestStatusFilters' => self::REQUEST_STATUS_FILTERS,
            'methodOptions' => PaymentMethod::options(),
            'canFilterBranch' => ! LocationScope::isRestricted($request->user()),
        ]);
    }

    /**
     * Submit a due payment against a purchase invoice.
     *
     * When the acting user is the responsible approver for this purchase (or
     * the only responsible user at its outlet/branch) the payment is applied
     * immediately — no approval request is raised. Otherwise the request is
     * saved pending and has no financial effect until a manager approves it.
     */
    public function store(StorePurchaseDueRequest $request): RedirectResponse
    {
        abort_if($request->user()->isSupplierPortalUser(), 403);

        $validated = $request->validated();

        /** @var PurchaseOrder $order */
        $order = PurchaseOrder::query()->findOrFail((int) $validated['purchase_order_id']);

        if ($order->purchase_status === PurchaseStatus::Cancelled) {
            return back()->with('error', 'Cancelled purchases cannot carry due payments.');
        }

        if (! LocationScope::canAccess($request->user(), $order->location_id)) {
            return back()->withInput()->with('error', 'You can only manage dues of your own branch/location.');
        }

        $amount = round((float) $validated['amount'], 2);

        $payment = PurchasePayment::create([
            'purchase_order_id' => $order->id,
            'amount' => $amount,
            'method' => $validated['method'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => ApprovalStatus::Pending,
            'created_by' => $request->user()?->id,
        ]);

        if ($this->shouldCompleteDirectly($request->user(), $order)) {
            $this->applyPayment($payment, $order, $request->user()?->id);

            AuditService::created(
                $payment,
                sprintf(
                    'Due payment of %s against purchase %s completed directly by the responsible manager — no approval request was raised.',
                    Money::format($amount),
                    $order->reference_no
                )
            );

            return back()->with('success', 'Due payment applied to the purchase invoice.');
        }

        AuditService::created(
            $payment,
            sprintf('Requested a due payment of %s against purchase %s.', Money::format($amount), $order->reference_no)
        );

        NotificationService::notifyMany(
            $this->approversFor($order, $request->user()),
            sprintf("Purchase due request '%s' awaits your approval.", $order->reference_no),
            sprintf(
                '%s — %s — %s, submitted by %s.',
                $order->supplier?->name ?? 'Unknown supplier',
                $order->location?->name ?? 'Unknown location',
                Money::format($amount),
                $request->user()?->name ?? 'Unknown user'
            ),
            route('purchase-dues.index'),
            'warning'
        );

        return back()->with('success', 'Due payment request submitted for manager approval.');
    }

    /**
     * Edit a pending due payment request (its creator only). A creator who is
     * the responsible approver completes the request directly on update —
     * there is nobody else who could sign it off.
     */
    public function update(UpdatePurchaseDueRequest $request, PurchasePayment $purchasePayment): RedirectResponse
    {
        abort_if($request->user()->isSupplierPortalUser(), 403);

        $validated = $request->validated();
        $original = $purchasePayment->toArray();

        $order = $purchasePayment->purchaseOrder;
        $amount = round((float) $validated['amount'], 2);
        $completesDirectly = $order !== null && $this->shouldCompleteDirectly($request->user(), $order);

        if ($completesDirectly && $amount > (float) $order->payment_due) {
            return back()->withInput()->with('error', 'The remaining due of this purchase no longer covers the request.');
        }

        $purchasePayment->update([
            'amount' => $amount,
            'method' => $validated['method'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        if ($completesDirectly) {
            $this->applyPayment($purchasePayment, $order, $request->user()?->id);

            AuditService::updated(
                $purchasePayment,
                $original,
                sprintf(
                    'Due payment of %s against purchase %s completed directly by the responsible manager — no approval request was raised.',
                    Money::format($amount),
                    $order->reference_no
                )
            );

            return back()->with('success', 'Due payment applied to the purchase invoice.');
        }

        AuditService::updated($purchasePayment, $original, sprintf('Updated the due payment request for purchase %s.', $purchasePayment->purchaseOrder?->reference_no ?? ''));

        return back()->with('success', 'Due payment request updated.');
    }

    /**
     * Cancel a pending due payment request (its creator only). Nothing has
     * been applied yet, so the record is simply removed.
     */
    public function destroy(PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorize('delete', $purchasePayment);

        $reference = $purchasePayment->purchaseOrder?->reference_no ?? '';
        $original = $purchasePayment->toArray();

        $purchasePayment->delete();

        AuditService::log('deleted', null, $original, [], sprintf('Cancelled the due payment request for purchase %s.', $reference));

        return back()->with('success', 'Due payment request cancelled.');
    }

    /**
     * Approve a pending due payment request: the payment is applied to the
     * purchase invoice and the supplier's purchase due balance. Kept working
     * for pending legacy rows; a user may never approve their own request.
     */
    public function approve(Request $request, PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorize('approve', $purchasePayment);

        if ((int) $purchasePayment->created_by === (int) $request->user()?->id) {
            return back()->with('error', 'You cannot approve your own due payment request. Another responsible manager must sign it off.');
        }

        if ($purchasePayment->status !== ApprovalStatus::Pending) {
            return back()->with('error', 'Only pending due payment requests can be approved.');
        }

        $order = $purchasePayment->purchaseOrder;

        if ($order === null || $order->purchase_status === PurchaseStatus::Cancelled) {
            return back()->with('error', 'This purchase can no longer receive due payments.');
        }

        $amount = (float) $purchasePayment->amount;

        if ($amount > (float) $order->payment_due) {
            return back()->with('error', 'The remaining due of this purchase no longer covers the request.');
        }

        $this->applyPayment($purchasePayment, $order, $request->user()?->id);

        AuditService::log(
            'approved',
            $purchasePayment,
            [],
            $purchasePayment->toArray(),
            sprintf('Approved a due payment of %s against purchase %s.', Money::format($amount), $order->reference_no)
        );

        $this->notifyCreator($purchasePayment, $request, true);

        return back()->with('success', 'Due payment applied to the purchase invoice.');
    }

    /**
     * Reject a pending due payment request: nothing is applied anywhere. A
     * user may never reject (and thereby decide) their own request either —
     * creators cancel their own requests instead.
     */
    public function reject(Request $request, PurchasePayment $purchasePayment): RedirectResponse
    {
        $this->authorize('reject', $purchasePayment);

        if ((int) $purchasePayment->created_by === (int) $request->user()?->id) {
            return back()->with('error', 'You cannot decide your own due payment request. Cancel it instead.');
        }

        if ($purchasePayment->status !== ApprovalStatus::Pending) {
            return back()->with('error', 'Only pending due payment requests can be rejected.');
        }

        $purchasePayment->update([
            'status' => ApprovalStatus::Rejected,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        AuditService::log(
            'rejected',
            $purchasePayment,
            [],
            $purchasePayment->toArray(),
            sprintf('Rejected the due payment request of %s against purchase %s.', Money::format($purchasePayment->amount), $purchasePayment->purchaseOrder?->reference_no ?? '')
        );

        $this->notifyCreator($purchasePayment, $request, false);

        return back()->with('success', 'Due payment request rejected.');
    }

    /**
     * Tell the request's creator what was decided on it.
     */
    private function notifyCreator(PurchasePayment $purchasePayment, Request $request, bool $approved): void
    {
        $creator = $purchasePayment->creator;

        if ($creator === null) {
            return;
        }

        NotificationService::notify(
            $creator,
            sprintf(
                "Purchase due payment '%s' %s.",
                $purchasePayment->purchaseOrder?->reference_no ?? '',
                $approved ? 'approved' : 'rejected'
            ),
            sprintf(
                'Your due payment request of %s was %s by %s.',
                Money::format($purchasePayment->amount),
                $approved ? 'approved' : 'rejected',
                $request->user()?->name ?? 'an authorized manager'
            ),
            route('purchase-dues.index'),
            $approved ? 'success' : 'error'
        );
    }

    /**
     * Apply a due payment to the purchase invoice and the supplier's purchase
     * due balance, marking the payment approved by the given user. A payment
     * never changes the order's status; every applied payment is announced
     * on the supplier's profile feed, handled by {@see PurchaseOrderWorkflow}.
     */
    private function applyPayment(PurchasePayment $purchasePayment, PurchaseOrder $order, ?int $approvedBy): void
    {
        DB::transaction(function () use ($purchasePayment, $order, $approvedBy): void {
            $order->paid_amount = round((float) $order->paid_amount + (float) $purchasePayment->amount, 2);
            $order->save();

            if ($order->supplier_id) {
                $supplier = $order->supplier;

                $supplier->update([
                    'purchase_due' => round(max(0, (float) $supplier->purchase_due - (float) $purchasePayment->amount), 2),
                ]);
            }

            $purchasePayment->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            PurchaseOrderWorkflow::paymentApplied($purchasePayment);
        });
    }

    /**
     * Whether the acting user completes the due payment themselves instead of
     * raising an approval request.
     *
     * True when the user IS the responsible approver for the purchase's
     * outlet/branch (Super Admin, or a Manager/Outlet Manager of the record —
     * including head-office Managers, who are responsible for all outlets):
     * asking them to approve their own task would be self-approval theater.
     * Also true when the resolved approver set would only contain the
     * requester — there is nobody else who could ever sign it off.
     */
    private function shouldCompleteDirectly(?User $user, PurchaseOrder $order): bool
    {
        if ($user === null) {
            return false;
        }

        if (SalesScope::canApprove($user, $order)) {
            return true;
        }

        $responsible = ManagerLookup::managersFor($order->location_id);

        return $responsible->isNotEmpty()
            && $responsible->every(fn (User $candidate): bool => (int) $candidate->id === (int) $user->id);
    }

    /**
     * The users who may be asked to approve a request raised by the given
     * user: managers of the purchase's outlet/branch (never the requester
     * themselves), falling back to Super Admins when no manager is assigned.
     *
     * @return Collection<int, User>
     */
    private function approversFor(PurchaseOrder $order, ?User $actor): Collection
    {
        $isActor = fn (User $candidate): bool => $actor !== null && (int) $candidate->id === (int) $actor->id;

        $approvers = ManagerLookup::managersFor($order->location_id)
            ->filter(fn (User $candidate): bool => SalesScope::canApprove($candidate, $order))
            ->reject($isActor);

        if ($approvers->isNotEmpty()) {
            return $approvers->values();
        }

        return User::query()->active()->role('Super Admin')->get()
            ->reject($isActor)
            ->values();
    }

    /**
     * Base query for the Purchase Dues list: purchase invoices with money
     * still outstanding, with the page filters applied and the usual
     * branch/location scoping for restricted users.
     */
    private function dueQuery(Request $request): Builder
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'location.branch'])
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->where('purchase_status', '!=', PurchaseStatus::Cancelled->value)
            ->search($request->string('search')->toString())
            ->when($request->filled('request_status'), function (Builder $q) use ($request): void {
                $status = $request->string('request_status')->toString();

                if ($status === 'none') {
                    $q->whereNotExists(function (QueryBuilder $sub): void {
                        $sub->selectRaw('1')
                            ->from('purchase_payments')
                            ->whereColumn('purchase_payments.purchase_order_id', 'purchase_orders.id');
                    });

                    return;
                }

                // The latest request of the order carries the given status.
                $q->whereExists(function (QueryBuilder $sub) use ($status): void {
                    $sub->selectRaw('1')
                        ->from('purchase_payments')
                        ->whereColumn('purchase_payments.purchase_order_id', 'purchase_orders.id')
                        ->where('purchase_payments.status', $status)
                        ->whereNotExists(function (QueryBuilder $newer): void {
                            $newer->selectRaw('1')
                                ->from('purchase_payments as newer_requests')
                                ->whereColumn('newer_requests.purchase_order_id', 'purchase_payments.purchase_order_id')
                                ->whereColumn('newer_requests.id', '>', 'purchase_payments.id');
                        });
                });
            })
            ->when($request->filled('branch_id') && ! LocationScope::isRestricted($request->user()), function (Builder $q) use ($request): void {
                $q->whereIn(
                    'location_id',
                    Location::query()->where('branch_id', (int) $request->input('branch_id'))->pluck('id')
                );
            });

        return SupplierScope::scopeQuery(LocationScope::scopeQuery($query, $request->user()), $request->user())
            ->orderByDesc('order_date')
            ->orderByDesc('id');
    }
}
