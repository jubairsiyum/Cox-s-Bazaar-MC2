<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientAccountBalanceException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\Setting;

/**
 * All writes to `accounts.current_balance` go through this service so money
 * movement is applied exactly once, atomically, with row locks and an
 * insufficient-funds guard.
 */
class AccountBalanceService
{
    /**
     * Apply a completed transaction's effect to its account balance.
     */
    public function applyTransaction(AccountTransaction $transaction): void
    {
        $amount = round((float) $transaction->amount, 2);

        if ($transaction->isIncome()) {
            $this->credit($transaction->account_id, $amount);

            return;
        }

        $this->debit($transaction->account_id, $amount);
    }

    /**
     * Undo a previously applied transaction (edit/delete), restoring the
     * balance it moved. Corrections are never blocked on funds.
     */
    public function reverseTransaction(AccountTransaction $transaction): void
    {
        $amount = round((float) $transaction->amount, 2);

        if ($transaction->isIncome()) {
            $this->adjust($transaction->account_id, -$amount);

            return;
        }

        $this->credit($transaction->account_id, $amount);
    }

    /**
     * Move a completed transfer's amount from its source to its destination.
     */
    public function applyTransfer(AccountTransfer $transfer): void
    {
        $amount = round((float) $transfer->amount, 2);

        $this->debit($transfer->from_account_id, $amount);
        $this->credit($transfer->to_account_id, $amount);
    }

    /**
     * Undo a previously applied transfer: money flows back from → to.
     */
    public function reverseTransfer(AccountTransfer $transfer): void
    {
        $amount = round((float) $transfer->amount, 2);

        $this->adjust($transfer->to_account_id, -$amount);
        $this->credit($transfer->from_account_id, $amount);
    }

    /**
     * Put money into an account.
     */
    public function credit(Account|int $account, float $amount): void
    {
        $this->adjust($account, abs($amount));
    }

    /**
     * Take money out of an account. Throws when the withdrawal would overdraw
     * the account and negative balances are not allowed by settings.
     */
    public function debit(Account|int $account, float $amount): void
    {
        $amount = abs($amount);
        $locked = $this->lock($account);
        $available = (float) $locked->current_balance;

        if ($available - $amount < 0 && ! $this->negativeBalancesAllowed()) {
            throw InsufficientAccountBalanceException::for($locked->name, $available, $amount);
        }

        $this->saveBalance($locked, -$amount);
    }

    /**
     * Apply a signed balance change without any funds guard (corrections).
     */
    public function adjust(Account|int $account, float $delta): void
    {
        $this->saveBalance($this->lock($account), $delta);
    }

    /**
     * Whether settings permit accounts to go below zero.
     */
    public function negativeBalancesAllowed(): bool
    {
        return (string) Setting::get('allow_negative_balance', '0') === '1';
    }

    /**
     * Lock the account row so concurrent writers serialize on the balance.
     */
    private function lock(Account|int $account): Account
    {
        $id = $account instanceof Account ? $account->getKey() : $account;

        return Account::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function saveBalance(Account $account, float $delta): void
    {
        $account->current_balance = round((float) $account->current_balance + $delta, 2);
        $account->save();
    }
}
