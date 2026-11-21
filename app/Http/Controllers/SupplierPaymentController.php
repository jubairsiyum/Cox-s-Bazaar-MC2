<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Helpers\Money;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\SupplierPaymentService;
use App\Services\SupplierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Finance-facing supplier payments (docs/modules.md Module 10): every
 * App\Models\PurchasePayment in one list, plus recording payments against a
 * supplier's outstanding purchase dues from a chosen cash/bank account and
 * reversing them again. The Purchase Dues screens keep working unchanged —
 * both write the same table.
 */
class SupplierPaymentController extends Controller
{
    /**
     * List all supplier payments with supplier / date-range / account filters.
     * Strict branch isolation: only payments settling purchases of the user's
     * own branch (Super Admin sees every branch).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $payments = $this->scopedPurchasePayments($user)
            ->with(['purchaseOrder.supplier', 'account', 'creator'])
            ->when($request->filled('supplier_id'), function ($q) use ($request): void {
                $q->whereHas('purchaseOrder', fn ($oq) => $oq->where('supplier_id', (int) $request->input('supplier_id')));
            })
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', (int) $request->input('account_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereRaw('COALESCE(payment_date, DATE(created_at)) >= ?', [$request->input('date_from')]))
            ->when($request->filled('date_to'), fn ($q) => $q->whereRaw('COALESCE(payment_date, DATE(created_at)) <= ?', [$request->input('date_to')]))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = $request->string('search')->toString();
                $q->where(function ($qq) use ($term): void {
                    $qq->where('note', 'like', "%{$term}%")
                        ->orWhereHas('purchaseOrder', function ($oq) use ($term): void {
                            $oq->where('reference_no', 'like', "%{$term}%")
                                ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$term}%"));
                        });
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $monthTotals = $this->scopedPurchasePayments($user)
            ->selectRaw('
                COUNT(*) as payment_count,
                COALESCE(SUM(amount), 0) as amount_total
            ')
            ->where('status', ApprovalStatus::Approved->value)
            ->whereRaw('COALESCE(payment_date, DATE(created_at)) >= ?', [now()->startOfMonth()->toDateString()])
            ->whereRaw('COALESCE(payment_date, DATE(created_at)) <= ?', [now()->endOfMonth()->toDateString()])
            ->first();

        return view('supplier-payments.index', [
            'payments' => $payments,
            'perPage' => $perPage,
            'suppliers' => SupplierScope::scopeQuery(Supplier::query(), $user, 'id')->orderBy('name')->get(['id', 'supplier_id', 'name']),
            'accounts' => BranchScope::scopeQuery(Account::query(), $user)->orderBy('name')->get(['id', 'name', 'type']),
            'monthCount' => (int) ($monthTotals->payment_count ?? 0),
            'monthTotal' => (float) ($monthTotals->amount_total ?? 0),
            'totalOutstanding' => $this->totalOutstanding($user),
            'pendingRequests' => $this->scopedPurchasePayments($user)
                ->where('status', ApprovalStatus::Pending->value)
                ->count(),
            'paymentRequests' => SupplierScope::scopeQuery(BranchScope::scopeQuery(SupplierPaymentRequest::query(), $user), $user)
                ->with(['supplier', 'location', 'purchaseOrder', 'payment'])
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Purchase payments within the user's branch and supplier scope.
     */
    private function scopedPurchasePayments(User $user): Builder
    {
        return SupplierScope::scopeQueryHas(
            BranchScope::scopeQueryHas(PurchasePayment::query(), $user, 'purchaseOrder'),
            $user,
            'purchaseOrder'
        );
    }

    /**
     * Supplier due still outstanding within the user's scope: the Super Admin
     * sees the suppliers' global purchase due balances, branch users the
     * unpaid remainder of their own branch's purchases.
     */
    private function totalOutstanding(User $user): float
    {
        if (BranchScope::isUnrestricted($user)) {
            return (float) Supplier::query()->sum('purchase_due');
        }

        return (float) SupplierScope::scopeQuery(BranchScope::scopeQuery(PurchaseOrder::query(), $user), $user)
            ->where('purchase_status', '!=', 'cancelled')
            ->toBase()
            ->selectRaw('COALESCE(SUM(MAX(grand_total - paid_amount, 0)), 0) as outstanding')
            ->value('outstanding') ?? 0.0;
    }

