<?php

declare(strict_types=1);

use App\Enums\SupplierPaymentRequestStatus;
use App\Helpers\Money;
use App\Models\Account;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active Supplier-role login.
 */
function dueNotifyLogin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'active',
    ]);

    $user->assignRole('Supplier');

    return $user;
}

/**
 * A supplier with no balances, optionally linked to a login.
 */
function dueNotifySupplier(?User $login = null): Supplier
{
    return Supplier::factory()->create([
        'user_id' => $login?->id,
        'opening_balance' => 0,
        'purchase_due' => 0,
        'purchase_return_due' => 0,
    ]);
}

/**
 * Place a 5 × ৳200 purchase order through the real purchase order flow (৳1,000 total).
 */
function dueNotifyPlaceOrder(Supplier $supplier, Location $location, float $paid = 0): PurchaseOrder
{
    $unit = Unit::factory()->create(['quantity' => 50]);

    stockAtLocation($unit, 5, $location->id);

    test()->post(route('purchase-orders.store'), [
        'order_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'purchase_status' => 'ordered',
        'tax_rate' => 0,
        'paid_amount' => $paid,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    return PurchaseOrder::query()->latest('id')->firstOrFail();
}

/**
 * Every feed entry addressed to the supplier.
 *
 * @return Collection<int, DatabaseNotification>
 */
function dueNotifyFeed(Supplier $supplier)
{
    return $supplier->notifications()->get();
}

/**
 * The single feed entry whose title contains the given text.
 */
function dueNotifyEntry(Supplier $supplier, string $titleContains): DatabaseNotification
{
    return dueNotifyFeed($supplier)
        ->filter(fn ($entry) => str_contains($entry->data['title'] ?? '', $titleContains))
        ->sole();
}

/**
 * A paying account for the order's branch.
 */
function dueNotifyAccount(Location $location): Account
{
    return Account::factory()->cash()->withBalance(5000)->create([
        'name' => 'Branch Cash Box',
        'location_id' => $location->id,
    ]);
}

it('notifies the supplier feed and the linked login when a purchase order is placed with an unpaid balance', function () {
    actingAsSuperAdmin();

    $login = dueNotifyLogin();
    $supplier = dueNotifySupplier($login);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);

    $entry = dueNotifyFeed($supplier)->sole();

    expect($entry->data['title'])->toBe("New purchase order {$order->reference_no}")
        ->and($entry->data['body'])->toContain('Chakaria Outlet')
        ->and($entry->data['body'])->toContain('Amount due: '.Money::format(1000))
        ->and($entry->data['body'])->toContain('Total outstanding to you: '.Money::format(1000))
        ->and($entry->data['body'])->toContain('still owing: Chakaria Outlet ('.Money::format(1000).')')
        ->and($entry->data['location_id'])->toBe($location->id)
        ->and($login->notifications()->sole()->data['title'])->toBe("New purchase order {$order->reference_no}");
});

it('notifies the supplier feed and the linked login when a payment is recorded', function () {
    actingAsSuperAdmin();

    $login = dueNotifyLogin();
    $supplier = dueNotifySupplier($login);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 400,
        'method' => 'bkash',
    ])->assertRedirect();

    $entry = dueNotifyEntry($supplier, 'Payment received');

    expect(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and($entry->data['title'])->toBe("Payment received — {$order->reference_no}")
        ->and($entry->data['type'])->toBe('success')
        ->and($entry->data['body'])->toContain(Money::format(400))
        ->and($entry->data['body'])->toContain('Chakaria Outlet')
        ->and($entry->data['body'])->toContain('still owing: Chakaria Outlet ('.Money::format(600).')')
        ->and($login->notifications()->count())->toBe(2);
});

it('sends one payment notice when a single payment settles the purchase, not a second due message', function () {
    actingAsSuperAdmin();

    $supplier = dueNotifySupplier();
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
        'method' => 'cash',
    ])->assertRedirect();

    $feed = dueNotifyFeed($supplier);

    expect($feed)->toHaveCount(2)
        ->and($feed->filter(fn ($entry) => str_contains($entry->data['title'], 'Amount due'))->all())->toBe([])
        ->and(dueNotifyEntry($supplier, 'Payment received')->data['body'])->toContain('Branches with dues cleared: Chakaria Outlet');
});

it('notifies the supplier when a payment request is actioned by the branch, naming the request in one notice', function () {
    actingAsSuperAdmin();

    $login = dueNotifyLogin();
    $supplier = dueNotifySupplier($login);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);

    $paymentRequest = SupplierPaymentRequest::factory()->forOrder($order)->create([
        'amount' => 300,
        'status' => SupplierPaymentRequestStatus::Open->value,
    ]);

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => 300,
        'payment_date' => now()->toDateString(),
        'account_id' => dueNotifyAccount($location)->id,
        'method' => 'cash',
        'payment_request_id' => $paymentRequest->id,
    ])->assertRedirect(route('supplier-payments.index'));

    expect($paymentRequest->fresh()->status)->toBe(SupplierPaymentRequestStatus::Actioned);

    $actioned = dueNotifyEntry($supplier, 'Payment request actioned');

    expect(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and($actioned->data['title'])->toBe("Payment request actioned — {$order->reference_no}")
        ->and($actioned->data['body'])->toContain('Your payment request of '.Money::format(300).' was actioned.')
        ->and($actioned->data['body'])->toContain('Chakaria Outlet')
        ->and($actioned->data['type'])->toBe('success')
        ->and($login->notifications()->count())->toBe(2);
});

