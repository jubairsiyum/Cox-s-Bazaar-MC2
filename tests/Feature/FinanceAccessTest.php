<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Route names of the finance module pages.
 *
 * @return array<int, string>
 */
function financeRouteNames(): array
{
    return [
        'cash-accounts.index',
        'bank-accounts.index',
        'transactions.index',
        'account-transfers.index',
        'supplier-payments.index',
    ];
}

it('hides the finance menu for a storekeeper without finance permissions', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Finance')
        ->assertDontSee('Cash Accounts')
        ->assertDontSee('Bank Accounts');
});

it('shows the finance menu when finance permissions are granted', function () {
    $user = User::factory()->create();
    $user->assignRole('Storekeeper');
    $user->givePermissionTo('finance.view');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Finance')
        ->assertSee('Cash Accounts')
        ->assertSee('Transactions')
        ->assertSee('Supplier Payments');
});

it('shows the finance menu for a Super Admin', function () {
    actingAsSuperAdmin();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Finance')
        ->assertSee('Cash Accounts');
});

it('blocks a storekeeper from accessing finance pages directly', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper);

    foreach (financeRouteNames() as $routeName) {
        $this->get(route($routeName))->assertForbidden();
    }
});

it('allows an accountant to access finance pages', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant);

    foreach (financeRouteNames() as $routeName) {
        $this->get(route($routeName))->assertOk();
    }
});

it('redirects the legacy income and expense pages to the merged transactions section', function () {
    actingAsSuperAdmin();

    $this->get('/income-transactions')->assertRedirect(route('transactions.index'));
    $this->get('/expense-transactions')->assertRedirect(route('transactions.index'));
});

it('blocks the legacy income and expense pages for users without finance permissions', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper);

    $this->get('/income-transactions')->assertForbidden();
    $this->get('/expense-transactions')->assertForbidden();
});

it('verifies the finance menu gates in MenuHelper', function () {
    $financeNav = collect(MenuHelper::getMainNavItems())->firstWhere('name', 'Finance');

    expect($financeNav)->not->toBeNull()
        ->and($financeNav['canany'])->toContain('finance.view')
        ->and($financeNav['canany'])->toContain('finance.manage');

    $subItemNames = collect($financeNav['subItems'])->pluck('name')->all();

    expect($subItemNames)->toBe(['Cash Accounts', 'Bank Accounts', 'Transactions', 'Transfers', 'Supplier Payments']);

    foreach ($financeNav['subItems'] as $subItem) {
        expect($subItem)->toHaveKey('canany');
    }
});
