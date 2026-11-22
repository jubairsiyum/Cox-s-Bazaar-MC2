<?php

declare(strict_types=1);

use App\Enums\StockTransferStatus;
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

function withdrawBranchManager(Location $location): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $user->assignRole('Outlet Manager');

    return $user;
}

function submitWithdrawableRequest(User $requester, Location $source, Unit $unit): StockTransfer
{
    test()->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), [
            'from_location_id' => $source->id,
            'items' => [['unit_id' => $unit->id, 'quantity' => 10]],
        ])
        ->assertRedirect();

    return StockTransfer::query()->latest('id')->firstOrFail();
}

it('shows the withdraw button to the requesting branch on its pending request', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    $requester = withdrawBranchManager($branch);
    $request = submitWithdrawableRequest($requester, $warehouse, $unit);

    $this->actingAs($requester)
        ->get(route('stock-transfers.show', $request))
        ->assertOk()
        ->assertSee('Withdraw Request')
        ->assertSee(route('stock-transfers.withdraw', $request), false);
});

it('withdraws a pending request without moving stock and tells the source', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 50, null, 'Opening stock', $warehouse->id);
    $requester = withdrawBranchManager($branch);
    $request = submitWithdrawableRequest($requester, $warehouse, $unit);

    $this->actingAs($requester)
        ->patch(route('stock-transfers.withdraw', $request))
        ->assertRedirect(route('stock-transfers.index'))
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toBe(StockTransferStatus::Cancelled)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(50)
        ->and(StockService::quantityAt($unit, $branch->id))->toBe(0);

    expect($requester->fresh()->notifications->pluck('data.title')->all())
        ->toContain("Stock request '{$request->reference_no}' withdrawn.");
});

it('stops a branch from withdrawing a request another branch raised', function () {
    $branch = Location::factory()->create();
    $otherBranch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    $request = submitWithdrawableRequest(withdrawBranchManager($branch), $warehouse, $unit);

    $this->actingAs(withdrawBranchManager($otherBranch))
        ->patch(route('stock-transfers.withdraw', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(StockTransferStatus::Requested);
});

it('does not let the requester withdraw once the source has decided', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    $requester = withdrawBranchManager($branch);
    $request = submitWithdrawableRequest($requester, $warehouse, $unit);
    $request->update(['status' => StockTransferStatus::Rejected, 'rejection_reason' => 'Out of stock']);

    $this->actingAs($requester)
        ->patch(route('stock-transfers.withdraw', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(StockTransferStatus::Rejected);
});
