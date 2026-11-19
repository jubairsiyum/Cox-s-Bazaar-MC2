<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseApprovalNotifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A minimal valid purchase order payload for the given unit.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function purchaseOrderPayload(Unit $unit, array $overrides = []): array
{
    return array_merge([
        'order_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'supplier_id' => Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0])->id,
        'location_id' => Location::factory()->create()->id,
        'tax_rate' => 5,
        'paid_amount' => 50,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ], $overrides);
}

/**
 * A purchase from before purchases became effective at creation: a pending
 * order whose stock and supplier due effects were never posted.
 */
function legacyPendingPurchase(Unit $unit, int $quantity = 5, float $unitPrice = 200.0): PurchaseOrder
{
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'purchase_status' => 'ordered',
        'approval_status' => ApprovalStatus::Pending->value,
    ]);

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

it('redirects the purchase orders section to the add form', function () {
    actingAsSuperAdmin();

    $this->get(route('purchase-orders.index'))
        ->assertRedirect(route('purchase-orders.create'));
});

it('shows the new purchase order form', function () {
    actingAsSuperAdmin();

    $this->get(route('purchase-orders.create'))
        ->assertOk()
        ->assertSee('Search Products', false)
        ->assertSee('Purchase Tax', false)
        ->assertSee('Amount Paid', false)
        ->assertSee('Attach Document', false)
        ->assertSee('Received');
});

it('creates a purchase order that takes effect immediately', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = purchaseOrderPayload($unit);

    $response = $this->post(route('purchase-orders.store'), $payload);

    $response->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::first();

    expect($order)->not->toBeNull()
        ->and($order->reference_no)->toStartWith('PO')
        ->and((float) $order->subtotal)->toBe(1000.0)
        ->and((float) $order->tax_amount)->toBe(50.0)
        ->and((float) $order->grand_total)->toBe(1050.0)
        ->and($order->payment_status)->toBe(PaymentStatus::Partial)
        ->and($order->purchase_status->value)->toBe('received')
        ->and($order->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($order->approved_by)->not->toBeNull()
        ->and($order->approved_at)->not->toBeNull()
        ->and((float) $order->payment_due)->toBe(1000.0)
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->items->first()->subtotal)->toBe(1000.0);

    // Creation posts the effects the old approval used to: stock and the
    // supplier payable register right away.
    expect($unit->fresh()->quantity)->toBe(55)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(1000.0);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'restock',
        'quantity' => 5,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => PurchaseOrder::class,
        'auditable_id' => $order->id,
    ]);
});

it("makes the creating manager's own purchase effective right away with no self-approval step", function () {
    Notification::fake();

    $location = Location::factory()->create();
    $manager = purchaseOrderTeamUser('Manager', ['location_id' => $location->id, 'branch_id' => null]);
    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->actingAs($manager)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($unit, ['location_id' => $location->id]))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::first();

    // The creating manager is the responsible approver and the purchase has
    // already taken effect — nothing awaits sign-off.
    expect($order->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($order->approved_by)->toBe($manager->id)
        ->and($order->approved_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(55)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(1000.0);

    // No approve / reject UI is offered for the manager's own purchase.
    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertDontSee('Approve / Receive', false)
        ->assertDontSee('Confirm Reject', false);
});

it('validates the purchase order form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();

    $this->post(route('purchase-orders.store'), [
        'order_date' => now()->format('Y-m-d\TH:i'),
        'items' => [],
    ])->assertSessionHasErrors(['supplier_id', 'location_id', 'items']);
});

/**
 * A verified purchasing user with the given role and branch/location assignment.
 *
 * @param  array<string, mixed>  $attributes
 */
function purchaseOrderTeamUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('finds the responsible managers by location or by branch', function () {
    $location = Location::factory()->create();
    $branch = Branch::query()->create(['name' => 'Sadar Outlet', 'code' => 'TB1', 'status' => 'active']);

    $byLocation = purchaseOrderTeamUser('Outlet Manager', ['location_id' => $location->id, 'branch_id' => null]);
    $byBranch = purchaseOrderTeamUser('Manager', ['location_id' => null, 'branch_id' => $branch->id]);
    $inactive = purchaseOrderTeamUser('Outlet Manager', ['location_id' => $location->id, 'status' => 'inactive']);
    $elsewhere = purchaseOrderTeamUser('Outlet Manager', ['location_id' => Location::factory()->create()->id]);

    $found = PurchaseApprovalNotifier::managersFor($location->id, $branch->id);

    expect($found->pluck('id')->all())
        ->toContain($byLocation->id, $byBranch->id)
        ->not->toContain($inactive->id, $elsewhere->id);
});

it('sends no approval notifications anywhere in the purchase flow', function () {
    Notification::fake();

    $location = Location::factory()->create();
    $branch = Branch::query()->create(['name' => 'Sadar Outlet', 'code' => 'TB1', 'status' => 'active']);

    // Managers exist for the location and branch, exactly like the old
    // awaiting-approval audience, but nothing awaits approval anymore.
    purchaseOrderTeamUser('Outlet Manager', ['location_id' => $location->id, 'branch_id' => null]);
    purchaseOrderTeamUser('Manager', ['location_id' => null, 'branch_id' => $branch->id]);

    $creator = purchaseOrderTeamUser('Manager', ['location_id' => null, 'branch_id' => $branch->id]);
    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->actingAs($creator)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($unit, ['location_id' => $location->id]))
        ->assertRedirect(route('purchase-invoices.index'));

    // Completing a legacy pending purchase is silent as well.
    $legacy = legacyPendingPurchase(Unit::factory()->create(['quantity' => 50]));
    $this->patch(route('purchase-invoices.approve', $legacy))->assertRedirect(route('purchase-invoices.index'));

    $otherLegacy = legacyPendingPurchase(Unit::factory()->create(['quantity' => 50]));
    $this->patch(route('purchase-invoices.reject', $otherLegacy))->assertRedirect(route('purchase-invoices.index'));

    Notification::assertNothingSent();
});

it('completes a legacy pending purchase through the cleanup action', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = legacyPendingPurchase($unit);

    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(0.0);

    $this->patch(route('purchase-invoices.approve', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect($order->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($order->purchase_status->value)->toBe('received')
        ->and($order->approved_by)->not->toBeNull()
        ->and($order->approved_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(55)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(1000.0);
});

it('rejects a legacy pending purchase without touching stock or dues', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = legacyPendingPurchase($unit);

    $this->patch(route('purchase-invoices.reject', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect($order->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($order->purchase_status->value)->toBe('cancelled')
        ->and($unit->fresh()->quantity)->toBe(50)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(0.0);
});

it('shows the received status for a newly created purchase', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $this->post(route('purchase-orders.store'), purchaseOrderPayload($unit))->assertRedirect();
    $order = PurchaseOrder::first();

    foreach ([route('purchase-invoices.show', $order), route('purchase-invoices.index'), route('purchase-invoices.edit', $order)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('>Received</span>', false)
            ->assertDontSee('>Approved</span>', false)
            ->assertDontSee('>Awaiting Approval</span>', false);
    }
});

it('still renders the awaiting approval badge for legacy pending rows', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = legacyPendingPurchase($unit);

    foreach ([route('purchase-invoices.show', $order), route('purchase-invoices.index')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('>Awaiting Approval</span>', false)
            ->assertDontSee('>Approved</span>', false);
    }
});

it('shows only the rejected status when a legacy purchase is rejected', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = legacyPendingPurchase($unit);

    $this->patch(route('purchase-invoices.reject', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    foreach ([route('purchase-invoices.show', $order), route('purchase-invoices.index')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('>Rejected</span>', false)
            ->assertDontSee('>Cancelled</span>', false)
            ->assertDontSee('>Approved</span>', false);
    }
});
