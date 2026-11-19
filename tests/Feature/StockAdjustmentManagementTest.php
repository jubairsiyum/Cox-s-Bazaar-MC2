<?php

declare(strict_types=1);

use App\Models\AuditLog;
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
        'branch_id' => $location->id,
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
        ->assertSee('<span class="badge badge-success">Applied</span>', false);
});

it('renders legacy pending adjustments as applied and keeps rejected rows as history', function () {
    actingAsSuperAdmin();

    $legacy = StockAdjustment::factory()->pending()->create();
    $rejected = StockAdjustment::factory()->rejected()->create();

    $this->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee('<span class="badge badge-success">Applied</span>', false)
        ->assertSee('<span class="badge badge-danger">Rejected</span>', false);

    // Legacy pending rows are treated as completed and stay editable; the
    // rejected ones never touched stock and are immutable history.
    $this->get(route('stock-adjustments.edit', $legacy))->assertOk();

    $this->get(route('stock-adjustments.edit', $rejected))
        ->assertRedirect(route('stock-adjustments.index'))
        ->assertSessionHas('error');
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

it('applies the stock effect immediately when the adjustment is saved', function () {
    $admin = actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Found in back store'))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    // No pending-approval state: the adjustment is applied at once, exactly once.
    expect($adjustment->reference_no)->toStartWith('ADJ')
        ->and((float) $adjustment->total_amount)->toBe(1000.0)
        ->and($adjustment->isApproved())->toBeTrue()
        ->and($adjustment->isPending())->toBeFalse()
        ->and((int) $unit->fresh()->quantity)->toBe(35);

    $item = $adjustment->items->first();

    expect((int) $item->quantity_before)->toBe(30)
        ->and((int) $item->quantity_after)->toBe(35);

    // Opening stock plus exactly one adjustment movement.
    expect($unit->fresh()->stockMovements()->count())->toBe(2);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => StockAdjustment::class,
        'auditable_id' => $adjustment->id,
    ]);

    $audit = AuditLog::query()->where('auditable_id', $adjustment->id)->latest('id')->first();

    expect($audit->description)->toContain('applied to stock');
});

it('does not notify anyone when an adjustment is applied', function () {
    $admin = actingAsSuperAdmin();

    $location = Location::factory()->create();
    $manager = adjustmentManagerAt($location);
    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Found in back store', $location))
        ->assertRedirect(route('stock-adjustments.index'));

    // There is no approval step left to alert anyone about.
    expect($manager->fresh()->notifications()->count())->toBe(0)
        ->and($admin->fresh()->notifications()->count())->toBe(0);
});

it('adds stock to a product when saved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect($adjustment->isApproved())->toBeTrue()
        ->and((int) $unit->fresh()->quantity)->toBe(35);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect($movement->quantity)->toBe(5)
        ->and($movement->location_id)->not->toBeNull();
});

it('removes stock from a product when saved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'remove', 5, 200.0, 'Damaged in flood'))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and($adjustment->fresh()->items->first()->quantity_after)->toBe(25)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-5);
});

it('sets stock to the counted quantity when saved', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22, 200.0, 'Monthly stock take'))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(22)
        ->and($adjustment->fresh()->items->first()->quantity_before)->toBe(30)
        ->and($adjustment->fresh()->items->first()->quantity_after)->toBe(22)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-8);
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

it('deletes an adjustment and reverses its stock effect', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(22);

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

it('prevents deleting an adjustment once stock has moved since it was applied', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    // Another stock event happens after the adjustment was applied.
    StockService::adjust($unit, 25);

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('stock_adjustments', ['id' => $adjustment->id, 'deleted_at' => null]);
    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('shows the edit form for an adjustment', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->get(route('stock-adjustments.edit', $adjustment))
        ->assertOk()
        ->assertSee($adjustment->reference_no)
        ->assertSee('Location');
});

it('updates an adjustment by reversing its effect and re-posting the new quantities', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(22);

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 25, 200.0, 'Stock count correction', $adjustment->location))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment->refresh();

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and($adjustment->isApproved())->toBeTrue()
        ->and((int) $adjustment->items->first()->quantity_before)->toBe(30)
        ->and((int) $adjustment->items->first()->quantity_after)->toBe(25);
});

it('blocks editing an adjustment once stock has moved since it was applied', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    // Another stock event happens after the adjustment was applied.
    StockService::adjust($unit, 25);

    $this->get(route('stock-adjustments.edit', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'))
        ->assertSessionHas('error');

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 26, 200.0, 'Stock count correction', $adjustment->location))
        ->assertRedirect(route('stock-adjustments.index'))
        ->assertSessionHas('error');

    expect((int) $unit->fresh()->quantity)->toBe(25);
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

it('lets an outlet manager correct adjustments of their own location only', function () {
    actingAsSuperAdmin();

    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At the other place', $other))->assertRedirect();
    $otherAdjustment = StockAdjustment::first();

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'At my place', $mine))->assertRedirect();
    $mineAdjustment = StockAdjustment::latest('id')->first();

    expect((int) $unit->fresh()->quantity)->toBe(40);

    $manager = adjustmentManagerAt($mine);

    $this->actingAs($manager)
        ->patch(route('stock-adjustments.update', $otherAdjustment), adjustmentPayload($unit, 'add', 1, 200.0, 'Wrong place', $other))
        ->assertForbidden();

    // Their own adjustment is corrected in place: reversed and re-posted.
    $this->actingAs($manager)
        ->patch(route('stock-adjustments.update', $mineAdjustment), adjustmentPayload($unit, 'add', 3, 200.0, 'At my place', $mine))
        ->assertRedirect(route('stock-adjustments.index'));

    expect($mineAdjustment->fresh()->isApproved())->toBeTrue()
        ->and((int) $unit->fresh()->quantity)->toBe(38);
});

it('denies access without the stock-adjustments.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('stock-adjustments.index'))->assertForbidden();
});
