<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * Place an order through the purchase form (5 × ৳200 = ৳1,000) and return it.
 */
function receiveButtonPlacedOrder(Supplier $supplier, Location $location, Unit $unit, string $purchaseStatus = 'ordered'): PurchaseOrder
{
    test()->post(route('purchase-orders.store'), [
        'order_date' => now()->format('Y-m-d\TH:i'),
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'purchase_status' => $purchaseStatus,
        'tax_rate' => 0,
        'paid_amount' => 0,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    return PurchaseOrder::query()->latest('id')->firstOrFail();
}

/**
 * A branch user assigned to the given location with purchase invoice rights.
 */
function receiveButtonBranchUser(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

function receiveButtonSupplierAndLocation(): array
{
    return [
        Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]),
        Location::factory()->create(),
    ];
}

it('shows the Mark Received button on the invoice of an ordered order the supplier accepted', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertDontSee(route('purchase-invoices.receive', $order), false);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertSee(route('purchase-invoices.receive', $order), false)
        ->assertSee('Mark Received');
});

it('hides the Mark Received button for received and cancelled orders', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();

    $received = receiveButtonPlacedOrder($supplier, $location, $unit, 'received');

    $this->get(route('purchase-invoices.show', $received))
        ->assertOk()
        ->assertDontSee(route('purchase-invoices.receive', $received), false);

    $cancelled = receiveButtonPlacedOrder($supplier, $location, $unit);
    $this->patch(route('suppliers.profile.accept-order', [$supplier, $cancelled]))->assertRedirect();
    $this->patch(route('purchase-invoices.update', $cancelled), [
        'order_date' => $cancelled->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $cancelled->supplier_id,
        'location_id' => $cancelled->location_id,
        'purchase_status' => 'cancelled',
        'paid_amount' => 0,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    $this->get(route('purchase-invoices.show', $cancelled))
        ->assertOk()
        ->assertDontSee(route('purchase-invoices.receive', $cancelled), false);
});

it('hides the Mark Received button for an order the supplier has not accepted yet', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    expect($order->supplier_status)->toBe(SupplierOrderStatus::Pending);

    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertDontSee(route('purchase-invoices.receive', $order), false);
});

it('moves the branch order to Received and the supplier order to Delivered when Mark Received is clicked', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();
    $order->update(['paid_amount' => $order->grand_total]);

    $this->patch(route('purchase-invoices.receive', $order))
        ->assertRedirect(route('purchase-invoices.show', $order))
        ->assertSessionHas('success');

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Delivered)
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->received_at)->not->toBeNull()
        ->and($order->managerDisplayStatus()[1])->toBe('Received')
        ->and($order->supplierDisplayStatus()[1])->toBe('Delivered');

    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertSee('>Received</span>', false)
        ->assertSee('>Delivered</span>', false)
        ->assertDontSee(route('purchase-invoices.receive', $order), false);

    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('>Delivered</span>', false);
});

it('posts the stock of a received order exactly once', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $this->patch(route('purchase-invoices.receive', $order))->assertSessionHas('success');
    $this->patch(route('purchase-invoices.receive', $order))->assertSessionHas('error');

    expect($unit->fresh()->quantity)->toBe(55);

    expect(DB::table('stock_movements')
        ->where('unit_id', $unit->id)
        ->where('type', 'restock')
        ->count())->toBe(1);
});

it('refuses to receive a pending order the supplier has not accepted', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('purchase-invoices.receive', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('refuses to receive a cancelled order', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();
    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'cancelled',
        'paid_amount' => 0,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    $this->patch(route('purchase-invoices.receive', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Cancelled)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('forbids another branch from receiving an order', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $otherBranch = receiveButtonBranchUser(Location::factory()->create());

    $this->actingAs($otherBranch)
        ->patch(route('purchase-invoices.receive', $order))
        ->assertForbidden();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('lets the order branch manager receive the order', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = receiveButtonSupplierAndLocation();
    $order = receiveButtonPlacedOrder($supplier, $location, $unit);

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();
    $order->update(['paid_amount' => $order->grand_total]);

    $this->actingAs(receiveButtonBranchUser($location))
        ->patch(route('purchase-invoices.receive', $order))
        ->assertRedirect(route('purchase-invoices.show', $order));

    expect($order->fresh()->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Delivered);
});
