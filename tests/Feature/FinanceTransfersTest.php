<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Location;
use App\Models\SalesInvoice;
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
    $location = Location::factory()->create();

    $creator = User::factory()->create(['status' => 'active', 'branch_id' => $location->id]);
    $creator->assignRole('Accountant');
    $otherApprover = User::factory()->create(['status' => 'active', 'branch_id' => $location->id]);
    $otherApprover->assignRole('Accountant');

    $from = Account::factory()->cash()->withBalance(500)->create(['location_id' => $location->id]);
    $to = Account::factory()->bank()->withBalance(0)->create(['location_id' => $location->id]);

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

it('allows a branch manager to remit cash to a central HQ account and requires HQ approval', function () {
    $branchLocation = Location::factory()->create();

    $branchManager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branchLocation->id,
    ]);
    $branchManager->givePermissionTo(['finance.view', 'finance.manage', 'account-transfers.manage']);

    $branchCash = Account::factory()->cash()->withBalance(10000)->create([
        'location_id' => $branchLocation->id,
        'name' => 'Branch Till',
    ]);

    // Central HQ Account (no location_id, company-wide bank)
    $hqBank = Account::factory()->bank()->withBalance(50000)->create([
        'location_id' => null,
        'name' => 'HQ Central Bank',
    ]);

    // Branch manager records remittance
    $this->actingAs($branchManager)->post(route('account-transfers.store'), [
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'amount' => '5000',
        'transfer_date' => now()->toDateString(),
        'method' => 'cash',
        'note' => 'Daily branch cash remittance to HQ',
    ])->assertRedirect(route('account-transfers.index'));

    $transfer = AccountTransfer::query()->latest('id')->firstOrFail();

    expect($transfer->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $branchCash->fresh()->current_balance)->toBe(10000.0)
        ->and((float) $hqBank->fresh()->current_balance)->toBe(50000.0);

    // Branch manager CANNOT approve their own remittance to HQ
    $this->actingAs($branchManager)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertForbidden();

    // Super Admin / HQ Accountant approves remittance
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('success');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Completed)
        ->and((float) $branchCash->fresh()->current_balance)->toBe(5000.0)
        ->and((float) $hqBank->fresh()->current_balance)->toBe(55000.0);

    // Once completed, Super Admin viewing index should NOT see Approve or Reject buttons
    $this->actingAs($admin)
        ->get(route('account-transfers.index'))
        ->assertOk()
        ->assertSee('Completed')
        ->assertDontSeeText('Approve')
        ->assertDontSeeText('Reject');
});

it('blocks a branch manager from transferring to an arbitrary third branch account', function () {
    $branchLocationA = Location::factory()->brandShop()->create();
    $branchLocationB = Location::factory()->brandShop()->create();

    $managerA = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branchLocationA->id,
    ]);
    $managerA->givePermissionTo(['finance.manage', 'account-transfers.manage']);

    $cashA = Account::factory()->cash()->withBalance(5000)->create([
        'location_id' => $branchLocationA->id,
    ]);
    $cashB = Account::factory()->cash()->withBalance(5000)->create([
        'location_id' => $branchLocationB->id,
    ]);

    $this->actingAs($managerA)->post(route('account-transfers.store'), [
        'from_account_id' => $cashA->id,
        'to_account_id' => $cashB->id,
        'amount' => '1000',
        'transfer_date' => now()->toDateString(),
    ])->assertSessionHas('error');

    expect(AccountTransfer::query()->count())->toBe(0);
});

it('shows remaining branch payable to HQ and pre-fills amount on transfer create form', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Branch CTG']);

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.manage', 'account-transfers.manage']);

    Account::factory()->cash()->withBalance(200000)->create([
        'location_id' => $branch->id,
        'name' => 'Till CTG',
    ]);

    // Live sales of 90,000 today
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 90000,
        'paid_amount' => 90000,
        'due_amount' => 0,
        'invoice_date' => now()->toDateString(),
    ]);

    $this->actingAs($manager)
        ->get(route('account-transfers.create'))
        ->assertOk()
        ->assertSeeText('Remaining Payable to Central HQ')
        ->assertSee('90,000.00')
        ->assertSee('value="90000.00"', false);
});

it('sends in-app notifications to Central HQ and creator on cash remittance lifecycle', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Branch CTG']);

    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.manage', 'account-transfers.manage']);

    $branchCash = Account::factory()->cash()->withBalance(50000)->create([
        'location_id' => $branch->id,
        'name' => 'Till CTG',
    ]);
    $hqBank = Account::factory()->bank()->withBalance(100000)->create([
        'location_id' => null,
        'name' => 'Central Bank',
    ]);

    // 1. Manager submits cash remittance of 30,000
    $this->actingAs($manager)->post(route('account-transfers.store'), [
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'amount' => '30000',
        'transfer_date' => now()->toDateString(),
    ])->assertRedirect(route('account-transfers.index'));

    $transfer = AccountTransfer::query()->latest('id')->firstOrFail();

    // Admin received notification awaiting approval
    $adminNotif = $admin->notifications()->latest()->first();
    expect($adminNotif)->not->toBeNull()
        ->and($adminNotif->data['title'])->toContain('Cash Remittance Awaits Approval')
        ->and($adminNotif->data['body'])->toContain('30,000');

    // Manager received confirmation
    $managerNotif = $manager->notifications()->latest()->first();
    expect($managerNotif)->not->toBeNull()
        ->and($managerNotif->data['title'])->toContain('Cash Remittance Submitted')
        ->and($managerNotif->data['body'])->toContain('30,000');

    // 2. Admin approves the remittance
    $this->actingAs($admin)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('success');

    $approvedNotif = $manager->notifications()
        ->where('data->title', 'like', '%Cash Remittance Approved%')
        ->first();
    expect($approvedNotif)->not->toBeNull()
        ->and($approvedNotif->data['title'])->toContain('Cash Remittance Approved')
        ->and($approvedNotif->data['body'])->toContain('30,000');
});
