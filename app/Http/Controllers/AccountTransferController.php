<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountEntryStatus;
use App\Enums\AccountType;
use App\Enums\LocationType;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Helpers\Money;
use App\Http\Requests\AccountTransfer\StoreAccountTransferRequest;
use App\Http\Requests\AccountTransfer\UpdateAccountTransferRequest;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\PaymentMethod;
use App\Services\AccountBalanceService;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\DayClosingService;
use App\Services\RemittanceNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Money movements between the shop's own cash/bank accounts, with the same
 * pending → completed/cancelled approval workflow as account transactions.
 */
class AccountTransferController extends Controller
{
    /**
     * List account transfers with KPI summary cards.
     * Strict branch isolation: only the user's own branch's transfers (and KPIs).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountTransfer::class);

        $user = $request->user();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $transfersQuery = AccountTransfer::query();
        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $transfersQuery->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('location_id', $branchId)
                        ->orWhereHas('toAccount', fn ($tq) => $tq->where('location_id', $branchId));
                }
            });
        }

        $transfers = $transfersQuery
            ->with(['fromAccount', 'toAccount', 'creator', 'approver'])
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('account_id'), function ($q) use ($request) {
                $accountId = (int) $request->input('account_id');
                $q->where(fn ($qq) => $qq->where('from_account_id', $accountId)->orWhere('to_account_id', $accountId));
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('transfer_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('transfer_date', '<=', $request->input('date_to')))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $monthTotalsQuery = AccountTransfer::query();
        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $monthTotalsQuery->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('location_id', $branchId)
                        ->orWhereHas('toAccount', fn ($tq) => $tq->where('location_id', $branchId));
                }
            });
        }

        $monthTotals = $monthTotalsQuery
            ->selectRaw("
                COUNT(*) as transfer_count,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed_total,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count
            ")
            ->whereBetween('transfer_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->first();

        return view('account-transfers.index', [
            'transfers' => $transfers,
            'perPage' => $perPage,
            'accounts' => BranchScope::scopeQueryWithShared(Account::query(), $user)->active()->orderBy('name')->get(['id', 'name', 'type']),
            'monthCount' => (int) ($monthTotals->transfer_count ?? 0),
            'monthCompleted' => (float) ($monthTotals->completed_total ?? 0),
            'pendingCount' => (int) ($monthTotals->pending_count ?? 0),
        ]);
    }

    /**
     * Show the form for creating a transfer request.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', AccountTransfer::class);

        $user = $request->user();

        // Ensure the branch has a dedicated cash till account if user is assigned to a branch
        if ($user && $user->branch_id !== null) {
            Account::firstOrCreate(
                [
                    'location_id' => $user->branch_id,
                    'type' => AccountType::Cash,
                ],
                [
                    'name' => 'Cash Till - '.($user->branch?->name ?? 'Branch '.$user->branch_id),
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'status' => 'active',
                    'note' => 'Primary cash till for '.($user->branch?->name ?? 'Branch'),
                ]
            );
        }

        $fromAccounts = BranchScope::scopeQuery(Account::query(), $user)->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);

        $toAccountsQuery = Account::query()->active();
        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $toAccountsQuery->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('location_id', $branchId);
                }
                $q->orWhereNull('location_id')
                    ->orWhereHas('location', fn ($l) => $l->where('type', LocationType::Warehouse));
            });
        }
        $toAccounts = $toAccountsQuery->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);

        $branchId = BranchScope::locationId($user);
        $payableSummary = null;
        $remainingPayable = 0.0;

        if ($branchId !== null) {
            $payableSummary = DayClosingService::getPayableToHqSummary($branchId);
            $remainingPayable = (float) $payableSummary['left_amount'];
        } elseif (BranchScope::isUnrestricted($user) && $request->filled('location_id')) {
            $locId = (int) $request->input('location_id');
            $payableSummary = DayClosingService::getPayableToHqSummary($locId);
            $remainingPayable = (float) $payableSummary['left_amount'];
        }

        return view('account-transfers.create', [
            'accounts' => $toAccounts,
            'fromAccounts' => $fromAccounts,
            'toAccounts' => $toAccounts,
            'methods' => PaymentMethod::options(),
            'payableSummary' => $payableSummary,
            'remainingPayable' => $remainingPayable,
        ]);
    }

    /**
     * Store a new transfer as pending — money moves only once approved.
     */
    public function store(StoreAccountTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['amount'] = round((float) $validated['amount'], 2);
        $validated['status'] = AccountEntryStatus::Pending->value;
        $validated['created_by'] = $request->user()?->id;

        $fromAccount = Account::query()->with('location')->find((int) $validated['from_account_id']);
        $toAccount = Account::query()->with('location')->find((int) $validated['to_account_id']);

        if (! $fromAccount || ! $toAccount) {
            return back()->withInput()->with('error', 'Selected accounts could not be found.');
        }

        // The sending account must belong to the user's accessible branch.
        if (! BranchScope::canAccess($request->user(), $fromAccount->location_id)) {
            return back()->withInput()->with('error', 'You can only transfer from accounts of your own branch.');
        }

        // The receiving account may belong to the same branch or be a Central HQ/shared account (Remittance).
        $isDestinationBranch = BranchScope::canAccess($request->user(), $toAccount->location_id);
        $isDestinationHq = $toAccount->location_id === null || $toAccount->location?->type === LocationType::Warehouse;

        if (! $isDestinationBranch && ! $isDestinationHq) {
            return back()->withInput()->with('error', 'You can only transfer to your own branch accounts or Central HQ accounts.');
        }

        $validated['location_id'] = $fromAccount->location_id;

        $transfer = AccountTransfer::create($validated);

        RemittanceNotifier::submitted($transfer);

        AuditService::created($transfer, sprintf(
            'Transfer of %s from %s to %s recorded, awaiting approval.',
            Money::format($transfer->amount),
            $transfer->fromAccount?->name ?? '',
            $transfer->toAccount?->name ?? ''
        ));

        return redirect()->route('account-transfers.index')
            ->with('success', 'Transfer recorded and queued for approval.');
    }

