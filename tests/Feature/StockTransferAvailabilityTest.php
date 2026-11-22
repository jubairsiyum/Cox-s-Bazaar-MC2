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
 * A transfer payload between two locations with the given [unit, quantity] lines.
 *
 * @param  array<int, array{0: Unit, 1: int}>  $lines
 * @return array<string, mixed>
 */
function availabilityTransferPayload(Location $from, Location $to, array $lines, string $status = 'draft'): array
{
    return [
        'transfer_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'status' => $status,
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'shipping_charges' => 0,
        'additional_notes' => null,
        'items' => array_map(
            fn (array $line): array => ['unit_id' => $line[0]->id, 'quantity' => $line[1], 'unit_price' => 200.0],
            $lines
        ),
    ];
}

/**
 * A verified Outlet Manager assigned to the given location.
 */
function availabilityManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('refuses a line that asks for more than the source branch has and names the available quantity', function () {
    actingAsSuperAdmin();

    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $from->id);

    $this->post(route('stock-transfers.store'), availabilityTransferPayload($from, $to, [[$unit, 8]]))
        ->assertSessionHasErrors(['items.0.quantity' => "Only 5 available at {$from->name}."]);

    $this->assertDatabaseCount('stock_transfers', 0);

    expect($unit->fresh()->quantity)->toBe(5)
        ->and(StockService::availableAt($unit, $from->id))->toBe(5);
});

it('shows the line error next to the product when the transfer form is redisplayed', function () {
    actingAsSuperAdmin();

    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $from->id);

    $this->from(route('stock-transfers.create'))
        ->post(route('stock-transfers.store'), availabilityTransferPayload($from, $to, [[$unit, 8]]))
        ->assertRedirect(route('stock-transfers.create'));

    $this->get(route('stock-transfers.create'))
        ->assertOk()
        ->assertSee('Only 5 available at', false);
});

it('checks each line and reports only the lines that exceed the source stock', function () {
    actingAsSuperAdmin();

    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $plenty = Unit::factory()->create(['quantity' => 5]);
    $scarce = Unit::factory()->create(['quantity' => 1]);
    stockAtLocation($plenty, 5, $from->id);
    stockAtLocation($scarce, 1, $from->id);

    $this->post(route('stock-transfers.store'), availabilityTransferPayload($from, $to, [[$plenty, 5], [$scarce, 3]]))
        ->assertSessionHasErrors(['items.1.quantity' => "Only 1 available at {$from->name}."])
        ->assertSessionDoesntHaveErrors('items.0.quantity');

    $this->assertDatabaseCount('stock_transfers', 0);
});

it('transfers within the source stock, moves both branches and notifies the receiving managers', function () {
    $admin = actingAsSuperAdmin();

    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $receiver = availabilityManagerAt($to);
    $unit = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $from->id);

    $this->post(route('stock-transfers.store'), availabilityTransferPayload($from, $to, [[$unit, 5]], 'in-transit'))
        ->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    expect(StockService::availableAt($unit, $from->id))->toBe(0)
        ->and(StockService::availableAt($unit, $to->id))->toBe(0)
        ->and($receiver->fresh()->notifications()->count())->toBe(1)
        ->and($receiver->fresh()->notifications()->first()->data['type'])->toBe('warning');

    $this->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect(StockService::availableAt($unit, $from->id))->toBe(0)
        ->and(StockService::availableAt($unit, $to->id))->toBe(5)
        ->and($unit->fresh()->quantity)->toBe(5)
        ->and($admin->fresh()->notifications()->first()->data['type'])->toBe('success');
});

it('blocks approving a draft whose stock was taken elsewhere before it was approved', function () {
    actingAsSuperAdmin();

    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $from->id);

    $this->post(route('stock-transfers.store'), availabilityTransferPayload($from, $to, [[$unit, 5]]))
        ->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    StockService::sell($unit, 4, null, null, $from->id);

    $this->patch(route('stock-transfers.approve', $transfer))
        ->assertSessionHas('error', "Only 1 available at {$from->name}.");

    expect($transfer->fresh()->status->value)->toBe('draft')
        ->and(StockService::availableAt($unit, $from->id))->toBe(1)
        ->and(StockService::availableAt($unit, $to->id))->toBe(0);
});

it('lets a branch user see and transfer only the stock of their own branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 3, null, null, $other->id);
    StockService::restock($unit, 2, null, null, $mine->id);

    $manager = availabilityManagerAt($mine);

    $this->actingAs($manager)
        ->getJson(route('stock-transfers.search-units', ['q' => $unit->product->name]))
        ->assertOk()
        ->assertJsonPath('items.0.quantity', 2);

    $this->actingAs($manager)
        ->post(route('stock-transfers.store'), availabilityTransferPayload($mine, $to, [[$unit, 3]]))
        ->assertSessionHasErrors(['items.0.quantity' => "Only 2 available at {$mine->name}."]);

    $this->actingAs($manager)
        ->post(route('stock-transfers.store'), availabilityTransferPayload($other, $to, [[$unit, 1]]))
        ->assertSessionHasErrors('from_location_id');

    $this->actingAs($manager)
        ->post(route('stock-transfers.store'), availabilityTransferPayload($mine, $to, [[$unit, 2]], 'in-transit'))
        ->assertRedirect(route('stock-transfers.index'));

    expect(StockService::availableAt($unit, $mine->id))->toBe(0)
        ->and(StockService::availableAt($unit, $other->id))->toBe(3)
        ->and(StockTransfer::count())->toBe(1);
});
