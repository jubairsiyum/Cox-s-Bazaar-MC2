<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Enums\AccountMethod;
use App\Enums\TransactionCategoryType;
use App\Helpers\MenuHelper;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TransactionCategorySeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('seeds sensible default categories and methods idempotently', function () {
    $this->seed(TransactionCategorySeeder::class);
    $this->seed(PaymentMethodSeeder::class);
    $this->seed(TransactionCategorySeeder::class);
    $this->seed(PaymentMethodSeeder::class);

    expect(TransactionCategory::query()->count())->toBe(13)
        ->and(PaymentMethod::query()->count())->toBe(5);

    expect(TransactionCategory::query()->where('name', 'Service')->first()->type)->toBe(TransactionCategoryType::Income)
        ->and(TransactionCategory::query()->where('name', 'Utility')->first()->type)->toBe(TransactionCategoryType::Expense)
        ->and(TransactionCategory::query()->where('name', 'Commission')->first()->type)->toBe(TransactionCategoryType::Both)
        ->and(PaymentMethod::query()->where('name', 'Cash')->first()->type)->toBe(AccountMethod::Cash)
        ->and(PaymentMethod::query()->where('name', 'Bank Transfer')->first()->type)->toBe(AccountMethod::Bank)
        ->and(PaymentMethod::query()->where('name', 'bKash/Nagad')->first()->type)->toBe(AccountMethod::Other);
});

it('manages transaction categories with audit logging', function () {
    actingAsSuperAdmin();

    $this->post(route('transaction-categories.store'), [
        'name' => 'Delivery Income',
        'type' => 'income',
        'status' => 'active',
        'note' => 'Same-day delivery fees',
    ])->assertRedirect(route('transaction-categories.index'));

    $category = TransactionCategory::query()->where('name', 'Delivery Income')->firstOrFail();

    expect($category->type)->toBe(TransactionCategoryType::Income)
        ->and(AuditLog::query()->where('auditable_id', $category->id)->where('event', 'created')->exists())->toBeTrue();

    // Names are unique.
    $this->post(route('transaction-categories.store'), [
        'name' => 'Delivery Income',
        'type' => 'both',
    ])->assertSessionHasErrors('name');

    $this->patch(route('transaction-categories.update', $category), [
        'name' => 'Delivery',
        'type' => 'both',
        'status' => 'inactive',
        'note' => null,
    ])->assertRedirect(route('transaction-categories.index'));

    expect($category->fresh()->name)->toBe('Delivery')
        ->and($category->fresh()->type)->toBe(TransactionCategoryType::Both)
        ->and($category->fresh()->status)->toBe('inactive');

    $this->patch(route('transaction-categories.toggle-status', $category))
        ->assertSessionHas('success');

    expect($category->fresh()->status)->toBe('active');

    $this->delete(route('transaction-categories.destroy', $category))
        ->assertRedirect(route('transaction-categories.index'));

    expect(TransactionCategory::query()->find($category->id))->toBeNull();
});

it('manages payment methods with audit logging', function () {
    actingAsSuperAdmin();

    $this->post(route('payment-methods.store'), [
        'name' => 'Card',
        'type' => 'other',
        'status' => 'active',
        'note' => 'POS card machine',
    ])->assertRedirect(route('payment-methods.index'));

    $method = PaymentMethod::query()->where('name', 'Card')->firstOrFail();

    expect($method->type)->toBe(AccountMethod::Other)
        ->and(AuditLog::query()->where('auditable_id', $method->id)->where('event', 'created')->exists())->toBeTrue();

    $this->patch(route('payment-methods.update', $method), [
        'name' => 'Card Payment',
        'type' => 'bank',
        'status' => 'inactive',
        'note' => null,
    ])->assertRedirect(route('payment-methods.index'));

    expect($method->fresh()->name)->toBe('Card Payment')
        ->and($method->fresh()->type)->toBe(AccountMethod::Bank)
        ->and($method->fresh()->status)->toBe('inactive');

    $this->patch(route('payment-methods.toggle-status', $method))
        ->assertSessionHas('success');

    expect($method->fresh()->status)->toBe('active');

    $this->delete(route('payment-methods.destroy', $method))
        ->assertRedirect(route('payment-methods.index'));

    expect(PaymentMethod::query()->find($method->id))->toBeNull();
});