    /**
     * Show the "record payment" form: pick a supplier, then one of the
     * supplier's outstanding purchase invoices. Query params prefill the form
     * so supplier payment request notifications can link straight here
     * (supplier, branch/PO, outstanding amount, request to action).
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        abort_if($user->isSupplierPortalUser(), 403);

        $suppliers = Supplier::query()->active()->orderBy('name')->get(['id', 'supplier_id', 'name', 'purchase_due']);

        $paymentRequest = $request->filled('payment_request_id')
            ? SupplierPaymentRequest::query()->with(['purchaseOrder', 'location'])->find((int) $request->input('payment_request_id'))
            : null;

        // Payment requests are addressed to a branch — only that branch's
        // users (or the Super Admin) may act on them.
        if ($paymentRequest !== null
            && ($paymentRequest->status !== SupplierPaymentRequestStatus::Open
                || ! BranchScope::canAccess($user, $paymentRequest->location_id))
        ) {
            $paymentRequest = null;
        }

        $selectedSupplierId = (int) ($request->input('supplier_id') ?: $paymentRequest?->supplier_id);
        $orders = collect();

        if ($selectedSupplierId > 0) {
            $orders = BranchScope::scopeQuery(PurchaseOrder::query(), $user)
                ->where('supplier_id', $selectedSupplierId)
                ->where('purchase_status', '!=', 'cancelled')
                ->whereColumn('paid_amount', '<', 'grand_total')
                ->with('location')
                ->orderByDesc('order_date')
                ->get();
        }

        return view('supplier-payments.create', [
            'suppliers' => $suppliers,
            'orders' => $orders,
            'selectedSupplierId' => $selectedSupplierId,
            'accounts' => BranchScope::scopeQueryWithShared(Account::query(), $user)->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'methods' => PaymentMethod::options(),
            'paymentRequest' => $paymentRequest,
            'prefillOrderId' => (int) ($request->input('purchase_order_id') ?: $paymentRequest?->purchase_order_id),
            'prefillAmount' => $request->input('amount') ?: $paymentRequest?->amount,
        ]);
    }

    /**
     * Record a payment against a supplier's outstanding purchase due, paid
     * from a chosen cash/bank account. Takes effect immediately (like the
     * supplier page's due payments) and can be reversed later.
     */
    public function store(StoreSupplierPaymentRequest $request, SupplierPaymentService $payments): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->isSupplierPortalUser(), 403);

        $validated = $request->validated();

        $order = PurchaseOrder::query()->findOrFail((int) $validated['purchase_order_id']);
        $amount = round((float) $validated['amount'], 2);

        // Strict branch isolation: payments settle the user's own branch's
        // purchases from their own branch's accounts.
        if (! BranchScope::canAccess($user, $order->location_id)) {
            return back()->withInput()->with('error', 'You can only record payments for purchases of your own branch.');
        }

        $account = Account::query()->find((int) $validated['account_id']);

        if ($account === null || ($account->location_id !== null && ! BranchScope::canAccess($user, $account->location_id))) {
            return back()->withInput()->with('error', 'You can only pay from accounts of your own branch.');
        }

        try {
            $payment = DB::transaction(function () use ($validated, $order, $amount, $request, $payments, $user): PurchasePayment {
                $payment = PurchasePayment::create([
                    'purchase_order_id' => $order->id,
                    'account_id' => (int) $validated['account_id'],
                    'amount' => $amount,
                    'payment_date' => $validated['payment_date'],
                    'method' => $validated['method'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'status' => ApprovalStatus::Approved,
                    'created_by' => $request->user()?->id,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);

                // Clearing a supplier payment request from the pre-filled
                // screen actions the request against the recorded payment.
                // Requests are addressed to a branch — only that branch's
                // users (or the Super Admin) may action them. This runs before
                // the payment is applied so its supplier notice names the request.
                if (! empty($validated['payment_request_id'])) {
                    BranchScope::scopeQuery(SupplierPaymentRequest::query(), $user)
                        ->whereKey((int) $validated['payment_request_id'])
                        ->where('status', SupplierPaymentRequestStatus::Open->value)
                        ->update([
                            'status' => SupplierPaymentRequestStatus::Actioned->value,
                            'actioned_by' => $request->user()?->id,
                            'payment_id' => $payment->id,
                        ]);
                }

                $payments->apply($payment);

                return $payment;
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditService::log('paid', $payment, [], $payment->toArray(), sprintf(
            'Recorded a supplier payment of %s against purchase %s from a cash/bank account.',
            Money::format($amount),
            $order->reference_no
        ));

        return redirect()->route('supplier-payments.index')
            ->with('success', 'Supplier payment of '.Money::format($amount).' recorded successfully.');
    }

    /**
     * Show one supplier payment.
     */
    public function show(Request $request, PurchasePayment $purchasePayment): View
    {
        abort_unless(BranchScope::canAccess($request->user(), $purchasePayment->locationId()), 403);
        abort_unless(SupplierScope::canAccess($request->user(), $purchasePayment->purchaseOrder?->supplier_id), 403);

        $purchasePayment->load(['purchaseOrder.supplier', 'purchaseOrder.location', 'account', 'creator', 'approver']);

        return view('supplier-payments.show', [
            'payment' => $purchasePayment,
        ]);
    }

    /**
     * Delete/reverse a payment. Applied payments give the money back to the
     * account and restore the purchase and supplier dues first.
     */
    public function destroy(Request $request, PurchasePayment $purchasePayment, SupplierPaymentService $payments): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ! $user->isSupplierPortalUser() && ($user->can('supplier-payments.manage') || $user->can('finance.manage')),
            403
        );

        abort_unless(BranchScope::canAccess($user, $purchasePayment->locationId()), 403);

        $original = $purchasePayment->toArray();
        $wasApplied = $purchasePayment->status === ApprovalStatus::Approved;

        DB::transaction(function () use ($purchasePayment, $payments, $wasApplied, $user): void {
            if ($wasApplied) {
                $payments->reverse($purchasePayment, $user);
            }

            $purchasePayment->delete();
        });

        AuditService::log('deleted', null, $original, [], sprintf(
            'Deleted supplier payment of %s against purchase %s%s.',
            Money::format((float) $original['amount']),
            $purchasePayment->purchaseOrder?->reference_no ?? '',
            $wasApplied ? ' and restored the due balances' : ''
        ));

        return back()->with('success', $wasApplied
            ? 'Supplier payment deleted and all due balances restored.'
            : 'Supplier payment request cancelled.');
    }
}
