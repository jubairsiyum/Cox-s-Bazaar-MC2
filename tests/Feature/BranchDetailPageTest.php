<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('renders the branch detail page for a super admin with branch name, product count and stock', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create(['name' => 'Main Warehouse', 'type' => 'warehouse']);

    $unit = stockRowFor('Samsung Phone', ['quantity' => 100, 'unit' => 'Pcs']);
    stockAtLocation($unit, 15, $location->id);

    $this->get(route('branches.show', $location))
        ->assertOk()
        ->assertSee('Main Warehouse')
        ->assertSee('Samsung Phone')
        ->assertSee('15');
});

it('lists incoming and outgoing transfers for the branch', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->create(['name' => 'Main Warehouse']);
    $other = Location::factory()->create(['name' => 'Brand Shop Coxs Bazar']);

    StockTransfer::factory()->create([
        'reference_no' => 'TRF-IN-001',
        'from_location_id' => $other->id,
        'to_location_id' => $branch->id,
    ]);

    StockTransfer::factory()->create([
        'reference_no' => 'TRF-OUT-001',
        'from_location_id' => $branch->id,
        'to_location_id' => $other->id,
    ]);

    $this->get(route('branches.show', $branch))
        ->assertOk()
        ->assertSee('Incoming Transfers')
        ->assertSee('Outgoing Transfers')
        ->assertSee('TRF-IN-001')
        ->assertSee('TRF-OUT-001')
        ->assertSee('Brand Shop Coxs Bazar');
});

it('shows the daily sales breakdown and returns', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->create(['name' => 'Main Warehouse']);

    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 1000,
    ]);

    SalesReturn::factory()->create([
        'return_no' => 'SR-001',
        'location_id' => $branch->id,
        'refund_amount' => 200,
    ]);

    PurchaseReturn::factory()->create([
        'reference_no' => 'PR-001',
        'location_id' => $branch->id,
        'grand_total' => 300,
    ]);

    $this->get(route('branches.show', $branch))
        ->assertOk()
        ->assertSee('Daily Sales')
        ->assertSee('1,000.00')
        ->assertSee('SR-001')
        ->assertSee('PR-001');
});

it('lets a branch user view their own branch but forbids another branch', function () {
    $own = Location::factory()->create(['name' => 'Own Branch']);
    $other = Location::factory()->create(['name' => 'Other Branch']);

    $user = User::factory()->create(['branch_id' => $own->id]);
    $user->assignRole('Manager');

    $this->actingAs($user)
        ->get(route('branches.show', $own))
        ->assertOk()
        ->assertSee('Own Branch');

    $this->actingAs($user)
        ->get(route('branches.show', $other))
        ->assertForbidden();
});

it('resolves the branch card link to the show page', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();

    expect(route('branches.show', $location))
        ->toContain('/branches/'.$location->id);

    $this->get(route('branches.show', $location))->assertOk();
});
