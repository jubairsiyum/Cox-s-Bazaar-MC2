<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Enums\AccountTransactionType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * An accountant-style approver with full finance transaction rights, working
 * at the given branch (strict branch isolation).
 */
function financeApproverUser(?Location $location = null): User
{
    $user = User::factory()->create(['status' => 'active', 'branch_id' => $location?->id]);
    $user->assignRole('Accountant');

    return $user;
}

it('records a pending income entry without touching the balance', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Service',
        'amount' => '500',
        'transaction_date' => now()->toDateString(),
        'method' => 'cash',
    ])->assertRedirect(route('transactions.index'));

    $transaction = AccountTransaction::query()->firstOrFail();

    expect($transaction->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $account->fresh()->current_balance)->toBe(1000.0);
});

it('moves the balance only once a pending entry is approved', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Utility',
        'amount' => '400',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();

    expect((float) $account->fresh()->current_balance)->toBe(1000.0);

    $this->patch(route('transactions.approve', $transaction))
        ->assertSessionHas('success');

    $transaction->refresh();

    expect($transaction->status)->toBe(AccountEntryStatus::Completed)
        ->and($transaction->approved_by)->not->toBeNull()
        ->and((float) $account->fresh()->current_balance)->toBe(600.0);
});

it('cancelling a pending entry never moves money', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Commission',
        'amount' => '250',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();

    $this->patch(route('transactions.reject', $transaction))
        ->assertSessionHas('success');

    expect($transaction->fresh()->status)->toBe(AccountEntryStatus::Cancelled)
        ->and((float) $account->fresh()->current_balance)->toBe(1000.0);
});

it('blocks self-approval when another qualified approver exists', function () {
    $location = Location::factory()->create();

    $creator = financeApproverUser($location);
    $otherApprover = financeApproverUser($location);

    $account = Account::factory()->cash()->withBalance(0)->create(['location_id' => $location->id]);

    $this->actingAs($creator)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Scrap Sale',
        'amount' => '100',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();

    $this->actingAs($creator)
        ->patch(route('transactions.approve', $transaction))
        ->assertForbidden();

    expect($transaction->fresh()->status)->toBe(AccountEntryStatus::Pending);

    $this->actingAs($otherApprover)
        ->patch(route('transactions.approve', $transaction))
        ->assertSessionHas('success');

    expect($transaction->fresh()->status)->toBe(AccountEntryStatus::Completed);
});

it('allows self-approval when no other qualified approver exists', function () {
    $location = Location::factory()->create();

    $soleApprover = User::factory()->create(['status' => 'active', 'branch_id' => $location->id]);
    $soleApprover->givePermissionTo('income-transactions.manage');

    $account = Account::factory()->cash()->withBalance(0)->create(['location_id' => $location->id]);

    $this->actingAs($soleApprover)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Service',
        'amount' => '100',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();

    $this->actingAs($soleApprover)
        ->patch(route('transactions.approve', $transaction))
        ->assertSessionHas('success');

    expect($transaction->fresh()->status)->toBe(AccountEntryStatus::Completed)
        ->and((float) $account->fresh()->current_balance)->toBe(100.0);
});

it('keeps balances correct when a completed entry is updated and deleted', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(0)->create();

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Service',
        'amount' => '1000',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();
    $this->patch(route('transactions.approve', $transaction));

    expect((float) $account->fresh()->current_balance)->toBe(1000.0);

    // Update: reverse 1000, re-apply 1500.
    $this->patch(route('transactions.update', $transaction), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Service',
        'amount' => '1500',
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect(route('transactions.index'));

    expect((float) $account->fresh()->current_balance)->toBe(1500.0);

    // Delete: the completed entry gives its money back.
    $this->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    expect((float) $account->fresh()->current_balance)->toBe(0.0)
        ->and(AccountTransaction::query()->count())->toBe(0);
});

it('blocks an expense approval that would overdraw the account', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(100)->create();

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Rent',
        'amount' => '500',
        'transaction_date' => now()->toDateString(),
    ]);

    $transaction = AccountTransaction::query()->firstOrFail();

    $this->patch(route('transactions.approve', $transaction))
        ->assertSessionHas('error');

    expect($transaction->fresh()->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $account->fresh()->current_balance)->toBe(100.0);
});

it('gates income and expense management by their own permissions', function () {
    $location = Location::factory()->create();

    $expenseOnly = User::factory()->create(['status' => 'active', 'branch_id' => $location->id]);
    $expenseOnly->givePermissionTo('expense-transactions.manage');

    $account = Account::factory()->cash()->withBalance(1000)->create(['location_id' => $location->id]);

    // The expense manager may record expenses...
    $this->actingAs($expenseOnly)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Utility',
        'amount' => '100',
        'transaction_date' => now()->toDateString(),
    ])->assertRedirect(route('transactions.index'));

    // ...but never income.
    $this->actingAs($expenseOnly)->post(route('transactions.store'), [
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Service',
        'amount' => '100',
        'transaction_date' => now()->toDateString(),
    ])->assertForbidden();

    // A viewer may see the ledger but not write into it.
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->givePermissionTo('finance.view');

    $this->actingAs($viewer)
        ->get(route('transactions.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('transactions.store'), [
            'account_id' => $account->id,
            'type' => 'income',
            'category' => 'Service',
            'amount' => '100',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('freezes cancelled entries against edits', function () {
    $manager = User::factory()->create(['status' => 'active']);
    $manager->givePermissionTo('income-transactions.manage', 'expense-transactions.manage');

    $account = Account::factory()->cash()->withBalance(500)->create();
    $transaction = AccountTransaction::factory()->cancelled()->create([
        'account_id' => $account->id,
        'amount' => 100,
    ]);

    $this->actingAs($manager)
        ->get(route('transactions.edit', $transaction))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('transactions.update', $transaction), [
            'account_id' => $account->id,
            'type' => 'income',
            'category' => 'Service',
            'amount' => '100',
            'transaction_date' => now()->toDateString(),
        ])->assertForbidden();
});

it('blocks branch accountants from editing or deleting completed transactions', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Branch One']);
    $accountant = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branch->id]);
    $accountant->assignRole('Accountant');

    $branchCash = Account::factory()->cash()->withBalance(15000)->create([
        'location_id' => $branch->id,
        'name' => 'Till Branch One',
    ]);

    $completedTx = AccountTransaction::factory()->completed()->create([
        'account_id' => $branchCash->id,
        'location_id' => $branch->id,
        'type' => AccountTransactionType::Expense,
        'amount' => 500,
        'created_by' => $accountant->id,
    ]);

    $this->actingAs($accountant)->patch(route('transactions.update', $completedTx), [
        'account_id' => $branchCash->id,
        'type' => 'expense',
        'category' => 'Rent',
        'amount' => '5000',
        'transaction_date' => now()->toDateString(),
    ])->assertForbidden();

    $this->actingAs($accountant)->delete(route('transactions.destroy', $completedTx))
        ->assertForbidden();
});
