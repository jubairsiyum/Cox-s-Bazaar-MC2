<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase order with one line item to apply the return against.
 */
function returnParentOrder(Unit $unit, int $quantity = 5, float $unitPrice = 200.0): PurchaseOrder
{
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $order = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $quantity * $unitPrice,
    ]);

    $order->update([
        'subtotal' => $quantity * $unitPrice,
        'tax_amount' => 0,
        'grand_total' => $quantity * $unitPrice,
    ]);

    return $order->fresh();
}

/**
 * A minimal valid return payload against the given purchase.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchaseReturnPayload(PurchaseOrder $order, array $overrides = []): array
{
    return array_merge([
        'return_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'purchase_order_id' => $order->id,
        'supplier_id' => $order->supplier_id,
        'reason' => 'Defective or Damaged',
        'items' => [
            ['unit_id' => $order->items->first()->unit_id, 'quantity' => 2],
        ],
    ], $overrides);
}

/**
 * A return from before returns became effective at submission: a pending
 * return whose stock and supplier return due effects were never posted.
 */
function legacyPendingReturn(PurchaseOrder $order, int $quantity = 2, float $unitPrice = 200.0): PurchaseReturn
{
    $return = PurchaseReturn::factory()->forPurchase($order)->create([
        'approval_status' => ApprovalStatus::Pending->value,
    ]);

    $return->items()->create([
        'unit_id' => $order->items->first()->unit_id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $quantity * $unitPrice,
    ]);

    $return->update([
        'subtotal' => $quantity * $unitPrice,
        'grand_total' => $quantity * $unitPrice,
    ]);

    return $return->fresh();
}

it('lists purchase returns with the parent purchase and an Apply Return button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);
    $return = PurchaseReturn::factory()->forPurchase($order)->create();

    $this->get(route('purchase-returns.index'))
        ->assertOk()
        ->assertSee($return->reference_no)
        ->assertSee($order->reference_no)
        ->assertSee('Parent Purchase', false)
        ->assertSee('Apply Return');
});

it('shows the apply return form prefilled from a parent purchase', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    $this->get(route('purchase-returns.create', ['purchase_order' => $order->id]))
        ->assertOk()
        ->assertSee('Invoice Reference', false)
        ->assertSee('Reason for Return', false)
        ->assertSee('Amount Paid', false)
        ->assertSee('Amount Due', false)
        ->assertSee('Products Brought In', false)
        ->assertSee('Return Quantity', false)
        ->assertSee($order->reference_no);
});

it('lists the distributor purchases and their details for the return form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    $this->get(route('purchase-returns.supplier-purchases', ['supplier_id' => $order->supplier_id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('purchases.0.id', $order->id)
        ->assertJsonPath('purchases.0.reference_no', $order->reference_no);

    $this->get(route('purchase-returns.purchase-details', ['purchase_order_id' => $order->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('purchase.reference_no', $order->reference_no)
        ->assertJsonPath('purchase.paid_amount', 0)
        ->assertJsonPath('purchase.payment_due', 1000)
        ->assertJsonPath('items.0.purchased', 5);
});

it('applies the return at submission, moving stock and the supplier return due', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order))
        ->assertRedirect(route('purchase-returns.index'));

    $return = PurchaseReturn::first();

    expect($return)->not->toBeNull()
        ->and($return->reference_no)->toStartWith('PR')
        ->and($return->purchase_order_id)->toBe($order->id)
        ->and($return->location_id)->toBe($order->location_id)
        ->and($return->reason)->toBe('Defective or Damaged')
        ->and((float) $return->grand_total)->toBe(400.0)
        ->and((float) $return->payment_due)->toBe(400.0)
        ->and($return->approval_status->value)->toBe('approved')
        ->and($return->approved_by)->not->toBeNull()
        ->and($return->approved_at)->not->toBeNull()
        ->and($return->items)->toHaveCount(1);

    // Submission is effective: stock leaves and the supplier return due
    // registers immediately.
    expect($unit->fresh()->quantity)->toBe(48)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(400.0);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'purchase_return',
        'quantity' => -2,
    ]);
});

it("applies the creating manager's own return right away with no self-approval step", function () {
    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);
    $manager = purchaseReturnTeamUser('Outlet Manager', ['branch_id' => $order->location_id]);

    $this->actingAs($manager)
        ->post(route('purchase-returns.store'), purchaseReturnPayload($order))
        ->assertRedirect(route('purchase-returns.index'));

    $return = PurchaseReturn::first();

    expect($return->approval_status->value)->toBe('approved')
        ->and($return->approved_by)->toBe($manager->id)
        ->and($return->approved_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(48)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(400.0);

    // No approve / reject UI is offered for the manager's own return.
    $this->get(route('purchase-returns.show', $return))
        ->assertOk()
        ->assertDontSee('Approve Return', false)
        ->assertDontSee('Confirm Reject', false);
});

