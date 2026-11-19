<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cash & bank account management. The same screen serves both the
 * "Cash Accounts" and "Bank Accounts" sections — the route's `type` default
 * scopes every action to one account type.
 */
class AccountController extends Controller
{
    /**
     * List the accounts of one type with balance KPIs.
     */
    public function index(Request $request, string $type): View
    {
        $accountType = $this->accountType($type);

        $this->authorize('viewAny', Account::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $accounts = Account::query()
            ->type($accountType)
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->with('location')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $balanceTotals = Account::query()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'cash' THEN current_balance ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN type = 'bank' THEN current_balance ELSE 0 END), 0) as bank_total,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active_count,
                COUNT(*) as total_count
            ")
            ->first();

        $monthMovement = AccountTransaction::query()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense_total
            ")
            ->where('status', 'completed')
            ->whereHas('account', fn ($q) => $q->type($accountType))
            ->whereBetween('transaction_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->first();

        return view('accounts.index', [
            'type' => $accountType,
            'accounts' => $accounts,
            'perPage' => $perPage,
            'cashTotal' => (float) ($balanceTotals->cash_total ?? 0),
            'bankTotal' => (float) ($balanceTotals->bank_total ?? 0),
            'activeCount' => (int) ($balanceTotals->active_count ?? 0),
            'totalCount' => (int) ($balanceTotals->total_count ?? 0),
            'monthIncome' => (float) ($monthMovement->income_total ?? 0),
            'monthExpense' => (float) ($monthMovement->expense_total ?? 0),
        ]);
    }

    /**
     * Show the form for creating a cash or bank account.
     */
    public function create(string $type): View
    {
        $accountType = $this->accountType($type);

        $this->authorize('create', [Account::class, $accountType]);

        return view('accounts.create', [
            'type' => $accountType,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created cash or bank account.
     */
    public function store(StoreAccountRequest $request, string $type): RedirectResponse
    {
        $accountType = $this->accountType($type);

        $validated = $request->validated();
        $validated['type'] = $accountType->value;
        $validated['opening_balance'] = round((float) ($validated['opening_balance'] ?? 0), 2);
        $validated['current_balance'] = $validated['opening_balance'];
        $validated['created_by'] = $request->user()?->id;

        $account = Account::create($validated);

        AuditService::created($account, sprintf("%s account '%s' created.", $accountType->label(), $account->name));

        return redirect()->route($this->indexRoute($type))
            ->with('success', sprintf("%s account '%s' created successfully.", $accountType->label(), $account->name));
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit(Account $account, string $type): View
    {
        $this->scopedAccount($account, $type);
        $this->authorize('update', $account);

        return view('accounts.edit', [
            'type' => $account->type,
            'account' => $account,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified account. Opening balance changes shift the current
     * balance by the same delta so the two never drift apart.
     */
    public function update(UpdateAccountRequest $request, Account $account, string $type): RedirectResponse
    {
        $this->scopedAccount($account, $type);

        $original = $account->getOriginal();
        $validated = $request->validated();
        $validated['type'] = $account->type->value;

        $openingDelta = round((float) ($validated['opening_balance'] ?? 0) - (float) $account->opening_balance, 2);

        DB::transaction(function () use ($account, $validated, $openingDelta): void {
            $account->fill($validated);

            if ($openingDelta !== 0.0) {
                $account->current_balance = round((float) $account->current_balance + $openingDelta, 2);
            }

            $account->save();
        });

        AuditService::updated($account, $original, sprintf("Account '%s' updated.", $account->name));

        return redirect()->route($this->indexRoute($type))
            ->with('success', sprintf("Account '%s' updated successfully.", $account->name));
    }

    /**
     * Delete an account. Accounts with recorded financial entries are kept and
     * can only be marked inactive — money history must not be lost.
     */
    public function destroy(Account $account, string $type): RedirectResponse
    {
        $this->scopedAccount($account, $type);
        $this->authorize('delete', $account);

        $hasEntries = $account->transactions()->exists()
            || $account->outgoingTransfers()->exists()
            || $account->incomingTransfers()->exists()
            || $account->supplierPayments()->exists();

        if ($hasEntries) {
            return back()->with('error', "Account '{$account->name}' has recorded financial entries and cannot be deleted. Set it to inactive instead.");
        }

        $account->delete();

        AuditService::deleted($account, sprintf("Account '%s' deleted.", $account->name));

        return redirect()->route($this->indexRoute($type))
            ->with('success', sprintf("Account '%s' deleted successfully.", $account->name));
    }

    /**
     * Validate the route type segment and return its enum value.
     */
    private function accountType(string $type): AccountType
    {
        return AccountType::from($type);
    }

    /**
     * Guard against reaching a bank account through the cash screens and vice
     * versa.
     */
    private function scopedAccount(Account $account, string $type): void
    {
        abort_unless($account->type->value === $type, 404);
    }

    /**
     * Index route name of the section the request came from.
     */
    private function indexRoute(string $type): string
    {
        return "{$type}-accounts.index";
    }
}
