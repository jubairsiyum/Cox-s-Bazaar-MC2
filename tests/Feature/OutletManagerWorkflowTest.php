<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified Outlet Manager assigned to the given location.
 */
function outletManagerUser(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

/**
 * A purchase order at the given location with one line item.
 */
function managerPurchase(Location $location, Unit $unit, string $status = 'ordered'): PurchaseOrder
{
    $order = PurchaseOrder::factory()->create([
        'location_id' => $location->id,
        'purchase_status' => $status,
    ]);

    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 5,
        'unit_price' => 200,
        'subtotal' => 1000,
    ]);

    return $order->fresh();
}

it('seeds the Outlet Manager role with branch-scoped permissions', function () {
    $role = Role::findByName('Outlet Manager');

    expect($role->hasPermissionTo('stock-transfers.approve'))->toBeTrue()
        ->and($role->hasPermissionTo('purchase-invoices.approve'))->toBeTrue()
        ->and($role->hasPermissionTo('purchase-returns.approve'))->toBeTrue()
        ->and($role->hasPermissionTo('inventory.view'))->toBeTrue()
        ->and($role->hasPermissionTo('finance.view'))->toBeTrue();
});

it('scopes the purchase register to the assigned location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create();

    $myOrder = managerPurchase($mine, $unit);
    $otherOrder = managerPurchase($other, $unit);

    $this->actingAs(outletManagerUser($mine))
        ->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);
});

it('blocks an outlet manager from viewing purchases of another location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create();

    $otherOrder = managerPurchase($other, $unit);

    $this->actingAs(outletManagerUser($mine))
        ->get(route('purchase-invoices.show', $otherOrder))
        ->assertForbidden();
});

it('lets an outlet manager approve purchases for their location only', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myOrder = managerPurchase($mine, $unit);
    $otherOrder = managerPurchase($other, $unit);
    $manager = outletManagerUser($mine);

    $this->actingAs($manager)
        ->patch(route('purchase-invoices.approve', $otherOrder))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('purchase-invoices.approve', $myOrder))
        ->assertRedirect(route('purchase-invoices.index'));

    expect($myOrder->fresh()->approval_status->value)->toBe('approved')
        ->and($myOrder->fresh()->approved_by)->toBe($manager->id)
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('scopes the transfer list to transfers leaving or arriving at the assigned location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myTransfer = StockTransfer::factory()->inTransit()->create([
        'from_location_id' => $mine->id,
        'to_location_id' => $other->id,
    ]);
    $otherTransfer = StockTransfer::factory()->inTransit()->create([
        'from_location_id' => $other->id,
        'to_location_id' => Location::factory()->create()->id,
    ]);

    $this->actingAs(outletManagerUser($mine))
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee($myTransfer->reference_no)
        ->assertDontSee($otherTransfer->reference_no);
});

it('shows awaiting approval to the receiver and Delivered/Received after approval', function () {
    $from = Location::factory()->create();
    $to = Location::factory()->create();

    $transfer = StockTransfer::factory()->inTransit()->create([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
    ]);

    $receiver = outletManagerUser($to);
    $sender = outletManagerUser($from);

    // While on the way, the receiver is asked to review it.
    expect($transfer->displayStatusFor($receiver)['label'])->toBe('Awaiting Approval')
        ->and($transfer->displayStatusFor($sender)['label'])->toBe('In Transit');

    $this->actingAs($receiver)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    $transfer->refresh();

    // Once approved the receiver reads "Received" and the sender "Delivered".
    expect($transfer->status->value)->toBe('received')
        ->and($transfer->approved_by)->toBe($receiver->id)
        ->and($transfer->displayStatusFor($receiver)['label'])->toBe('Received')
        ->and($transfer->displayStatusFor($sender)['label'])->toBe('Delivered');
});

it('stops the sending side from approving its own transfer', function () {
    $from = Location::factory()->create();
    $to = Location::factory()->create();

    $transfer = StockTransfer::factory()->inTransit()->create([
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
    ]);

    $this->actingAs(outletManagerUser($from))
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertForbidden();
});