it('gates reference data management by finance permissions', function () {
    $category = TransactionCategory::factory()->create();
    $method = PaymentMethod::factory()->create();

    $payload = ['name' => 'Anything', 'type' => 'both', 'status' => 'active', 'note' => null];

    // A finance viewer may browse the lists but never write.
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->givePermissionTo('finance.view');

    $this->actingAs($viewer)->get(route('transaction-categories.index'))->assertOk();
    $this->actingAs($viewer)->get(route('payment-methods.index'))->assertOk();

    foreach ([
        ['get', route('transaction-categories.create'), null],
        ['post', route('transaction-categories.store'), $payload],
        ['get', route('transaction-categories.edit', $category), null],
        ['patch', route('transaction-categories.update', $category), $payload],
        ['patch', route('transaction-categories.toggle-status', $category), null],
        ['delete', route('transaction-categories.destroy', $category), null],
        ['get', route('payment-methods.create'), null],
        ['post', route('payment-methods.store'), $payload],
        ['patch', route('payment-methods.toggle-status', $method), null],
        ['delete', route('payment-methods.destroy', $method), null],
    ] as [$verb, $url, $data]) {
        $this->actingAs($viewer)->{$verb}($url, $data ?? [])->assertForbidden();
    }

    // Entry managers may manage the reference data.
    $incomeManager = User::factory()->create(['status' => 'active']);
    $incomeManager->givePermissionTo('income-transactions.manage');

    $this->actingAs($incomeManager)->get(route('payment-methods.create'))->assertOk();
    $this->actingAs($incomeManager)->post(route('payment-methods.store'), [
        'name' => 'Card',
        'type' => 'other',
    ])->assertRedirect(route('payment-methods.index'));

    // A storekeeper has no access at all.
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper)->get(route('transaction-categories.index'))->assertForbidden();
    $this->actingAs($storekeeper)->get(route('payment-methods.index'))->assertForbidden();
});

it('only offers active reference rows in the finance dropdowns', function () {
    actingAsSuperAdmin();

    TransactionCategory::factory()->create(['name' => 'ActiveCat', 'type' => 'both']);
    TransactionCategory::factory()->inactive()->create(['name' => 'RetiredCat', 'type' => 'both']);
    PaymentMethod::factory()->create(['name' => 'ActivePay', 'type' => 'other']);
    PaymentMethod::factory()->inactive()->create(['name' => 'RetiredPay', 'type' => 'other']);

    $this->get(route('transactions.create'))->assertOk()
        ->assertSee('ActiveCat')
        ->assertDontSee('RetiredCat')
        ->assertSee('ActivePay')
        ->assertDontSee('RetiredPay');

    $this->get(route('account-transfers.create'))->assertOk()
        ->assertSee('ActivePay')
        ->assertDontSee('RetiredPay');

    // The ledger's category filter is driven by the same source.
    $this->get(route('transactions.index'))->assertOk()
        ->assertSee('ActiveCat')
        ->assertDontSee('RetiredCat');
});

it('validates transaction entries against the active reference rows', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    TransactionCategory::factory()->income()->create(['name' => 'Sales']);
    TransactionCategory::factory()->inactive()->income()->create(['name' => 'Retired']);
    PaymentMethod::factory()->create(['name' => 'HandCash']);
    PaymentMethod::factory()->inactive()->create(['name' => 'OldWay']);

    $entry = [
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => '500',
        'transaction_date' => now()->toDateString(),
    ];

    // Unknown and inactive names are rejected once reference rows exist.
    $this->post(route('transactions.store'), $entry + ['category' => 'Mystery'])
        ->assertSessionHasErrors('category');

    $this->post(route('transactions.store'), $entry + ['category' => 'Retired'])
        ->assertSessionHasErrors('category');

    $this->post(route('transactions.store'), $entry + ['category' => 'Sales', 'method' => 'Mystery'])
        ->assertSessionHasErrors('method');

    $this->post(route('transactions.store'), $entry + ['category' => 'Sales', 'method' => 'OldWay'])
        ->assertSessionHasErrors('method');

    // Active names go through.
    $this->post(route('transactions.store'), $entry + ['category' => 'Sales', 'method' => 'HandCash'])
        ->assertRedirect(route('transactions.index'));

    $transaction = AccountTransaction::query()->firstOrFail();

    expect($transaction->category)->toBe('Sales')
        ->and($transaction->method)->toBe('HandCash')
        ->and($transaction->status)->toBe(AccountEntryStatus::Pending);
});

it('scopes categories to the entry type', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(1000)->create();

    TransactionCategory::factory()->income()->create(['name' => 'Sales']);
    TransactionCategory::factory()->expense()->create(['name' => 'UtilityBill']);

    $entry = [
        'account_id' => $account->id,
        'amount' => '500',
        'transaction_date' => now()->toDateString(),
        'category' => 'UtilityBill',
    ];

    // An expense-only category is not valid on an income entry…
    $this->post(route('transactions.store'), $entry + ['type' => 'income'])
        ->assertSessionHasErrors('category');

    // …while its own entry type accepts it.
    $this->post(route('transactions.store'), $entry + ['type' => 'expense'])
        ->assertRedirect(route('transactions.index'));

    $this->post(route('transactions.store'), [
        'account_id' => $account->id,
        'amount' => '500',
        'transaction_date' => now()->toDateString(),
        'category' => 'Sales',
        'type' => 'income',
    ])->assertRedirect(route('transactions.index'));
});

it('keeps free-text categories and methods while nothing is configured', function () {
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

    expect(AccountTransaction::query()->firstOrFail()->category)->toBe('Service');
});

