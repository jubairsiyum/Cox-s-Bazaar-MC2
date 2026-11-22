<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified Outlet Manager assigned to the given location.
 */
function branchAddProductsManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('shows the Add Products action to the super admin on every branch card', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Coxs Bazar']);

    $this->get(route('branches.index'))
        ->assertOk()
        ->assertSee('Add Products')
        ->assertSee(route('stock-transfers.create', ['to_location_id' => $branch->id]), false);
});

it('hides the Add Products action from a branch manager', function () {
    $manager = User::factory()->create(['email_verified_at' => now()]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('branches.index'))
        ->assertOk()
        ->assertDontSee('Add Products');
});

it('shows the Add Products to Branch action on the branch detail page', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Coxs Bazar']);

    $this->get(route('branches.show', $branch))
        ->assertOk()
        ->assertSee('Add Products to Branch')
        ->assertSee(route('stock-transfers.create', ['to_location_id' => $branch->id]), false);
});

it('pre-fills the destination branch and defaults the source to the main warehouse', function () {
    actingAsSuperAdmin();

    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Coxs Bazar']);

    $this->get(route('stock-transfers.create', ['to_location_id' => $branch->id]))
        ->assertOk()
        ->assertSee('value="'.$warehouse->id.'" selected', false)
        ->assertSee('value="'.$branch->id.'" selected', false)
        ->assertSee('value="in-transit" selected', false);
});

it('keeps the normal create flow working without a pre-filled destination', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-transfers.create'))
        ->assertOk()
        ->assertSee('Location (From)', false)
        ->assertDontSee('value="in-transit" selected', false);
});

it('moves stock out of the main warehouse when the transfer is created in transit', function () {
    actingAsSuperAdmin();

    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Coxs Bazar']);
    $unit = Unit::factory()->create(['quantity' => 50]);
    stockAtLocation($unit, 20, $warehouse->id);

    $this->post(route('stock-transfers.store'), [
        'transfer_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'status' => 'in-transit',
        'from_location_id' => $warehouse->id,
        'to_location_id' => $branch->id,
        'shipping_charges' => 0,
        'additional_notes' => null,
        'items' => [['unit_id' => $unit->id, 'quantity' => 10, 'unit_price' => 200.0]],
    ])->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->status->value)->toBe('in-transit')
        ->and($transfer->from_location_id)->toBe($warehouse->id)
        ->and($transfer->to_location_id)->toBe($branch->id)
        ->and(StockService::availableAt($unit->id, $warehouse->id))->toBe(10);
});

it('lets the receiving manager receive the transfer and shows received and delivered per side', function () {
    $admin = actingAsSuperAdmin();

    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Coxs Bazar']);
    $unit = Unit::factory()->create(['quantity' => 50]);
    stockAtLocation($unit, 20, $warehouse->id);

    $this->post(route('stock-transfers.store'), [
        'transfer_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'status' => 'in-transit',
        'from_location_id' => $warehouse->id,
        'to_location_id' => $branch->id,
        'shipping_charges' => 0,
        'additional_notes' => null,
        'items' => [['unit_id' => $unit->id, 'quantity' => 10, 'unit_price' => 200.0]],
    ])->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::firstOrFail();

    $branchManager = branchAddProductsManagerAt($branch);
    $warehouseManager = branchAddProductsManagerAt($warehouse);

    $this->actingAs($branchManager)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    $transfer->refresh();

    expect($transfer->status->value)->toBe('received')
        ->and($transfer->displayStatusFor($branchManager)['label'])->toBe('Received')
        ->and($transfer->displayStatusFor($warehouseManager)['label'])->toBe('Delivered')
        ->and($transfer->displayStatusFor($admin)['label'])->toBe('Received')
        ->and(StockService::availableAt($unit->id, $branch->id))->toBe(10);
});
