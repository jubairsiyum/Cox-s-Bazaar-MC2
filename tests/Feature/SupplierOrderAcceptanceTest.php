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
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active Supplier-role login (no branch, as the seeded supplier login has).
 */
function acceptanceSupplierLogin(): User
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
function acceptanceBranchManager(Location $location): User
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
function acceptancePlaceOrder(User $branchUser, Supplier $supplier, Location $location): PurchaseOrder
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

it('lets a supplier login open its own profile and see purchase orders from every branch', function () {
    $supplierUser = acceptanceSupplierLogin();
    $supplier = Supplier::factory()->create(['name' => 'Realme Hub Bangladesh', 'user_id' => $supplierUser->id]);

    $chakaria = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $teknaf = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $chakariaOrder = acceptancePlaceOrder(acceptanceBranchManager($chakaria), $supplier, $chakaria);
    $teknafOrder = acceptancePlaceOrder(acceptanceBranchManager($teknaf), $supplier, $teknaf);

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee('Realme Hub Bangladesh')
        ->assertSee($chakariaOrder->reference_no)
        ->assertSee($teknafOrder->reference_no)
        ->assertSee('Chakaria Outlet')
        ->assertSee('Teknaf Outlet')
        ->assertSee('Accept Order')
        ->assertSee('>Pending</span>', false)
        ->assertDontSee('Mark Delivered')
        ->assertDontSee(route('purchase-invoices.show', $chakariaOrder));

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'dues'))
        ->assertOk()
        ->assertSee('Branch-wise Dues')
        ->assertDontSee('Accept Order');

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'requests'))
        ->assertOk()
        ->assertSee('Send Payment Request');
});

it('lets a supplier login accept its own purchase order', function () {
    $supplierUser = acceptanceSupplierLogin();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $location = Location::factory()->create();
    $order = acceptancePlaceOrder(acceptanceBranchManager($location), $supplier, $location);

    $this->actingAs($supplierUser)
        ->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertSessionHas('success');

    expect($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Accepted);

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee('>Accepted</span>', false)
        ->assertDontSee('Accept Order');
});

it('refuses a supplier login the acceptance of another supplier purchase order', function () {
    $supplierUser = acceptanceSupplierLogin();
    $ownSupplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $otherSupplier = Supplier::factory()->create();

    $location = Location::factory()->create();
    $otherOrder = acceptancePlaceOrder(acceptanceBranchManager($location), $otherSupplier, $location);

    $this->actingAs($supplierUser)
        ->patch(route('suppliers.profile.accept-order', [$otherSupplier, $otherOrder]))
        ->assertForbidden();

    $this->actingAs($supplierUser)
        ->patch(route('suppliers.profile.accept-order', [$ownSupplier, $otherOrder]))
        ->assertNotFound();

    expect($otherOrder->fresh()->supplier_status)->toBe(SupplierOrderStatus::Pending);
});

it('lists a cancelled purchase order to the supplier with no accept action', function () {
    $supplierUser = acceptanceSupplierLogin();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $location = Location::factory()->create();
    $order = acceptancePlaceOrder(acceptanceBranchManager($location), $supplier, $location);
    $order->update(['purchase_status' => PurchaseStatus::Cancelled]);

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee($order->reference_no)
        ->assertViewHas('orders', fn ($orders) => $orders->contains(fn (array $row) => $row['cancelled'] && ! $row['awaiting_acceptance']))
        ->assertDontSee('Accept Order');

    $this->actingAs($supplierUser)
        ->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertSessionHas('error', 'A cancelled purchase order cannot be accepted.');

    expect($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Pending);
});

it('no longer has a Mark Delivered action for suppliers', function () {
    expect(Route::has('suppliers.profile.deliver-order'))->toBeFalse();

    $supplierUser = acceptanceSupplierLogin();
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $location = Location::factory()->create();
    $order = acceptancePlaceOrder(acceptanceBranchManager($location), $supplier, $location);

    $this->actingAs($supplierUser)
        ->patch('/suppliers/'.$supplier->id.'/profile/purchase-orders/'.$order->id.'/deliver')
        ->assertNotFound();

    expect($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Pending);
});
