<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountEntryStatus;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Http\Requests\AccountTransaction\StoreAccountTransactionRequest;
use App\Http\Requests\AccountTransaction\UpdateAccountTransactionRequest;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Services\AccountBalanceService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The merged "Transactions" section (docs/modules.md Module 07): income and
 * expense entries on cash/bank accounts, with a lightweight approval workflow.
 * Only completed entries move account balances.
 */
class AccountTransactionController extends Controller
{
    /**
     * List income/expense entries with KPI summary cards.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountTransaction::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $transactions = AccountTransaction::query()
            ->with(['account', 'creator', 'approver'])
            ->search($request->string('search')->toString())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', (int) $request->input('account_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->input('date_to')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $monthTotals = AccountTransaction::query()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense_total
            ")
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->first();

        $pendingCount = AccountTransaction::query()->where('status', 'pending')->count();

        return view('transactions.index', [
            'transactions' => $transactions,
            'perPage' => $perPage,
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type']),
            'categories' => AccountTransaction::CATEGORIES,
            'monthIncome' => (float) ($monthTotals->income_total ?? 0),
            'monthExpense' => (float) ($monthTotals->expense_total ?? 0),
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Show the form for creating an income or expense entry.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', [AccountTransaction::class, $request->string('type')->toString() ?: null]);

        return view('transactions.create', [
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'categories' => AccountTransaction::CATEGORIES,
            'defaultType' => $request->string('type')->toString(),
        ]);
    }

    /**
     * Store a new entry as pending — it only moves money once approved.
     */
    public function store(StoreAccountTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['amount'] = round((float) $validated['amount'], 2);
        $validated['status'] = AccountEntryStatus::Pending->value;
        $validated['created_by'] = $request->user()?->id;

        $transaction = AccountTransaction::create($validated);

        AuditService::created($transaction, sprintf(
            '%s entry of %s (%s) recorded on account %s, awaiting approval.',
            $transaction->type->label(),
            number_format((float) $transaction->amount, 2),
            $transaction->category,
            $transaction->account?->name ?? ''
        ));

        return redirect()->route('transactions.index')
            ->with('success', sprintf('%s entry recorded and queued for approval.', $transaction->type->label()));
    }

    /**
     * Show the form for editing an entry.
     */
    public function edit(AccountTransaction $accountTransaction): View
    {
        $this->authorize('update', $accountTransaction);

        return view('transactions.edit', [
            'transaction' => $accountTransaction,
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'categories' => AccountTransaction::CATEGORIES,
        ]);
    }

    /**
     * Update an entry. A completed entry is reversed first and re-applied after
     * the change so the balance is never double-counted.
     */
    public function update(UpdateAccountTransactionRequest $request, AccountTransaction $accountTransaction, AccountBalanceService $balances): RedirectResponse
    {
        $original = $accountTransaction->getOriginal();
        $validated = $request->validated();
        $validated['amount'] = round((float) $validated['amount'], 2);

        // Keep the auto-generated document number when left blank.
        if (blank($validated['reference_no'] ?? null)) {
            $validated['reference_no'] = $accountTransaction->reference_no;
        }

        $wasCompleted = $accountTransaction->status === AccountEntryStatus::Completed;

        try {
            DB::transaction(function () use ($accountTransaction, $validated, $balances, $wasCompleted): void {
                if ($wasCompleted) {
                    $balances->reverseTransaction($accountTransaction);
                }

                $accountTransaction->update($validated);

                if ($wasCompleted) {
                    $balances->applyTransaction($accountTransaction);
                }
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditService::updated($accountTransaction, $original, sprintf(
            '%s entry %s updated.',
            $accountTransaction->type->label(),
            $accountTransaction->reference_no
        ));

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction updated successfully.');
    }

    /**
     * Delete an entry. Completed entries give their money back first.
     */
    public function destroy(AccountTransaction $accountTransaction, AccountBalanceService $balances): RedirectResponse
    {
        $this->authorize('delete', $accountTransaction);

        $original = $accountTransaction->toArray();

        DB::transaction(function () use ($accountTransaction, $balances): void {
            if ($accountTransaction->status === AccountEntryStatus::Completed) {
                $balances->reverseTransaction($accountTransaction);
            }

            $accountTransaction->delete();
        });

        AuditService::log('deleted', null, $original, [], sprintf(
            'Deleted %s entry %s and restored its balance effect.',
            $accountTransaction->type->value,
            $accountTransaction->reference_no
        ));

        return redirect()->route('transactions.index')
            ->with('success', 'Transaction deleted and account balance restored.');
    }

    /**
     * Approve a pending entry: it becomes completed and moves the balance.
     */
    public function approve(Request $request, AccountTransaction $accountTransaction, AccountBalanceService $balances): RedirectResponse
    {
        $this->authorize('approve', $accountTransaction);

        if ($accountTransaction->status !== AccountEntryStatus::Pending) {
            return back()->with('error', 'Only pending transactions can be approved.');
        }

        try {
            DB::transaction(function () use ($accountTransaction, $balances, $request): void {
                $accountTransaction->update([
                    'status' => AccountEntryStatus::Completed,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);

                $balances->applyTransaction($accountTransaction);
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        AuditService::log('approved', $accountTransaction, [], $accountTransaction->toArray(), sprintf(
            'Approved %s entry %s of %s.',
            $accountTransaction->type->value,
            $accountTransaction->reference_no,
            number_format((float) $accountTransaction->amount, 2)
        ));

        return back()->with('success', 'Transaction approved and account balance updated.');
    }

    /**
     * Reject a pending entry: it is cancelled and never moves money.
     */
    public function reject(Request $request, AccountTransaction $accountTransaction): RedirectResponse
    {
        $this->authorize('reject', $accountTransaction);

        if ($accountTransaction->status !== AccountEntryStatus::Pending) {
            return back()->with('error', 'Only pending transactions can be rejected.');
        }

        $accountTransaction->update([
            'status' => AccountEntryStatus::Cancelled,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        AuditService::log('rejected', $accountTransaction, [], $accountTransaction->toArray(), sprintf(
            'Rejected %s entry %s.',
            $accountTransaction->type->value,
            $accountTransaction->reference_no
        ));

        return back()->with('success', 'Transaction cancelled.');
    }
}
