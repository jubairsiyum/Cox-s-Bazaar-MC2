<?php

declare(strict_types=1);

use App\Enums\ProductRequestDeliveryStatus;
use App\Enums\ProductRequestStatus;
use App\Models\Location;
use App\Models\ProductRequest;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function deliveryBranchManager(Location $location): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $user->assignRole('Outlet Manager');

    return $user;
}

function deliverySuperAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    return $admin;
}

function approvedDeliveryRequest(Location $location, User $manager, int $quantity = 4): ProductRequest
{
    $request = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Delivery Tracked Phone',
        'barcode' => '9990001112223',
        'quantity' => $quantity,
        'unit' => 'Box',
    ]);

    test()->actingAs(deliverySuperAdmin())
        ->patch(route('products.requests.approve', $request))
        ->assertRedirect();

    return $request->fresh();
}

it('marks a request in transit when the Super Admin approves it', function () {
    $location = Location::factory()->create();
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);

    expect($request->status)->toBe(ProductRequestStatus::Approved)
        ->and($request->delivery_status)->toBe(ProductRequestDeliveryStatus::InTransit);
});

it('hides the approve and reject actions after a decision is made', function () {
    $location = Location::factory()->create();
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);

    $this->actingAs(deliverySuperAdmin())
        ->get(route('products.requests.show', $request))
        ->assertOk()
        ->assertDontSee(route('products.requests.approve', $request), false)
        ->assertDontSee(route('products.requests.reject', $request), false)
        ->assertSee('In Transit');
});

it('shows the receive action to the requesting branch while in transit', function () {
    $location = Location::factory()->create(['name' => 'Outlet North']);
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);

    $this->actingAs($manager)
        ->get(route('products.requests.show', $request))
        ->assertOk()
        ->assertSee('Receive Product')
        ->assertSee(route('products.requests.receive', $request), false);
});

it('receives the product, flips the delivery status to delivered and moves stock to the branch', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $location = Location::factory()->create(['name' => 'Outlet North']);
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager, 4);
    $unit = $request->createdProduct->stockRow;

    expect(StockService::quantityAt($unit, $location->id))->toBe(0)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(4);

    $this->actingAs($manager)
        ->patch(route('products.requests.receive', $request))
        ->assertRedirect(route('products.requests.show', $request));

    $request->refresh();

    expect($request->delivery_status)->toBe(ProductRequestDeliveryStatus::Delivered)
        ->and($request->delivered_at)->not->toBeNull()
        ->and($request->received_by)->toBe($manager->id)
        ->and(StockService::quantityAt($unit, $location->id))->toBe(4)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(0);
});

it('notifies the Super Admin when the branch receives the product', function () {
    $location = Location::factory()->create();
    $manager = deliveryBranchManager($location);
    $admin = deliverySuperAdmin();
    $request = approvedDeliveryRequest($location, $manager);

    $this->actingAs($manager)
        ->patch(route('products.requests.receive', $request))
        ->assertRedirect();

    $adminAlert = $admin->notifications()->first();

    expect($admin->notifications()->count())->toBeGreaterThanOrEqual(1)
        ->and($adminAlert->data['title'])->toContain('delivered');
});

it('stops a branch other than the requester from receiving', function () {
    $location = Location::factory()->create();
    $otherBranch = Location::factory()->create();
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);

    $this->actingAs(deliveryBranchManager($otherBranch))
        ->patch(route('products.requests.receive', $request))
        ->assertForbidden();

    expect($request->fresh()->delivery_status)->toBe(ProductRequestDeliveryStatus::InTransit);
});

it('refuses to receive a request that is not in transit', function () {
    $location = Location::factory()->create();
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);
    $request->update(['delivery_status' => ProductRequestDeliveryStatus::Delivered, 'delivered_at' => now()]);

    $this->actingAs($manager)
        ->patch(route('products.requests.receive', $request))
        ->assertForbidden();

    expect($request->fresh()->delivery_status)->toBe(ProductRequestDeliveryStatus::Delivered);
});

it('shows the delivery tracking on the request index', function () {
    $location = Location::factory()->create(['name' => 'Outlet North']);
    $manager = deliveryBranchManager($location);
    $request = approvedDeliveryRequest($location, $manager);

    $this->actingAs($manager)
        ->get(route('products.requests.index'))
        ->assertOk()
        ->assertSee('In Transit');

    $this->actingAs($manager)
        ->patch(route('products.requests.receive', $request))
        ->assertRedirect();

    $this->actingAs($manager)
        ->get(route('products.requests.index'))
        ->assertOk()
        ->assertSee('Delivered');
});
