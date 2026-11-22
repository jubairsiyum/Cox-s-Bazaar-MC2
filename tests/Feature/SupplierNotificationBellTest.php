<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active Supplier-role login (no branch, as the seeded supplier login has).
 */
function bellSupplierLogin(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'active',
    ]);

    $user->assignRole('Supplier');

    return $user;
}

/**
 * A verified Outlet Manager assigned to the given branch.
 */
function bellBranchManager(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'active',
        'branch_id' => $location->id,
    ]);

    $user->assignRole('Outlet Manager');

    return $user;
}

/**
 * A purchase order placed by a branch user through the real purchase order flow.
 */
function bellPlaceOrder(User $branchUser, Supplier $supplier, Location $location): PurchaseOrder
{
    $unit = Unit::factory()->create(['quantity' => 50]);

    test()->actingAs($branchUser)
        ->post(route('purchase-orders.store'), [
            'order_date' => now()->format('Y-m-d\TH:i'),
            'reference_no' => null,
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'purchase_status' => 'ordered',
            'tax_rate' => 5,
            'paid_amount' => 0,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
            ],
        ])->assertRedirect(route('purchase-invoices.index'));

    return PurchaseOrder::query()->latest('id')->firstOrFail();
}

it('puts the placed purchase order alert in the linked supplier login bell and on its feed', function () {
    $supplierUser = bellSupplierLogin();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = bellPlaceOrder(bellBranchManager($location), $supplier, $location);

    $alert = $supplierUser->notifications()->sole();

    expect($alert->data['title'])->toBe("New purchase order {$order->reference_no}")
        ->and($alert->data['body'])->toContain('Chakaria Outlet')
        ->and($alert->data['url'])->toBe('/suppliers/'.$supplier->id.'/profile')
        ->and($alert->data['type'])->toBe('info')
        ->and($alert->data['location_id'])->toBe($location->id)
        ->and($supplier->notifications()->count())->toBe(1);

    $this->actingAs($supplierUser)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee("New purchase order {$order->reference_no}");

    expect(NotificationService::scopeFor($supplierUser->unreadNotifications(), $supplierUser)->count())->toBe(1);
});

it('puts the recorded payment alert in the linked supplier login bell', function () {
    $admin = actingAsSuperAdmin();
    $supplierUser = bellSupplierLogin();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $location = Location::factory()->create();
    $order = bellPlaceOrder(bellBranchManager($location), $supplier, $location);

    $this->actingAs($admin)
        ->post(route('purchase-dues.store'), [
            'purchase_order_id' => $order->id,
            'amount' => 500,
            'method' => 'bkash',
        ])
        ->assertRedirect();

    $payment = $supplierUser->notifications()
        ->get()
        ->first(fn ($notification) => str_starts_with($notification->data['title'] ?? '', 'Payment received'));

    expect($payment)->not->toBeNull()
        ->and($payment->data['type'])->toBe('success')
        ->and($supplierUser->notifications()->count())->toBe(2)
        ->and($supplier->notifications()->count())->toBe(2);
});

it('keeps the supplier feed working when the supplier has no linked login', function () {
    $supplier = Supplier::factory()->create(['user_id' => null]);
    $location = Location::factory()->create();
    $order = bellPlaceOrder(bellBranchManager($location), $supplier, $location);

    expect($supplier->notifications()->count())->toBe(1)
        ->and($supplier->notifications()->first()->data['title'])->toBe("New purchase order {$order->reference_no}");
});
