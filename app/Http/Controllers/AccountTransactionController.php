<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountEntryStatus;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Helpers\Money;
use App\Http\Requests\AccountTransaction\StoreAccountTransactionRequest;
use App\Http\Requests\AccountTransaction\UpdateAccountTransactionRequest;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\PaymentMethod;
use App\Models\TransactionCategory;
use App\Services\AccountBalanceService;
use App\Services\AuditService;
use App\Services\BranchScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * Strict branch isolation: only the user's own branch's entries (and KPIs).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountTransaction::class);

        $user = $request->user();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $type = $request->string('type')->toString();
        $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : null;
        $dateToExclusive = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->addDay()->toDateString()
            : null;

        $txBase = BranchScope::scopeQuery(AccountTransaction::query(), $user)
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', (int) $request->input('account_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($dateFrom !== null, fn ($q) => $q->where('transaction_date', '>=', $dateFrom))
            ->when($dateToExclusive !== null, fn ($q) => $q->where('transaction_date', '<', $dateToExclusive));

        $trBase = AccountTransfer::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('account_id'), function ($q) use ($request) {
                $accId = (int) $request->input('account_id');
                $q->where(fn ($qq) => $qq->where('from_account_id', $accId)->orWhere('to_account_id', $accId));
            })
            ->when($dateFrom !== null, fn ($q) => $q->where('transfer_date', '>=', $dateFrom))
            ->when($dateToExclusive !== null, fn ($q) => $q->where('transfer_date', '<', $dateToExclusive));

        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $trBase->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('location_id', $branchId)
                        ->orWhereHas('toAccount', fn ($tq) => $tq->where('location_id', $branchId));
                }
            });
        }

        if ($type === 'transfer') {
            $transactions = (clone $trBase)
                ->with(['fromAccount', 'toAccount', 'creator', 'approver'])
                ->orderByDesc('transfer_date')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        } elseif ($type === 'income' || $type === 'expense') {
            $transactions = (clone $txBase)
                ->with(['account', 'creator', 'approver'])
                ->where('type', $type)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();
        } else {
            $transactions = $this->paginateUnifiedLedger($txBase, $trBase, $request, $perPage);
        }

        $monthTotals = BranchScope::scopeQuery(AccountTransaction::query(), $user)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense_total
            ")
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->first();

        $monthTransfers = (float) (clone $trBase)
            ->where('status', 'completed')
            ->whereBetween('transfer_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $pendingTxCount = BranchScope::scopeQuery(AccountTransaction::query(), $user)->where('status', 'pending')->count();
        $pendingTrCount = (clone $trBase)->where('status', 'pending')->count();
        $pendingCount = $pendingTxCount + $pendingTrCount;

        return view('transactions.index', [
            'transactions' => $transactions,
            'perPage' => $perPage,
            'accounts' => BranchScope::scopeQuery(Account::query(), $user)->active()->orderBy('name')->get(['id', 'name', 'type']),
            'categories' => TransactionCategory::options(),
            'monthIncome' => (float) ($monthTotals->income_total ?? 0),
            'monthExpense' => (float) ($monthTotals->expense_total ?? 0),
            'monthTransfers' => $monthTransfers,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Paginate unified transactions and transfers using SQL UNION ALL instead of PHP memory merges.
     */
    private function paginateUnifiedLedger(
        Builder $txBase,
        Builder $trBase,
        Request $request,
        int $perPage,
    ): LengthAwarePaginator {
        $txSelect = (clone $txBase)
            ->selectRaw("id, 'transaction' as entry_kind, transaction_date as entry_date")
            ->toBase();

        $trSelect = (clone $trBase)
            ->when($request->filled('category'), fn ($q) => $q->whereRaw('1 = 0'))
            ->selectRaw("id, 'transfer' as entry_kind, transfer_date as entry_date")
            ->toBase();

        $paginator = DB::query()
            ->fromSub($txSelect->unionAll($trSelect), 'unified_ledger')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $pageRows = $paginator->getCollection();
        $txIds = $pageRows->where('entry_kind', 'transaction')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $trIds = $pageRows->where('entry_kind', 'transfer')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $txModels = $txIds === []
            ? collect()
            : AccountTransaction::with(['account', 'creator', 'approver'])
                ->whereIn('id', $txIds)
                ->get()
                ->keyBy('id');

        $trModels = $trIds === []
            ? collect()
            : AccountTransfer::with(['fromAccount', 'toAccount', 'creator', 'approver'])
                ->whereIn('id', $trIds)
                ->get()
                ->keyBy('id');

        $hydrated = $pageRows
            ->map(static fn (object $row) => $row->entry_kind === 'transfer'
                ? $trModels->get((int) $row->id)
                : $txModels->get((int) $row->id))
            ->filter()
            ->values();

        $paginator->setCollection($hydrated);

        return $paginator;
    }

    /**
     * Show the form for creating an income or expense entry.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', [AccountTransaction::class, $request->string('type')->toString() ?: null]);

        return view('transactions.create', [
            'accounts' => BranchScope::scopeQuery(Account::query(), $request->user())->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'categories' => TransactionCategory::query()->active()->orderBy('name')->get(['id', 'name', 'type']),
            'methods' => PaymentMethod::options(),
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

        // Strict branch isolation: entries are recorded on the user's own
        // branch's accounts only (shared/head-office accounts are Super Admin).
        $account = Account::query()->find((int) $validated['account_id']);

        if (! BranchScope::canAccess($request->user(), $account?->location_id)) {
            return back()->withInput()->with('error', 'You can only record entries on accounts of your own branch.');
        }

        $transaction = AccountTransaction::create($validated);

        AuditService::created($transaction, sprintf(
            '%s entry of %s (%s) recorded on account %s, awaiting approval.',
            $transaction->type->label(),
            Money::format($transaction->amount),
            $transaction->category,
            $transaction->account?->name ?? ''
        ));

        return redirect()->route('transactions.index')
            ->with('success', sprintf('%s entry recorded and queued for approval.', $transaction->type->label()));
    }

    /**
     * Show the form for editing an entry.
     */
    public function edit(Request $request, AccountTransaction $accountTransaction): View
    {
        $this->authorize('update', $accountTransaction);

        return view('transactions.edit', [
            'transaction' => $accountTransaction,
            'accounts' => BranchScope::scopeQuery(Account::query(), $request->user())->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
            'categories' => TransactionCategory::query()->active()->orderBy('name')->get(['id', 'name', 'type']),
            'methods' => PaymentMethod::options(),
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

        // Strict branch isolation: entries stay on the user's own branch's
        // accounts (crafted requests may not re-point them elsewhere).
        $account = Account::query()->find((int) ($validated['account_id'] ?? $accountTransaction->account_id));

        if (! BranchScope::canAccess($request->user(), $account?->location_id)) {
            return back()->withInput()->with('error', 'You can only record entries on accounts of your own branch.');
        }

        try {
            DB::transaction(function () use ($accountTransaction, $validated, $balances): void {
                $locked = AccountTransaction::query()->whereKey($accountTransaction->id)->lockForUpdate()->firstOrFail();
                $wasCompleted = $locked->status === AccountEntryStatus::Completed;

                if ($wasCompleted) {
                    $balances->reverseTransaction($locked);
                }

                $locked->update($validated);

                if ($wasCompleted) {
                    $balances->applyTransaction($locked);
                }
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $accountTransaction->refresh();

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
            $locked = AccountTransaction::query()->whereKey($accountTransaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AccountEntryStatus::Completed) {
                $balances->reverseTransaction($locked);
            }

            $locked->delete();
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

        try {
            $processed = DB::transaction(function () use ($accountTransaction, $balances, $request): bool {
                $locked = AccountTransaction::query()->whereKey($accountTransaction->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== AccountEntryStatus::Pending) {
                    return false;
                }

                $locked->update([
                    'status' => AccountEntryStatus::Completed,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);

                $balances->applyTransaction($locked);

                return true;
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (! $processed) {
            return back()->with('error', 'Only pending transactions can be approved.');
        }

        $accountTransaction->refresh();

        AuditService::log('approved', $accountTransaction, [], $accountTransaction->toArray(), sprintf(
            'Approved %s entry %s of %s.',
            $accountTransaction->type->value,
            $accountTransaction->reference_no,
            Money::format($accountTransaction->amount)
        ));

        return back()->with('success', 'Transaction approved and account balance updated.');
    }

    /**
     * Reject a pending entry: it is cancelled and never moves money.
     */
    public function reject(Request $request, AccountTransaction $accountTransaction): RedirectResponse
    {
        $this->authorize('reject', $accountTransaction);

        $processed = DB::transaction(function () use ($accountTransaction, $request): bool {
            $locked = AccountTransaction::query()->whereKey($accountTransaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AccountEntryStatus::Pending) {
                return false;
            }

            $locked->update([
                'status' => AccountEntryStatus::Cancelled,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            return true;
        });

        if (! $processed) {
            return back()->with('error', 'Only pending transactions can be rejected.');
        }

        $accountTransaction->refresh();

        AuditService::log('rejected', $accountTransaction, [], $accountTransaction->toArray(), sprintf(
            'Rejected %s entry %s.',
            $accountTransaction->type->value,
            $accountTransaction->reference_no
        ));

        return back()->with('success', 'Transaction cancelled.');
    }
}
