<?php

declare(strict_types=1);

use App\Models\Location;
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
 * A minimal valid transfer payload for the given unit and status.
 *
 * @return array<string, mixed>
 */
function transferPayload(Unit $unit, string $status = 'draft', int $quantity = 5, float $unitPrice = 200.0, float $shipping = 100.0): array
{
    return [
        'transfer_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'status' => $status,
        'from_location_id' => Location::factory()->create()->id,
        'to_location_id' => Location::factory()->create()->id,
        'shipping_charges' => $shipping,
        'additional_notes' => 'Handle with care',
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
        ],
    ];
}

it('lists stock transfers', function () {
    actingAsSuperAdmin();

    $transfer = StockTransfer::factory()->create();

    $this->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee($transfer->reference_no)
        ->assertSee('Location (From)', false);
});

it('shows the add stock transfer form', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-transfers.create'))
        ->assertOk()
        ->assertSee('Location (From)', false)
        ->assertSee('Search products to transfer', false)
        ->assertSee('Shipping Charges');
});

it('creates a stock transfer with line items and an auto reference number', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $response = $this->post(route('stock-transfers.store'), transferPayload($unit));

    $response->assertRedirect(route('stock-transfers.index'));

    $transfer = StockTransfer::first();

    expect($transfer)->not->toBeNull()
        ->and($transfer->reference_no)->toStartWith('TRF')
        ->and((float) $transfer->total_amount)->toBe(1100.0)
        ->and($transfer->items)->toHaveCount(1)
        ->and((float) $transfer->items->first()->subtotal)->toBe(1000.0);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => StockTransfer::class,
        'auditable_id' => $transfer->id,
    ]);
});

it('validates the stock transfer form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();
    $location = Location::factory()->create();

    $this->post(route('stock-transfers.store'), [
        'transfer_date' => now()->format('Y-m-d\TH:i'),
        'from_location_id' => $location->id,
        'to_location_id' => $location->id,
        'items' => [],
    ])->assertSessionHasErrors(['status', 'to_location_id', 'items']);
});

it('does not move stock while a transfer is a draft', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('stock-transfers.store'), transferPayload($unit))->assertRedirect();

    expect($unit->fresh()->quantity)->toBe(50)
        ->and($unit->stockMovements()->count())->toBe(1); // opening stock only
});

it('moves stock out when a transfer is dispatched and back in when received', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    $this->patch(route('stock-transfers.update', $transfer), array_merge($payload, ['status' => 'in-transit']))
        ->assertRedirect(route('stock-transfers.index'));

    expect($unit->fresh()->quantity)->toBe(45);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'transfer_out',
        'quantity' => -5,
    ]);

    // The receiving side approves the transfer to receive the stock.
    $this->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect($unit->fresh()->quantity)->toBe(50);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'transfer_in',
        'quantity' => 5,
    ]);
});

it('returns stock when an in-transit transfer is cancelled', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    $this->patch(route('stock-transfers.update', $transfer), array_merge($payload, ['status' => 'in-transit']))->assertRedirect();
    expect($unit->fresh()->quantity)->toBe(45);

    $this->patch(route('stock-transfers.update', $transfer), array_merge($payload, ['status' => 'cancelled']))->assertRedirect();
    expect($unit->fresh()->quantity)->toBe(50);
});

it('blocks dispatching more stock than available', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 3]);

    $this->post(route('stock-transfers.store'), transferPayload($unit, 'in-transit', 5))
        ->assertSessionHas('error');

    $this->assertDatabaseCount('stock_transfers', 0);
    expect($unit->fresh()->quantity)->toBe(3);
});

it('rejects an invalid status transition', function () {
    actingAsSuperAdmin();

    $transfer = StockTransfer::factory()->cancelled()->create();

    $this->patch(route('stock-transfers.update', $transfer), ['status' => 'in-transit'])
        ->assertSessionHasErrors('status');
});

it('freezes line items once a transfer has left the source', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    $this->patch(route('stock-transfers.update', $transfer), array_merge($payload, ['status' => 'in-transit']))->assertRedirect();

    // Attempting to change quantities after dispatch is ignored.
    $this->patch(route('stock-transfers.update', $transfer), array_merge($payload, [
        'status' => 'in-transit',
        'items' => [['unit_id' => $unit->id, 'quantity' => 20, 'unit_price' => 200.0]],
    ]))->assertRedirect(route('stock-transfers.index'));

    expect($transfer->items()->first()->quantity)->toBe(5)
        ->and($unit->fresh()->quantity)->toBe(45);
});