it('validates the return against the chosen purchase', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);
    $otherSupplier = Supplier::factory()->create();

    $this->post(route('purchase-returns.store'), [
        'return_date' => now()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors(['purchase_order_id', 'supplier_id', 'reason', 'items']);

    // Return quantity cannot exceed the purchased units.
    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 6]],
    ]))->assertSessionHasErrors(['items.0.quantity']);

    // The purchase must belong to the selected distributor.
    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order, [
        'supplier_id' => $otherSupplier->id,
    ]))->assertSessionHasErrors(['purchase_order_id']);

    // At least one product needs a return quantity.
    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 0]],
    ]))->assertSessionHasErrors(['items']);

    expect(PurchaseReturn::count())->toBe(0);
});

it('syncs stock and totals when the return quantities change', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order))->assertRedirect();
    $return = PurchaseReturn::first();

    expect($unit->fresh()->quantity)->toBe(48);

    $this->patch(route('purchase-returns.update', $return), purchaseReturnPayload($order, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 3]],
    ]))->assertRedirect(route('purchase-returns.index'));

    expect($unit->fresh()->quantity)->toBe(47)
        ->and((float) $return->fresh()->grand_total)->toBe(600.0);
});

it('restores stock and clears the supplier return due when a return is deleted', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order))->assertRedirect();
    $return = PurchaseReturn::first();

    expect($unit->fresh()->quantity)->toBe(48)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(400.0);

    $this->delete(route('purchase-returns.destroy', $return))
        ->assertRedirect(route('purchase-returns.index'));

    $this->assertSoftDeleted('purchase_returns', ['id' => $return->id]);

    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(0.0);
});

it('blocks submitting a return that exceeds the stock on hand', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);
    $order = returnParentOrder($unit);

    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(PurchaseReturn::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(1);
});

/**
 * A verified purchasing user with the given role and branch/location assignment.
 *
 * @param  array<string, mixed>  $attributes
 */
function purchaseReturnTeamUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('sends no approval notifications to staff anywhere in the return flow', function () {
    Notification::fake();

    actingAsSuperAdmin();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);

    // The old awaiting-approval audience still exists, but nothing awaits
    // approval anymore: a submitted return is already applied.
    $manager = purchaseReturnTeamUser('Outlet Manager', ['branch_id' => $order->location_id]);
    purchaseReturnTeamUser('Outlet Manager', ['branch_id' => Location::factory()->create()->id]);

    $this->post(route('purchase-returns.store'), purchaseReturnPayload($order))
        ->assertRedirect(route('purchase-returns.index'));

    // Completing or refusing a legacy pending return is silent for staff as well.
    $legacy = legacyPendingReturn($order);
    $this->patch(route('purchase-returns.approve', $legacy))->assertRedirect(route('purchase-returns.index'));

    $otherLegacy = legacyPendingReturn($order);
    $this->patch(route('purchase-returns.reject', $otherLegacy))->assertRedirect(route('purchase-returns.index'));

    // The only alerts are the supplier's credit notices: the submitted return
    // and the approved legacy return. The rejected one moves no credit.
    Notification::assertNotSentTo($manager, GenericDatabaseNotification::class);
    Notification::assertSentTimes(GenericDatabaseNotification::class, 2);
});

it('completes a legacy pending return through the cleanup action', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);
    $return = legacyPendingReturn($order);

    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(0.0);

    $this->patch(route('purchase-returns.approve', $return))
        ->assertRedirect(route('purchase-returns.index'));

    $return->refresh();

    expect($return->approval_status->value)->toBe('approved')
        ->and($return->approved_by)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(48)
        ->and((float) $return->supplier->fresh()->purchase_return_due)->toBe(400.0);
});

it('keeps a rejected legacy return rejected when it is edited', function () {
    Notification::fake();

    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = returnParentOrder($unit);
    $return = legacyPendingReturn($order);

    $this->patch(route('purchase-returns.reject', $return))->assertRedirect(route('purchase-returns.index'));
    expect($return->fresh()->approval_status->value)->toBe('rejected');

    // There is no approval queue to resubmit into: editing a rejected return
    // leaves it rejected and nothing moves.
    $this->patch(route('purchase-returns.update', $return), purchaseReturnPayload($order, [
        'reason' => 'Wrong Product Delivered',
    ]))->assertRedirect(route('purchase-returns.index'));

    expect($return->fresh()->approval_status->value)->toBe('rejected')
        ->and($unit->fresh()->quantity)->toBe(50);

    Notification::assertNothingSent();
});
