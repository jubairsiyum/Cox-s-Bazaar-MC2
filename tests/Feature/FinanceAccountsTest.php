<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists cash accounts with balance KPIs without leaking bank accounts', function () {
    actingAsSuperAdmin();

    $cash = Account::factory()->cash()->withBalance(5000)->create(['name' => 'Counter Cash Box']);
    $bank = Account::factory()->bank()->withBalance(7000)->create(['name' => 'City Bank Main']);

    $this->get(route('cash-accounts.index'))
        ->assertOk()
        ->assertSee('Cash Accounts')
        ->assertSee('Counter Cash Box')
        ->assertSee('5,000.00')
        ->assertDontSee('City Bank Main');

    $this->get(route('bank-accounts.index'))
        ->assertOk()
        ->assertSee('Bank Accounts')
        ->assertSee('City Bank Main')
        ->assertSee('7,000.00')
        ->assertDontSee('Counter Cash Box');

    expect($cash->fresh()->current_balance)->toEqual(5000)
        ->and($bank->fresh()->current_balance)->toEqual(7000);
});

it('creates a cash account with its opening balance as current balance', function () {
    actingAsSuperAdmin();

    $this->post(route('cash-accounts.store'), [
        'name' => 'Showroom Till',
        'account_number' => 'CASH-01',
        'opening_balance' => '2500.50',
        'status' => 'active',
    ])->assertRedirect(route('cash-accounts.index'));

    $account = Account::query()->where('name', 'Showroom Till')->firstOrFail();

    expect($account->type->value)->toBe('cash')
        ->and((float) $account->opening_balance)->toBe(2500.50)
        ->and((float) $account->current_balance)->toBe(2500.50);
});

it('keeps each section scoped to its own account type', function () {
    actingAsSuperAdmin();

    $bank = Account::factory()->bank()->create();

    // A bank account is unreachable through the cash screens.
    $this->get(route('cash-accounts.edit', $bank))->assertNotFound();
    $this->patch(route('cash-accounts.update', $bank), ['name' => 'Hacked', 'status' => 'active'])->assertNotFound();
    $this->delete(route('cash-accounts.destroy', $bank))->assertNotFound();

    // And its type can never be switched by editing.
    $this->patch(route('bank-accounts.update', $bank), [
        'name' => 'Renamed Bank',
        'type' => 'cash',
        'status' => 'active',
    ])->assertRedirect(route('bank-accounts.index'));

    expect($bank->fresh()->type->value)->toBe('bank');
});

it('gates cash and bank account management by permission', function () {
    $cashier = User::factory()->create();
    $cashier->givePermissionTo('cash-accounts.manage');

    $this->actingAs($cashier)
        ->get(route('cash-accounts.create'))
        ->assertOk();

    $this->actingAs($cashier)
        ->post(route('cash-accounts.store'), ['name' => 'Cash Box', 'opening_balance' => 0, 'status' => 'active'])
        ->assertRedirect(route('cash-accounts.index'));

    // Without bank-accounts.manage the bank section stays closed.
    $this->actingAs($cashier)
        ->get(route('bank-accounts.create'))
        ->assertForbidden();

    $this->actingAs($cashier)
        ->post(route('bank-accounts.store'), ['name' => 'Sneaky Bank', 'opening_balance' => 0, 'status' => 'active'])
        ->assertForbidden();

    // Viewers can read but never write.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('finance.view');

    $this->actingAs($viewer)
        ->get(route('cash-accounts.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('cash-accounts.store'), ['name' => 'Nope', 'opening_balance' => 0, 'status' => 'active'])
        ->assertForbidden();
});

it('shifts the current balance by the opening balance delta on update', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    $this->patch(route('cash-accounts.update', $account), [
        'name' => $account->name,
        'opening_balance' => '1500',
        'status' => 'active',
    ])->assertRedirect(route('cash-accounts.index'));

    $account->refresh();

    expect((float) $account->opening_balance)->toBe(1500.0)
        ->and((float) $account->current_balance)->toBe(1500.0);
});

it('deletes unused accounts but protects accounts with recorded entries', function () {
    actingAsSuperAdmin();

    $unused = Account::factory()->cash()->create();

    $this->delete(route('cash-accounts.destroy', $unused))
        ->assertRedirect(route('cash-accounts.index'));

    expect(Account::withTrashed()->find($unused->id)?->trashed())->toBeTrue();

    $used = Account::factory()->cash()->create();
    AccountTransaction::factory()->completed()->income()->create([
        'account_id' => $used->id,
        'amount' => 100,
    ]);

    $this->delete(route('cash-accounts.destroy', $used))
        ->assertSessionHas('error');

    expect($used->fresh())->not->toBeNull();
});

it('renders the account screens with the shared page furniture', function () {
    actingAsSuperAdmin();

    Account::factory()->cash()->create(['name' => 'Counter Cash Box']);

    $this->get(route('cash-accounts.index'))
        ->assertOk()
        ->assertSee('Total Cash Balance')
        ->assertSee('Active Accounts')
        ->assertSee('Counter Cash Box')
        ->assertSee('Delete');
});