it('notifies the supplier when an order edit raises the amount due, and stays silent for a zero change', function () {
    actingAsSuperAdmin();

    $supplier = dueNotifySupplier();
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);
    $unit = $order->items()->firstOrFail()->unit;

    $editPayload = fn (int $quantity): array => [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'ordered',
        'tax_rate' => 0,
        'paid_amount' => 0,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => 200],
        ],
    ];

    $this->patch(route('purchase-invoices.update', $order), $editPayload(8))
        ->assertRedirect(route('purchase-invoices.index'));

    $increase = dueNotifyEntry($supplier, 'Amount due increased');

    expect(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and($increase->data['title'])->toBe("Amount due increased — {$order->reference_no}")
        ->and($increase->data['body'])->toContain('rose by '.Money::format(600))
        ->and($increase->data['body'])->toContain('Chakaria Outlet now owes '.Money::format(1600))
        ->and($increase->data['type'])->toBe('warning');

    $this->patch(route('purchase-invoices.update', $order), $editPayload(8))
        ->assertRedirect(route('purchase-invoices.index'));

    expect(dueNotifyFeed($supplier))->toHaveCount(2);
});

it('notifies the supplier of an approved purchase return and records the return credit', function () {
    actingAsSuperAdmin();

    $login = dueNotifyLogin();
    $supplier = dueNotifySupplier($login);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);
    $unit = $order->items()->firstOrFail()->unit;

    $this->post(route('purchase-returns.store'), [
        'return_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'purchase_order_id' => $order->id,
        'supplier_id' => $supplier->id,
        'reason' => 'Defective or Damaged',
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 2],
        ],
    ])->assertRedirect(route('purchase-returns.index'));

    $return = PurchaseReturn::query()->latest('id')->firstOrFail();
    $entry = dueNotifyEntry($supplier, 'credited');

    expect((float) $supplier->fresh()->purchase_return_due)->toBe(400.0)
        ->and(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and($entry->data['title'])->toBe("Purchase return {$return->reference_no} credited")
        ->and($entry->data['body'])->toContain('Credit of '.Money::format(400).' for purchase return '.$return->reference_no.' from Chakaria Outlet')
        ->and($entry->data['body'])->toContain('Return credit still due from you: '.Money::format(400))
        ->and($login->notifications()->count())->toBe(2);
});

it('keeps a pending return out of the dues until it is approved, then notifies once', function () {
    actingAsSuperAdmin();

    $supplier = dueNotifySupplier();
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);

    $return = PurchaseReturn::factory()->forPurchase($order)->create([
        'approval_status' => 'pending',
        'grand_total' => 400,
        'paid_amount' => 0,
    ]);

    expect(dueNotifyFeed($supplier))->toHaveCount(1)
        ->and((float) $supplier->fresh()->purchase_return_due)->toBe(0.0);

    $this->patch(route('purchase-returns.approve', $return))
        ->assertRedirect(route('purchase-returns.index'));

    expect((float) $supplier->fresh()->purchase_return_due)->toBe(400.0)
        ->and(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and(dueNotifyEntry($supplier, 'credited')->data['title'])->toBe("Purchase return {$return->reference_no} credited");
});

it('notifies the supplier when a payment reversal raises the due again', function () {
    actingAsSuperAdmin();

    $login = dueNotifyLogin();
    $supplier = dueNotifySupplier($login);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);
    $account = dueNotifyAccount($location);

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => 400,
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'method' => 'cash',
    ])->assertRedirect(route('supplier-payments.index'));

    $payment = $order->payments()->firstOrFail();

    $this->delete(route('supplier-payments.destroy', $payment))
        ->assertRedirect();

    $reversal = dueNotifyEntry($supplier, 'Payment reversed');

    expect((float) $order->fresh()->paid_amount)->toBe(0.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(1000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(5000.0)
        ->and(dueNotifyFeed($supplier))->toHaveCount(3)
        ->and($reversal->data['title'])->toBe("Payment reversed — {$order->reference_no}")
        ->and($reversal->data['type'])->toBe('warning')
        ->and($reversal->data['body'])->toContain('The due is raised again: Chakaria Outlet now owes '.Money::format(1000))
        ->and($reversal->data['body'])->toContain('Total outstanding to you: '.Money::format(1000))
        ->and($login->notifications()->count())->toBe(3);
});

it('withdraws a cancelled purchase order with one short notice and no due message', function () {
    actingAsSuperAdmin();

    $supplier = dueNotifySupplier();
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = dueNotifyPlaceOrder($supplier, $location);
    $reference = $order->reference_no;

    $this->delete(route('purchase-invoices.destroy', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    $withdrawal = dueNotifyEntry($supplier, 'cancelled');

    expect(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and($withdrawal->data['title'])->toBe("Purchase order {$reference} cancelled")
        ->and($withdrawal->data['body'])->toContain('Due reduced by '.Money::format(1000))
        ->and($withdrawal->data['type'])->toBe('warning')
        ->and((float) $supplier->fresh()->purchase_due)->toBe(0.0);
});

it('keeps the supplier feed working when the supplier has no linked login', function () {
    actingAsSuperAdmin();

    $supplier = dueNotifySupplier();
    $location = Location::factory()->create();
    $order = dueNotifyPlaceOrder($supplier, $location);

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 100,
        'method' => 'cash',
    ])->assertRedirect();

    expect($supplier->user_id)->toBeNull()
        ->and(dueNotifyFeed($supplier))->toHaveCount(2)
        ->and(dueNotifyEntry($supplier, 'Payment received')->data['title'])->toBe("Payment received — {$order->reference_no}");
});
