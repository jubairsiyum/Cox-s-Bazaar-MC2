<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Product;
use App\Models\StockAdjustment;
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
 * A minimal valid adjustment payload for the given stock line.
 *
 * @return array<string, mixed>
 */
function adjustmentPayload(Unit $unit, string $type = 'set', int $quantity = 5, float $unitPrice = 200.0, ?string $reason = 'Stock count correction', ?Location $location = null): array
{
    return [
        'reference_no' => null,
        'adjustment_date' => now()->format('Y-m-d\TH:i'),
        'location_id' => ($location ?? Location::factory()->create())->id,
        'type' => $type,
        'reason' => $reason,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
        ],
    ];
}

/**
 * A verified Outlet Manager assigned to the given location.
 */
function adjustmentManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'location_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('lists stock adjustments', function () {
    actingAsSuperAdmin();

    $adjustment = StockAdjustment::factory()->create();

    $this->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee($adjustment->reference_no)
        ->assertSee('Adjustment type', false)
        ->assertSee('Awaiting Approval');
});

it('shows the add stock adjustment form', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-adjustments.create'))
        ->assertOk()
        ->assertSee('Adjustment type', false)
        ->assertSee('Search products to adjust', false)
        ->assertSee('Location')
        ->assertSee('Add Stock')
        ->assertSee('Remove Stock')
        ->assertSee('Set Stock (Count)')
        ->assertSee('Reason');
});

it('pre-fills the form when opened from stock levels', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create(['name' => 'iPhone 13', 'storage' => null, 'color' => null]);
    $unit = Unit::factory()->for($product)->create(['quantity' => 12]);

    $this->get(route('stock-adjustments.create', ['unit_id' => $unit->id]))
        ->assertOk()
        ->assertSee('iPhone 13');
});

it('shows the edit form for a pending adjustment', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->get(route('stock-adjustments.edit', $adjustment))
        ->assertOk()
        ->assertSee($adjustment->reference_no)
        ->assertSee('Location');
});

it('keeps stock untouched while the adjustment awaits approval', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Found in back store'))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    expect($adjustment->reference_no)->toStartWith('ADJ')
        ->and((float) $adjustment->total_amount)->toBe(1000.0)
        ->and($adjustment->isPending())->toBeTrue()
        ->and((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => StockAdjustment::class,
        'auditable_id' => $adjustment->id,
    ]);
});

it('notifies the location managers when an adjustment is submitted', function () {
    $admin = actingAsSuperAdmin();

    $location = Location::factory()->create();
    $manager = adjustmentManagerAt($location);
    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Found in back store', $location))
        ->assertRedirect(route('stock-adjustments.index'));

    $notifications = $manager->fresh()->notifications();

    expect($notifications->count())->toBe(1)
        ->and($notifications->first()->data['type'])->toBe('warning')
        ->and($admin->fresh()->notifications()->count())->toBe(0);
});

it('adds stock to a product once approved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->patch(route('stock-adjustments.approve', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect($adjustment->fresh()->isApproved())->toBeTrue()
        ->and((int) $unit->fresh()->quantity)->toBe(35);

    $item = $adjustment->fresh()->items->first();

    expect($item->quantity_before)->toBe(30)
        ->and($item->quantity_after)->toBe(35);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect($movement->quantity)->toBe(5)
        ->and($movement->location_id)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'posted',
        'auditable_type' => StockAdjustment::class,
        'auditable_id' => $adjustment->id,
    ]);
});

it('removes stock from a product once approved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'remove', 5, 200.0, 'Damaged in flood'))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and($adjustment->fresh()->items->first()->quantity_after)->toBe(25)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-5);
});

it('sets stock to the counted quantity once approved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22, 200.0, 'Monthly stock take'))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    expect((int) $unit->fresh()->quantity)->toBe(22)
        ->and($adjustment->fresh()->items->first()->quantity_before)->toBe(30)
        ->and($adjustment->fresh()->items->first()->quantity_after)->toBe(22)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-8);
});

it('notifies the creator when an adjustment is approved', function () {
    $admin = actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5))->assertRedirect();
    $this->patch(route('stock-adjustments.approve', StockAdjustment::first()))->assertRedirect();

    $notifications = $admin->fresh()->notifications();

    expect($notifications->count())->toBe(1)
        ->and($notifications->first()->data['type'])->toBe('success');
});