it('shows accept and reject options to the receiving manager for a submitted request', function () {
    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    stockAtLocation($unit, 5, $from->id);

    $sender = outletManagerUser($from);
    $receiver = outletManagerUser($to);

    $this->actingAs($sender)
        ->post(route('stock-transfers.store'), [
            'transfer_date' => now()->format('Y-m-d\TH:i'),
            'reference_no' => null,
            'status' => 'draft',
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'shipping_charges' => 0,
            'additional_notes' => 'Please restock',
            'items' => [['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200.0]],
        ])
        ->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    // The request reads as awaiting approval on the receiving side.
    expect($transfer->displayStatusFor($receiver)['label'])->toBe('Awaiting Approval')
        ->and($transfer->displayStatusFor($sender)['label'])->toBe('Pending');

    // The receiving manager is alerted so the request cannot be missed.
    expect($receiver->fresh()->notifications()->count())->toBe(1)
        ->and($receiver->fresh()->notifications()->first()->data['type'])->toBe('warning');

    $this->actingAs($receiver)
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee($transfer->reference_no)
        ->assertSee('Accept')
        ->assertSee('Reject');

    $this->actingAs($receiver)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect($transfer->fresh()->status->value)->toBe('received')
        ->and($transfer->fresh()->approved_by)->toBe($receiver->id)
        ->and(collect($sender->fresh()->notifications)->pluck('data.type')->all())->toContain('success');
});

it('lets the receiving manager reject a submitted request', function () {
    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    stockAtLocation($unit, 5, $from->id);

    $sender = outletManagerUser($from);
    $receiver = outletManagerUser($to);

    $this->actingAs($sender)
        ->post(route('stock-transfers.store'), [
            'transfer_date' => now()->format('Y-m-d\TH:i'),
            'reference_no' => null,
            'status' => 'draft',
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
            'shipping_charges' => 0,
            'additional_notes' => null,
            'items' => [['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200.0]],
        ])
        ->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    $this->actingAs($receiver)
        ->patch(route('stock-transfers.reject', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect($transfer->fresh()->status->value)->toBe('rejected')
        ->and((int) $unit->fresh()->quantity)->toBe(50)
        ->and(collect($sender->fresh()->notifications)->pluck('data.type')->all())->toContain('error');
});

it('scopes the return register and its approval to the assigned location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myOrder = managerPurchase($mine, $unit);
    $otherOrder = managerPurchase($other, $unit);

    $myReturn = PurchaseReturn::factory()->forPurchase($myOrder)->create();
    $otherReturn = PurchaseReturn::factory()->forPurchase($otherOrder)->create();

    $manager = outletManagerUser($mine);

    $this->actingAs($manager)
        ->get(route('purchase-returns.index'))
        ->assertOk()
        ->assertSee($myReturn->reference_no)
        ->assertDontSee($otherReturn->reference_no);

    $this->actingAs($manager)
        ->patch(route('purchase-returns.approve', $otherReturn))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('purchase-returns.approve', $myReturn))
        ->assertRedirect(route('purchase-returns.index'));

    expect($myReturn->fresh()->approval_status->value)->toBe('approved')
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('completes purchase due payments the outlet manager records for their own outlet', function () {
    $mine = Location::factory()->create();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $mine->id,
        'purchase_status' => 'received',
        'approval_status' => 'approved',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'grand_total' => 5000,
        'paid_amount' => 2000,
    ]);

    $manager = outletManagerUser($mine);

    $this->actingAs($manager)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000,
        'method' => 'cash',
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    // No self-approval theater: the manager's own due payment is applied at
    // once and they are never asked to approve their own task.
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->created_by)->toBe($manager->id)
        ->and((int) $payment->approved_by)->toBe($manager->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(5000.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(0.0)
        ->and($manager->fresh()->notifications()->count())->toBe(0);
});
