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
use App\Notifications\GenericDatabaseNotification;
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
        'purchase_status' => 'ordered',
        'tax_rate' => 5,
        'paid_amount' => 50,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ], $overrides);
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
        ->assertSee('Attach Document', false);
});

it('creates a purchase order with line items and an auto reference number', function () {
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
        ->and($order->approval_status)->toBe(ApprovalStatus::Pending)
        ->and((float) $order->payment_due)->toBe(1000.0)
        ->and($order->items)->toHaveCount(1)
        ->and((float) $order->items->first()->subtotal)->toBe(1000.0);

    // Nothing takes effect before approval: no stock movement, no supplier due.
    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(0.0);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => PurchaseOrder::class,
        'auditable_id' => $order->id,
    ]);
});

it('receives stock and registers the supplier due only after approval', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('purchase-orders.store'), purchaseOrderPayload($unit))->assertRedirect();
    $order = PurchaseOrder::first();

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

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'restock',
        'quantity' => 5,
    ]);
});

it('validates the purchase order form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();

    $this->post(route('purchase-orders.store'), [
        'order_date' => now()->format('Y-m-d\TH:i'),
        'items' => [],
    ])->assertSessionHasErrors(['supplier_id', 'location_id', 'purchase_status', 'items']);
});

it('does not allow receiving a purchase at creation time', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('purchase-orders.store'), purchaseOrderPayload($unit, ['purchase_status' => 'received']))
        ->assertSessionHasErrors(['purchase_status']);
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

it('notifies the location and branch managers when a purchase awaits approval', function () {
    Notification::fake();

    $location = Location::factory()->create();
    $branch = Branch::query()->create(['name' => 'Sadar Outlet', 'code' => 'TB1', 'status' => 'active']);

    $locationManager = purchaseOrderTeamUser('Outlet Manager', ['location_id' => $location->id, 'branch_id' => null]);
    $branchManager = purchaseOrderTeamUser('Manager', ['location_id' => null, 'branch_id' => $branch->id]);
    $elsewhere = purchaseOrderTeamUser('Outlet Manager', ['location_id' => Location::factory()->create()->id]);

    $creator = purchaseOrderTeamUser('Manager', ['location_id' => null, 'branch_id' => $branch->id]);
    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->actingAs($creator)
        ->post(route('purchase-orders.store'), purchaseOrderPayload($unit, ['location_id' => $location->id]))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::first();

    $awaitsApproval = fn (GenericDatabaseNotification $notification): bool => str_contains($notification->title, $order->reference_no)
        && str_contains($notification->title, 'awaits your approval')
        && $notification->type === 'warning';

    Notification::assertSentTo($locationManager, GenericDatabaseNotification::class, $awaitsApproval);
    Notification::assertSentTo($branchManager, GenericDatabaseNotification::class, $awaitsApproval);
    Notification::assertNotSentTo($elsewhere, GenericDatabaseNotification::class);
});

it('notifies the creator when a purchase is approved or rejected', function () {
    Notification::fake();

    $admin = actingAsSuperAdmin();
    $location = Location::factory()->create();

    $this->post(route('purchase-orders.store'), purchaseOrderPayload(
        Unit::factory()->create(['quantity' => 50]),
        ['location_id' => $location->id]
    ))->assertRedirect();
    $approved = PurchaseOrder::first();

    $this->patch(route('purchase-invoices.approve', $approved))
        ->assertRedirect(route('purchase-invoices.index'));

    Notification::assertSentTo($admin, GenericDatabaseNotification::class, fn (GenericDatabaseNotification $notification): bool => str_contains($notification->title, 'approved')
        && $notification->type === 'success');

    $this->post(route('purchase-orders.store'), purchaseOrderPayload(
        Unit::factory()->create(['quantity' => 50]),
        ['location_id' => $location->id]
    ))->assertRedirect();
    $rejected = PurchaseOrder::orderByDesc('id')->first();

    $this->patch(route('purchase-invoices.reject', $rejected))
        ->assertRedirect(route('purchase-invoices.index'));

    Notification::assertSentTo($admin, GenericDatabaseNotification::class, fn (GenericDatabaseNotification $notification): bool => str_contains($notification->title, 'rejected')
        && $notification->type === 'error');
});