it('deletes a draft transfer', function () {
    actingAsSuperAdmin();

    $transfer = StockTransfer::factory()->create();

    $this->delete(route('stock-transfers.destroy', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    $this->assertSoftDeleted('stock_transfers', ['id' => $transfer->id]);
});

it('prevents deleting an in-transit transfer', function () {
    actingAsSuperAdmin();

    $transfer = StockTransfer::factory()->inTransit()->create();

    $this->delete(route('stock-transfers.destroy', $transfer))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'deleted_at' => null]);
});

it('searches products for the transfer form', function () {
    actingAsSuperAdmin();

    stockRowFor('iPhone 16 Silicone Case');

    $this->getJson(route('stock-transfers.search-units', ['q' => 'iPhone']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.product_name', 'iPhone 16 Silicone Case');
});

it('denies access without the stock-transfers.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('stock-transfers.index'))->assertForbidden();
});

/**
 * A verified Outlet Manager assigned to the given location.
 */
function transferManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'location_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('lets the receiving manager accept a submitted draft request', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit, 'draft', 5);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    expect($unit->fresh()->quantity)->toBe(50);

    $this->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect($transfer->fresh()->status->value)->toBe('received')
        ->and($unit->fresh()->quantity)->toBe(50);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'transfer_out',
        'quantity' => -5,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'transfer_in',
        'quantity' => 5,
    ]);
});

it('marks a rejected transfer as rejected and returns dispatched stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit, 'draft', 5);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $draft = StockTransfer::first();

    $this->patch(route('stock-transfers.reject', $draft))->assertRedirect();

    expect($draft->fresh()->status->value)->toBe('rejected')
        ->and($unit->fresh()->quantity)->toBe(50);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $dispatched = StockTransfer::latest('id')->first();

    $this->patch(route('stock-transfers.update', $dispatched), array_merge($payload, ['status' => 'in-transit']))->assertRedirect();
    expect($unit->fresh()->quantity)->toBe(45);

    $this->patch(route('stock-transfers.reject', $dispatched))->assertRedirect();

    expect($dispatched->fresh()->status->value)->toBe('rejected')
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('lets the sending manager cancel a pending transfer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit, 'draft', 5);

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    $this->patch(route('stock-transfers.cancel', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    expect($transfer->fresh()->status->value)->toBe('cancelled')
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('notifies the receiving managers on creation and the creator on decisions', function () {
    $admin = actingAsSuperAdmin();

    $to = Location::factory()->create();
    $receiver = transferManagerAt($to);
    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit, 'draft', 5);
    $payload['to_location_id'] = $to->id;

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $transfer = StockTransfer::first();

    $notifications = $receiver->fresh()->notifications();

    expect($notifications->count())->toBe(1)
        ->and($notifications->first()->data['type'])->toBe('warning');

    $this->patch(route('stock-transfers.approve', $transfer))->assertRedirect();

    expect($admin->fresh()->notifications()->first()->data['type'])->toBe('success');

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $second = StockTransfer::latest('id')->first();

    $this->patch(route('stock-transfers.reject', $second))->assertRedirect();

    expect(collect($admin->fresh()->notifications)->pluck('data.type')->all())
        ->toContain('error');

    $this->post(route('stock-transfers.store'), $payload)->assertRedirect();
    $third = StockTransfer::latest('id')->first();

    $this->patch(route('stock-transfers.cancel', $third))->assertRedirect();

    $receiverTypes = collect($receiver->fresh()->notifications)->pluck('data.type')->all();
    $creatorTypes = collect($admin->fresh()->notifications)->pluck('data.type')->all();

    expect($receiverTypes)->toContain('warning')
        ->and($receiverTypes)->toContain('info')
        ->and($creatorTypes)->toContain('success')
        ->and($creatorTypes)->toContain('error');
});

it('rejects creating a transfer with a decided status', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $payload = transferPayload($unit, 'draft', 5);

    $this->post(route('stock-transfers.store'), array_merge($payload, ['status' => 'received']))
        ->assertSessionHasErrors('status');

    $this->post(route('stock-transfers.store'), array_merge($payload, ['status' => 'rejected']))
        ->assertSessionHasErrors('status');

    $this->assertDatabaseCount('stock_transfers', 0);
});

it('blocks an outlet manager from dispatching stock of another location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $to = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $manager = transferManagerAt($mine);

    $this->actingAs($manager)
        ->post(route('stock-transfers.store'), [
            'transfer_date' => now()->format('Y-m-d\TH:i'),
            'reference_no' => null,
            'status' => 'draft',
            'from_location_id' => $other->id,
            'to_location_id' => $to->id,
            'shipping_charges' => 0,
            'additional_notes' => null,
            'items' => [['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200.0]],
        ])
        ->assertSessionHasErrors('from_location_id');

    $this->assertDatabaseCount('stock_transfers', 0);
});
