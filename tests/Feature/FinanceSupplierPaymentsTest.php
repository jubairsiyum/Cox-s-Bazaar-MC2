<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Models\Account;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase invoice with money still outstanding plus the paying account.
 *
 * @param  array<string, mixed>  $paymentAttributes
 * @return array{0: Supplier, 1: PurchaseOrder, 2: Account}
 */
function financeDuePurchase(array $paymentAttributes = []): array
{
    $supplier = Supplier::factory()->create([
        'name' => 'Bismillah Phone Distributors',
        'opening_balance' => 0,
        'purchase_due' => 5000,
        'purchase_return_due' => 0,
    ]);

    $order = PurchaseOrder::factory()->create(array_merge([
        'supplier_id' => $supplier->id,
        'purchase_status' => 'received',
        'approval_status' => 'approved',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'grand_total' => 5000,
        'paid_amount' => 2000,
    ], $paymentAttributes));

    $account = Account::factory()->cash()->withBalance(2000)->create(['name' => 'Counter Cash Box']);

    return [$supplier, $order, $account];
}

it('records a payment that settles the purchase due and debits the account', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'method' => 'cash',
    ])->assertRedirect(route('supplier-payments.index'));

    $payment = PurchasePayment::query()->firstOrFail();

    expect($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->account_id)->toBe($account->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(3000.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(4000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(1000.0);
});

it('caps the payment amount at the remaining due of the purchase', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();

    $this->from(route('supplier-payments.create', ['supplier_id' => $supplier->id]))
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'amount' => '5000',
            'payment_date' => now()->toDateString(),
            'account_id' => $account->id,
        ])
        ->assertSessionHasErrors('amount');

    expect(PurchasePayment::query()->count())->toBe(0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(5000.0);
});

it('rejects payments against another supplier\'s purchase invoice', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();
    $otherSupplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $otherSupplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
    ])->assertSessionHasErrors('purchase_order_id');

    expect(PurchasePayment::query()->count())->toBe(0);
});

it('blocks payments from an account without sufficient balance', function () {
    actingAsSuperAdmin();

    [$supplier, $order] = financeDuePurchase();
    $poorAccount = Account::factory()->cash()->withBalance(50)->create();

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $poorAccount->id,
    ])->assertSessionHas('error');

    expect(PurchasePayment::query()->count())->toBe(0)
        ->and((float) $poorAccount->fresh()->current_balance)->toBe(50.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(5000.0);
});

it('filters the payment register by supplier and paying account', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();

    $otherAccount = Account::factory()->bank()->withBalance(9000)->create(['name' => 'City Bank Main']);

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
    ]);

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $otherAccount->id,
    ]);

    $counterPayment = PurchasePayment::query()->orderBy('id')->firstOrFail();
    $bankPayment = PurchasePayment::query()->orderByDesc('id')->firstOrFail();

    $this->get(route('supplier-payments.index', ['account_id' => $account->id]))
        ->assertOk()
        ->assertSee(route('supplier-payments.show', $counterPayment), false)
        ->assertDontSee(route('supplier-payments.show', $bankPayment), false);

    $this->get(route('supplier-payments.index', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertSee('Bismillah Phone Distributors');

    $this->get(route('supplier-payments.index', ['date_from' => now()->addDay()->toDateString()]))
        ->assertOk()
        ->assertSee('No supplier payments found');
});

it('reverses a payment, restoring the purchase due, supplier due and account balance', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
    ]);

    $payment = PurchasePayment::query()->firstOrFail();

    $this->get(route('supplier-payments.show', $payment))
        ->assertOk()
        ->assertSee('Bismillah Phone Distributors');

    $this->delete(route('supplier-payments.destroy', $payment))
        ->assertSessionHas('success');

    expect(PurchasePayment::query()->count())->toBe(0)
        ->and((float) $order->fresh()->paid_amount)->toBe(2000.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(5000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(2000.0);
});

it('gates recording and reversing supplier payments by permission', function () {
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->givePermissionTo('supplier-payments.view');

    [$supplier, $order, $account] = financeDuePurchase();

    $this->actingAs($viewer)
        ->get(route('supplier-payments.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'amount' => '100',
            'payment_date' => now()->toDateString(),
            'account_id' => $account->id,
        ])
        ->assertForbidden();

    $manager = User::factory()->create(['status' => 'active']);
    $manager->givePermissionTo('supplier-payments.manage');

    $this->actingAs($manager)
        ->post(route('supplier-payments.store'), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $order->id,
            'amount' => '100',
            'payment_date' => now()->toDateString(),
            'account_id' => $account->id,
        ])
        ->assertRedirect(route('supplier-payments.index'));

    $payment = PurchasePayment::query()->firstOrFail();

    $this->actingAs($viewer)
        ->delete(route('supplier-payments.destroy', $payment))
        ->assertForbidden();

    $this->actingAs($manager)
        ->delete(route('supplier-payments.destroy', $payment))
        ->assertSessionHas('success');
});

it('keeps the purchase dues screens working alongside finance payments', function () {
    actingAsSuperAdmin();

    [$supplier, $order, $account] = financeDuePurchase();

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => '1000',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
    ]);

    // The Purchase Dues list still shows the invoice's remaining due.
    $this->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertSee($order->reference_no)
        ->assertSee('2,000.00');
});
