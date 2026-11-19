<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('records a pending transfer and moves money only after approval', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(5000)->create();
    $to = Account::factory()->bank()->withBalance(0)->create();

    $this->post(route('account-transfers.store'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '2000',
        'transfer_date' => now()->toDateString(),
        'method' => 'bank',
    ])->assertRedirect(route('account-transfers.index'));

    $transfer = AccountTransfer::query()->firstOrFail();

    expect($transfer->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $from->fresh()->current_balance)->toBe(5000.0)
        ->and((float) $to->fresh()->current_balance)->toBe(0.0);

    $this->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('success');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Completed)
        ->and((float) $from->fresh()->current_balance)->toBe(3000.0)
        ->and((float) $to->fresh()->current_balance)->toBe(2000.0);
});

it('refuses transfers between the same account', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(5000)->create();

    $this->post(route('account-transfers.store'), [
        'from_account_id' => $account->id,
        'to_account_id' => $account->id,
        'amount' => '100',
        'transfer_date' => now()->toDateString(),
    ])->assertSessionHasErrors('from_account_id');

    expect(AccountTransfer::query()->count())->toBe(0);
});

it('blocks approving a transfer larger than the source balance', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(500)->create();
    $to = Account::factory()->bank()->withBalance(0)->create();

    $this->post(route('account-transfers.store'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '1000',
        'transfer_date' => now()->toDateString(),
    ]);

    $transfer = AccountTransfer::query()->firstOrFail();

    $this->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('error');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $from->fresh()->current_balance)->toBe(500.0)
        ->and((float) $to->fresh()->current_balance)->toBe(0.0);
});

it('cancels a pending transfer without moving money', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(500)->create();
    $to = Account::factory()->bank()->withBalance(0)->create();

    $this->post(route('account-transfers.store'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '200',
        'transfer_date' => now()->toDateString(),
    ]);

    $transfer = AccountTransfer::query()->firstOrFail();

    $this->patch(route('account-transfers.reject', $transfer))
        ->assertSessionHas('success');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Cancelled)
        ->and((float) $from->fresh()->current_balance)->toBe(500.0)
        ->and((float) $to->fresh()->current_balance)->toBe(0.0);
});

it('restores both balances when a completed transfer is deleted', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(3000)->create();
    $to = Account::factory()->bank()->withBalance(2000)->create();

    // Already-completed transfer of 2000: from was 5000 before, to was 0 before.
    $transfer = AccountTransfer::factory()->completed()->create([
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 2000,
    ]);

    $this->delete(route('account-transfers.destroy', $transfer))
        ->assertRedirect(route('account-transfers.index'));

    expect(AccountTransfer::query()->count())->toBe(0)
        ->and((float) $from->fresh()->current_balance)->toBe(5000.0)
        ->and((float) $to->fresh()->current_balance)->toBe(0.0);
});

it('re-applies balances when a completed transfer is edited', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(3000)->create();
    $to = Account::factory()->bank()->withBalance(2000)->create();

    $transfer = AccountTransfer::factory()->completed()->create([
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 2000,
    ]);

    $this->patch(route('account-transfers.update', $transfer), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '1000',
        'transfer_date' => now()->toDateString(),
    ])->assertRedirect(route('account-transfers.index'));

    // Reversed the 2000 move (5000 / 0), then applied the 1000 move.
    expect((float) $from->fresh()->current_balance)->toBe(4000.0)
        ->and((float) $to->fresh()->current_balance)->toBe(1000.0);
});

it('blocks self-approval of transfers when another approver exists', function () {
    $creator = User::factory()->create(['status' => 'active']);
    $creator->assignRole('Accountant');
    $otherApprover = User::factory()->create(['status' => 'active']);
    $otherApprover->assignRole('Accountant');

    $from = Account::factory()->cash()->withBalance(500)->create();
    $to = Account::factory()->bank()->withBalance(0)->create();

    $this->actingAs($creator)->post(route('account-transfers.store'), [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '100',
        'transfer_date' => now()->toDateString(),
    ]);

    $transfer = AccountTransfer::query()->firstOrFail();

    $this->actingAs($creator)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertForbidden();

    $this->actingAs($otherApprover)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('success');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Completed);
});

it('gates transfer management by permission', function () {
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->givePermissionTo('finance.view');

    $this->actingAs($viewer)
        ->get(route('account-transfers.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('account-transfers.create'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('account-transfers.store'), [
            'from_account_id' => Account::factory()->create()->id,
            'to_account_id' => Account::factory()->create()->id,
            'amount' => '10',
            'transfer_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});