it('rejects an adjustment request without touching stock', function () {
    $admin = actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.reject', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect($adjustment->fresh()->isRejected())->toBeTrue()
        ->and((int) $unit->fresh()->quantity)->toBe(30)
        ->and($unit->fresh()->stockMovements()->count())->toBe(1); // opening stock only

    expect($admin->fresh()->notifications()->first()->data['type'])->toBe('error');
});

it('prevents deciding an adjustment twice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();
    $this->patch(route('stock-adjustments.reject', $adjustment))->assertSessionHas('error');

    expect($adjustment->fresh()->isApproved())->toBeTrue();
});

it('validates the adjustment form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();

    $this->post(route('stock-adjustments.store'), [
        'items' => [],
    ])->assertSessionHasErrors(['adjustment_date', 'type', 'reason', 'items', 'location_id']);
});

it('requires a reason', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, null))
        ->assertSessionHasErrors('reason');
});

it('rejects removing more than the available stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 3]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'remove', 5))
        ->assertSessionHasErrors('items.0.quantity');

    expect((int) $unit->fresh()->quantity)->toBe(3);
});

it('rejects a negative counted quantity', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', -5))
        ->assertSessionHasErrors('items.0.quantity');
});

it('rejects duplicate products in the items', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $payload = adjustmentPayload($unit, 'add', 5);
    $payload['items'][] = ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 100.0];

    $this->post(route('stock-adjustments.store'), $payload)
        ->assertSessionHasErrors('items.1.unit_id');
});

it('deletes a pending adjustment without touching stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

it('reverses and deletes an approved adjustment while stock is unchanged', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();
    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    expect((int) $unit->fresh()->quantity)->toBe(22);

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

it('prevents deleting an approved adjustment once stock has moved since posting', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();
    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    // Another stock event happens after the adjustment was applied.
    StockService::adjust($unit, 25);

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('stock_adjustments', ['id' => $adjustment->id, 'deleted_at' => null]);
    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('updates a pending adjustment and applies the new quantities on approval', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 25, 200.0, 'Stock count correction', $adjustment->location))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30)
        ->and((int) $adjustment->fresh()->items->first()->quantity_after)->toBe(25);

    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('blocks editing an approved adjustment', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();
    $this->patch(route('stock-adjustments.approve', $adjustment))->assertRedirect();

    $this->get(route('stock-adjustments.edit', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'))
        ->assertSessionHas('error');

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 26))
        ->assertRedirect(route('stock-adjustments.index'))
        ->assertSessionHas('error');

    expect((int) $unit->fresh()->quantity)->toBe(22);
});

it('scopes the adjustment list to the manager location and blocks booking elsewhere', function () {
    actingAsSuperAdmin();

    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At the other place', $other))->assertRedirect();
    $otherAdjustment = StockAdjustment::first();

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At my place', $mine))->assertRedirect();
    $mineAdjustment = StockAdjustment::latest('id')->first();

    $manager = adjustmentManagerAt($mine);

    $this->actingAs($manager)
        ->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee($mineAdjustment->reference_no)
        ->assertDontSee($otherAdjustment->reference_no);

    $this->actingAs($manager)
        ->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Wrong place', $other))
        ->assertSessionHasErrors('location_id');
});

it('lets an outlet manager approve adjustments for their own location only', function () {
    actingAsSuperAdmin();

    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At the other place', $other))->assertRedirect();
    $otherAdjustment = StockAdjustment::first();

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At my place', $mine))->assertRedirect();
    $mineAdjustment = StockAdjustment::latest('id')->first();

    $manager = adjustmentManagerAt($mine);

    $this->actingAs($manager)
        ->patch(route('stock-adjustments.approve', $otherAdjustment))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('stock-adjustments.approve', $mineAdjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect($mineAdjustment->fresh()->isApproved())->toBeTrue()
        ->and($mineAdjustment->fresh()->approved_by)->toBe($manager->id)
        ->and((int) $unit->fresh()->quantity)->toBe(35);
});

it('denies access without the stock-adjustments.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('stock-adjustments.index'))->assertForbidden();
});