it('keeps stored values visible and editable after their reference row is deactivated', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(0)->create();

    $category = TransactionCategory::factory()->expense()->create(['name' => 'Courier']);
    $method = PaymentMethod::factory()->create(['name' => 'HandCash']);

    $transaction = AccountTransaction::factory()->expense()->create([
        'account_id' => $account->id,
        'category' => 'Courier',
        'method' => 'HandCash',
    ]);

    $category->update(['status' => 'inactive']);
    $method->update(['status' => 'inactive']);

    // Keep at least one active row of each kind so the dropdowns still render.
    TransactionCategory::factory()->create(['name' => 'ActiveCat']);
    PaymentMethod::factory()->create(['name' => 'ActivePay']);

    // History keeps displaying the stored values…
    $this->get(route('transactions.index'))->assertOk()->assertSee('Courier');

    // …while new-entry dropdowns drop them.
    $this->get(route('transactions.create'))->assertOk()
        ->assertDontSee('Courier')
        ->assertDontSee('HandCash');

    // The edit form keeps the stored values selectable, and re-saving is valid.
    $this->get(route('transactions.edit', $transaction))->assertOk()
        ->assertSee('Courier (stored)')
        ->assertSee('HandCash (stored)');

    $this->patch(route('transactions.update', $transaction), [
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Courier',
        'amount' => '150',
        'transaction_date' => now()->toDateString(),
        'method' => 'HandCash',
    ])->assertRedirect(route('transactions.index'));

    expect($transaction->fresh()->category)->toBe('Courier')
        ->and($transaction->fresh()->method)->toBe('HandCash');
});

it('blocks deleting reference rows that are still in use', function () {
    actingAsSuperAdmin();

    $account = Account::factory()->cash()->withBalance(0)->create();

    $category = TransactionCategory::factory()->expense()->create(['name' => 'Courier']);
    $method = PaymentMethod::factory()->create(['name' => 'HandCash']);

    AccountTransaction::factory()->expense()->create([
        'account_id' => $account->id,
        'category' => 'Courier',
        'method' => 'HandCash',
    ]);

    $this->delete(route('transaction-categories.destroy', $category))
        ->assertSessionHas('error');

    expect($category->fresh())->not->toBeNull();

    $this->delete(route('payment-methods.destroy', $method))
        ->assertSessionHas('error');

    expect($method->fresh())->not->toBeNull();

    // Unused rows delete cleanly.
    $unused = TransactionCategory::factory()->create(['name' => 'Unused']);

    $this->delete(route('transaction-categories.destroy', $unused))
        ->assertRedirect(route('transaction-categories.index'));

    expect(TransactionCategory::query()->find($unused->id))->toBeNull();
});

it('validates account transfer methods against the reference rows', function () {
    actingAsSuperAdmin();

    $from = Account::factory()->cash()->withBalance(1000)->create();
    $to = Account::factory()->cash()->withBalance(0)->create();

    PaymentMethod::factory()->create(['name' => 'HandCash']);

    $transfer = [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => '100',
        'transfer_date' => now()->toDateString(),
    ];

    $this->post(route('account-transfers.store'), $transfer + ['method' => 'Mystery'])
        ->assertSessionHasErrors('method');

    $this->post(route('account-transfers.store'), $transfer + ['method' => 'HandCash'])
        ->assertRedirect(route('account-transfers.index'));
});

it('filters the reference lists by search, type and status', function () {
    actingAsSuperAdmin();

    TransactionCategory::factory()->income()->create(['name' => 'AlphaCat']);
    TransactionCategory::factory()->inactive()->income()->create(['name' => 'BetaCat']);

    $this->get(route('transaction-categories.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('BetaCat')
        ->assertDontSee('AlphaCat');

    $this->get(route('transaction-categories.index', ['search' => 'AlphaCat']))
        ->assertOk()
        ->assertSee('AlphaCat')
        ->assertDontSee('BetaCat');

    $this->get(route('transaction-categories.index', ['type' => 'income']))
        ->assertOk()
        ->assertSee('AlphaCat')
        ->assertSee('BetaCat');

    PaymentMethod::factory()->create(['name' => 'GammaPay']);
    PaymentMethod::factory()->inactive()->create(['name' => 'DeltaPay']);

    $this->get(route('payment-methods.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('DeltaPay')
        ->assertDontSee('GammaPay');
});

it('renders every reference data screen without errors', function () {
    actingAsSuperAdmin();

    $category = TransactionCategory::factory()->create();
    $method = PaymentMethod::factory()->create();

    $this->get(route('transaction-categories.index'))->assertOk();
    $this->get(route('transaction-categories.create'))->assertOk();
    $this->get(route('transaction-categories.edit', $category))->assertOk();

    $this->get(route('payment-methods.index'))->assertOk();
    $this->get(route('payment-methods.create'))->assertOk();
    $this->get(route('payment-methods.edit', $method))->assertOk();
});

it('lists the reference screens as flat finance menu items', function () {
    $financeNav = collect(MenuHelper::getMainNavItems())->firstWhere('name', 'Finance');

    $subItemPaths = collect($financeNav['subItems'])->pluck('path', 'name')->all();

    expect($subItemPaths)
        ->toHaveKey('Transaction Category', '/transaction-categories')
        ->toHaveKey('Method', '/payment-methods')
        ->and(collect($financeNav['subItems'])->pluck('subItems')->filter()->all())->toBe([]);
});