    /**
     * Show the form for editing a transfer.
     */
    public function edit(Request $request, AccountTransfer $accountTransfer): View
    {
        $this->authorize('update', $accountTransfer);

        $user = $request->user();

        // Ensure the branch has a dedicated cash till account if user is assigned to a branch
        if ($user && $user->branch_id !== null) {
            Account::firstOrCreate(
                [
                    'location_id' => $user->branch_id,
                    'type' => AccountType::Cash,
                ],
                [
                    'name' => 'Cash Till - '.($user->branch?->name ?? 'Branch '.$user->branch_id),
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'status' => 'active',
                    'note' => 'Primary cash till for '.($user->branch?->name ?? 'Branch'),
                ]
            );
        }

        $fromAccounts = BranchScope::scopeQuery(Account::query(), $user)->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);

        $toAccountsQuery = Account::query()->active();
        if (BranchScope::isRestricted($user)) {
            $branchId = BranchScope::locationId($user);
            $toAccountsQuery->where(function ($q) use ($branchId) {
                if ($branchId !== null) {
                    $q->where('location_id', $branchId);
                }
                $q->orWhereNull('location_id')
                    ->orWhereHas('location', fn ($l) => $l->where('type', LocationType::Warehouse));
            });
        }
        $toAccounts = $toAccountsQuery->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);

        return view('account-transfers.edit', [
            'transfer' => $accountTransfer,
            'accounts' => $toAccounts,
            'fromAccounts' => $fromAccounts,
            'toAccounts' => $toAccounts,
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Update a transfer. A completed transfer is reversed first and re-applied
     * after the change so balances are never double-counted.
     */
    public function update(UpdateAccountTransferRequest $request, AccountTransfer $accountTransfer, AccountBalanceService $balances): RedirectResponse
    {
        $original = $accountTransfer->getOriginal();
        $validated = $request->validated();
        $validated['amount'] = round((float) $validated['amount'], 2);

        // Keep the auto-generated document number when left blank.
        if (blank($validated['reference_no'] ?? null)) {
            $validated['reference_no'] = $accountTransfer->reference_no;
        }

        $wasCompleted = $accountTransfer->status === AccountEntryStatus::Completed;

        $fromAccountId = (int) ($validated['from_account_id'] ?? $accountTransfer->from_account_id);
        $toAccountId = (int) ($validated['to_account_id'] ?? $accountTransfer->to_account_id);

        $fromAccount = Account::query()->with('location')->find($fromAccountId);
        $toAccount = Account::query()->with('location')->find($toAccountId);

        if (! $fromAccount || ! $toAccount) {
            return back()->withInput()->with('error', 'Selected accounts could not be found.');
        }

        if (! BranchScope::canAccess($request->user(), $fromAccount->location_id)) {
            return back()->withInput()->with('error', 'You can only transfer from accounts of your own branch.');
        }

        $isDestinationBranch = BranchScope::canAccess($request->user(), $toAccount->location_id);
        $isDestinationHq = $toAccount->location_id === null || $toAccount->location?->type === LocationType::Warehouse;

        if (! $isDestinationBranch && ! $isDestinationHq) {
            return back()->withInput()->with('error', 'You can only transfer to your own branch accounts or Central HQ accounts.');
        }

        try {
            DB::transaction(function () use ($accountTransfer, $validated, $balances, $wasCompleted): void {
                if ($wasCompleted) {
                    $balances->reverseTransfer($accountTransfer);
                }

                $accountTransfer->update($validated);

                if ($wasCompleted) {
                    $balances->applyTransfer($accountTransfer);
                }
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditService::updated($accountTransfer, $original, sprintf('Transfer %s updated.', $accountTransfer->reference_no));

        return redirect()->route('account-transfers.index')
            ->with('success', 'Transfer updated successfully.');
    }

    /**
     * Delete a transfer. Completed transfers move their money back first.
     */
    public function destroy(AccountTransfer $accountTransfer, AccountBalanceService $balances): RedirectResponse
    {
        $this->authorize('delete', $accountTransfer);

        $original = $accountTransfer->toArray();

        DB::transaction(function () use ($accountTransfer, $balances): void {
            if ($accountTransfer->status === AccountEntryStatus::Completed) {
                $balances->reverseTransfer($accountTransfer);
            }

            $accountTransfer->delete();
        });

        AuditService::log('deleted', null, $original, [], sprintf('Deleted transfer %s and restored both balances.', $accountTransfer->reference_no));

        return redirect()->route('account-transfers.index')
            ->with('success', 'Transfer deleted and both account balances restored.');
    }

    /**
     * Approve a pending transfer: the amount moves from → to atomically.
     */
    public function approve(Request $request, AccountTransfer $accountTransfer, AccountBalanceService $balances): RedirectResponse
    {
        $this->authorize('approve', $accountTransfer);

        if ($accountTransfer->status !== AccountEntryStatus::Pending) {
            return back()->with('error', 'Only pending transfers can be approved.');
        }

        if ((int) $accountTransfer->from_account_id === (int) $accountTransfer->to_account_id) {
            return back()->with('error', 'A transfer must move money between two different accounts.');
        }

        try {
            DB::transaction(function () use ($accountTransfer, $balances, $request): void {
                $accountTransfer->update([
                    'status' => AccountEntryStatus::Completed,
                    'approved_by' => $request->user()?->id,
                    'approved_at' => now(),
                ]);

                $balances->applyTransfer($accountTransfer);
            });
        } catch (InsufficientAccountBalanceException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        AuditService::log('approved', $accountTransfer, [], $accountTransfer->toArray(), sprintf(
            'Approved transfer %s of %s from %s to %s.',
            $accountTransfer->reference_no,
            Money::format($accountTransfer->amount),
            $accountTransfer->fromAccount?->name ?? '',
            $accountTransfer->toAccount?->name ?? ''
        ));

        RemittanceNotifier::approved($accountTransfer, $request->user());

        return back()->with('success', 'Transfer completed and both balances updated.');
    }

    /**
     * Reject a pending transfer: nothing ever moves.
     */
    public function reject(Request $request, AccountTransfer $accountTransfer): RedirectResponse
    {
        $this->authorize('reject', $accountTransfer);

        if ($accountTransfer->status !== AccountEntryStatus::Pending) {
            return back()->with('error', 'Only pending transfers can be rejected.');
        }

        $accountTransfer->update([
            'status' => AccountEntryStatus::Cancelled,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        AuditService::log('rejected', $accountTransfer, [], $accountTransfer->toArray(), sprintf('Rejected transfer %s.', $accountTransfer->reference_no));

        RemittanceNotifier::rejected($accountTransfer, $request->user());

        return back()->with('success', 'Transfer cancelled.');
    }
}
