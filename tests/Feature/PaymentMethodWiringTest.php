<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase invoice with money still outstanding at the given location.
 */
function methodWiringPurchase(Location $location): PurchaseOrder
{
    return PurchaseOrder::factory()->create([
        'location_id' => $location->id,
        'purchase_status' => 'received',
        'grand_total' => 5000,
        'paid_amount' => 2000,
    ]);
}

it('wires the supplier-payments and purchase-dues method dropdowns to the reference list', function () {
    actingAsSuperAdmin();

    PaymentMethod::factory()->create(['name' => 'HandCash']);

    $location = Location::factory()->create();
    $order = methodWiringPurchase($location);

    $this->get(route('supplier-payments.create', ['supplier_id' => $order->supplier_id]))
        ->assertOk()
        ->assertSee('HandCash');

    $this->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertSee('HandCash');
});

it('validates supplier-payment and purchase-dues methods against the active reference rows', function () {
    actingAsSuperAdmin();

    PaymentMethod::factory()->create(['name' => 'HandCash']);
    PaymentMethod::factory()->inactive()->create(['name' => 'OldWay']);

    $location = Location::factory()->create();
    $order = methodWiringPurchase($location);
    $account = Account::factory()->cash()->withBalance(5000)->create();

    // Unknown and inactive methods are rejected on both screens…
    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $order->supplier_id,
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'method' => 'Mystery',
    ])->assertSessionHasErrors('method');

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'method' => 'OldWay',
    ])->assertSessionHasErrors('method');

    // …while active names go through and are stored as the chosen name.
    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'method' => 'HandCash',
    ])->assertRedirect();

    expect(PurchasePayment::query()->firstOrFail()->method)->toBe('HandCash');
});

it('maps legacy purchase-payment method values to their display labels', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();
    $order = methodWiringPurchase($location);

    // Legacy rows store enum values ('cash', 'bkash', …); they display
    // through the enum label.
    PurchasePayment::factory()->approved()->create([
        'purchase_order_id' => $order->id,
        'method' => 'bkash',
    ]);

    $this->get(route('supplier-payments.index'))
        ->assertOk()
        ->assertSee('bKash');
});

it('keeps legacy enum values valid alongside the reference names', function () {
    actingAsSuperAdmin();

    PaymentMethod::factory()->create(['name' => 'HandCash']);

    $location = Location::factory()->create();
    $order = methodWiringPurchase($location);

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'method' => 'cash',
    ])->assertRedirect();

    expect(PurchasePayment::query()->firstOrFail()->method)->toBe('cash');
});

it('keeps purchase-payment methods free text while nothing is configured', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();
    $order = methodWiringPurchase($location);

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => '100',
        'method' => 'bkash',
    ])->assertRedirect();

    $payment = PurchasePayment::query()->firstOrFail();

    expect($payment->method)->toBe('bkash');

    $this->get(route('supplier-payments.index'))
        ->assertOk()
        ->assertSee('bKash');
});
