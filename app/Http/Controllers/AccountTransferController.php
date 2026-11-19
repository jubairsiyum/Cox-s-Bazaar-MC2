<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountEntryStatus;
use App\Exceptions\InsufficientAccountBalanceException;
use App\Http\Requests\AccountTransfer\StoreAccountTransferRequest;
use App\Http\Requests\AccountTransfer\UpdateAccountTransferRequest;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Services\AccountBalanceService;
use App\Services\AuditService;
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
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountTransfer::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $transfers = AccountTransfer::query()
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

        $monthTotals = AccountTransfer::query()
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
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type']),
            'monthCount' => (int) ($monthTotals->transfer_count ?? 0),
            'monthCompleted' => (float) ($monthTotals->completed_total ?? 0),
            'pendingCount' => (int) ($monthTotals->pending_count ?? 0),
        ]);
    }

    /**
     * Show the form for creating a transfer request.
     */
    public function create(): View
    {
        $this->authorize('create', AccountTransfer::class);

        return view('account-transfers.create', [
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
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

        $transfer = AccountTransfer::create($validated);

        AuditService::created($transfer, sprintf(
            'Transfer of %s from %s to %s recorded, awaiting approval.',
            number_format((float) $transfer->amount, 2),
            $transfer->fromAccount?->name ?? '',
            $transfer->toAccount?->name ?? ''
        ));

        return redirect()->route('account-transfers.index')
            ->with('success', 'Transfer recorded and queued for approval.');
    }

    /**
     * Show the form for editing a transfer.
     */
    public function edit(AccountTransfer $accountTransfer): View
    {
        $this->authorize('update', $accountTransfer);

        return view('account-transfers.edit', [
            'transfer' => $accountTransfer,
            'accounts' => Account::query()->active()->orderBy('name')->get(['id', 'name', 'type', 'current_balance']),
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
            number_format((float) $accountTransfer->amount, 2),
            $accountTransfer->fromAccount?->name ?? '',
            $accountTransfer->toAccount?->name ?? ''
        ));

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

        return back()->with('success', 'Transfer cancelled.');
    }
}
