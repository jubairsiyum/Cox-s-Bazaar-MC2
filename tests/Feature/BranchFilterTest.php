<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Location;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * An active branch with a unique code.
 */
function branchFilterBranch(string $name = 'Sadar Outlet'): Branch
{
    return Branch::query()->create([
        'name' => $name,
        'code' => strtoupper(fake()->unique()->lexify('BF##')),
        'status' => 'active',
    ]);
}

/**
 * A verified Outlet Manager assigned to the given location.
 */
function branchFilterManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('filters stock transfers by the branch of either location', function () {
    actingAsSuperAdmin();

    $branch = branchFilterBranch();
    $fromInBranch = Location::factory()->create(['branch_id' => $branch->id]);
    $toInBranch = Location::factory()->create(['branch_id' => $branch->id]);
    $outside = Location::factory()->create();

    $leaving = StockTransfer::factory()->create(['from_location_id' => $fromInBranch->id, 'to_location_id' => $outside->id]);
    $arriving = StockTransfer::factory()->create(['from_location_id' => $outside->id, 'to_location_id' => $toInBranch->id]);
    $unrelated = StockTransfer::factory()->create(['from_location_id' => $outside->id, 'to_location_id' => Location::factory()->create()->id]);

    $this->get(route('stock-transfers.index', ['branch' => $branch->id]))
        ->assertOk()
        ->assertSee($leaving->reference_no)
        ->assertSee($arriving->reference_no)
        ->assertDontSee($unrelated->reference_no);
});

it('filters stock adjustments by the branch of their location', function () {
    actingAsSuperAdmin();

    $branch = branchFilterBranch();
    $inBranch = Location::factory()->create(['branch_id' => $branch->id]);
    $outside = Location::factory()->create();

    $mine = StockAdjustment::factory()->create(['location_id' => $inBranch->id]);
    $theirs = StockAdjustment::factory()->create(['location_id' => $outside->id]);

    $this->get(route('stock-adjustments.index', ['branch' => $branch->id]))
        ->assertOk()
        ->assertSee($mine->reference_no)
        ->assertDontSee($theirs->reference_no);
});

it('scopes the inventory ledger and movement modal to the selected branch', function () {
    actingAsSuperAdmin();

    $branch = branchFilterBranch();
    $inBranch = Location::factory()->create(['branch_id' => $branch->id]);
    $outside = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 10]);

    StockMovement::factory()->create(['unit_id' => $unit->id, 'location_id' => $inBranch->id, 'note' => 'Branch movement marker']);
    StockMovement::factory()->create(['unit_id' => $unit->id, 'location_id' => $outside->id, 'note' => 'Outside movement marker']);

    $this->get(route('stocks.index', ['branch' => $branch->id]))
        ->assertOk()
        ->assertSee('Branch movement marker')
        ->assertDontSee('Outside movement marker');

    $this->getJson(route('stocks.movements', [$unit, 'branch' => $branch->id]))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.note', 'Branch movement marker');
});

it('composes the branch filter with the existing filters', function () {
    actingAsSuperAdmin();

    $branch = branchFilterBranch();
    $inBranch = Location::factory()->create(['branch_id' => $branch->id]);
    $outside = Location::factory()->create();

    $mine = StockTransfer::factory()->create(['from_location_id' => $inBranch->id, 'to_location_id' => $outside->id]);
    $theirs = StockTransfer::factory()->create(['from_location_id' => $outside->id, 'to_location_id' => Location::factory()->create()->id]);

    $this->get(route('stock-transfers.index', ['branch' => $branch->id, 'search' => $mine->reference_no]))
        ->assertOk()
        ->assertSee($mine->reference_no)
        ->assertDontSee($theirs->reference_no);
});

it('filters each list to nothing for a branch without locations', function () {
    actingAsSuperAdmin();

    $emptyBranch = branchFilterBranch('Empty Outlet');

    $transfer = StockTransfer::factory()->create();
    $adjustment = StockAdjustment::factory()->create(['location_id' => Location::factory()->create()->id]);
    $unit = Unit::factory()->create(['quantity' => 10]);
    StockMovement::factory()->create(['unit_id' => $unit->id, 'location_id' => Location::factory()->create()->id]);

    $this->get(route('stock-transfers.index', ['branch' => $emptyBranch->id]))
        ->assertOk()
        ->assertDontSee($transfer->reference_no)
        ->assertSee('No stock transfers found.');

    $this->get(route('stock-adjustments.index', ['branch' => $emptyBranch->id]))
        ->assertOk()
        ->assertDontSee($adjustment->reference_no)
        ->assertSee('No stock adjustments found.');

    $this->get(route('stocks.index', ['branch' => $emptyBranch->id]))
        ->assertOk()
        ->assertSee('No stock movements recorded for this scope yet.');
});

it('offers the branch filter to unrestricted users only', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee('name="branch"', false);

    $this->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee('name="branch"', false);

    $this->get(route('stocks.index'))
        ->assertOk()
        ->assertSee('name="branch"', false);

    $manager = branchFilterManagerAt(Location::factory()->create());

    $this->actingAs($manager)
        ->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertDontSee('name="branch"', false);
});

it('keeps restricted users on their automatic scoping', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $manager = branchFilterManagerAt($mine);

    $mineAdjustment = StockAdjustment::factory()->create(['location_id' => $mine->id]);
    $otherAdjustment = StockAdjustment::factory()->create(['location_id' => $other->id]);

    $this->actingAs($manager)
        ->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee($mineAdjustment->reference_no)
        ->assertDontSee($otherAdjustment->reference_no);
});
