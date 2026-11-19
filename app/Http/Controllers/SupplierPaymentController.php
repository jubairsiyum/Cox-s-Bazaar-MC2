<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Helpers\Money;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Models\Account;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\SupplierPaymentService;
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
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $payments = PurchasePayment::query()
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

        $monthTotals = PurchasePayment::query()
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
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'supplier_id', 'name']),
            'accounts' => Account::query()->orderBy('name')->get(['id', 'name', 'type']),
            'monthCount' => (int) ($monthTotals->payment_count ?? 0),
            'monthTotal' => (float) ($monthTotals->amount_total ?? 0),
            'totalOutstanding' => (float) Supplier::query()->sum('purchase_due'),
            'pendingRequests' => PurchasePayment::query()->where('status', ApprovalStatus::Pending->value)->count(),
        ]);
    }

    /**
     * Show the "record payment" form: pick a supplier, then one of the
     * supplier's outstanding purchase invoices.
     */
    public function create(Request $request): View
    {
        $suppliers = Supplier::query()->active()->orderBy('name')->get(['id', 'supplier_id', 'name', 'purchase_due']);

        $selectedSupplierId = (int) $request->input('supplier_id');
        $orders = collect();

        if ($selectedSupplierId > 0) {
            $orders = PurchaseOrder::query()
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
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
        ]);
    }

    /**
     * Record a payment against a supplier's outstanding purchase due, paid
     * from a chosen cash/bank account. Takes effect immediately (like the
     * supplier page's due payments) and can be reversed later.
     */
    public function store(StoreSupplierPaymentRequest $request, SupplierPaymentService $payments): RedirectResponse
    {
        $validated = $request->validated();

        $order = PurchaseOrder::query()->findOrFail((int) $validated['purchase_order_id']);
        $amount = round((float) $validated['amount'], 2);

        try {
            $payment = DB::transaction(function () use ($validated, $order, $amount, $request, $payments): PurchasePayment {
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
    public function show(PurchasePayment $purchasePayment): View
    {
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
            $user !== null && ($user->can('supplier-payments.manage') || $user->can('finance.manage')),
            403
        );

        $original = $purchasePayment->toArray();
        $wasApplied = $purchasePayment->status === ApprovalStatus::Approved;

        DB::transaction(function () use ($purchasePayment, $payments, $wasApplied): void {
            if ($wasApplied) {
                $payments->reverse($purchasePayment);
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
