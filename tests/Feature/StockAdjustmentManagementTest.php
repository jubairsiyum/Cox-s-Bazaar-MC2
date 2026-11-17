<?php

declare(strict_types=1);

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
function adjustmentPayload(Unit $unit, string $type = 'set', int $quantity = 5, float $unitPrice = 200.0, ?string $reason = 'Stock count correction'): array
{
    return [
        'reference_no' => null,
        'adjustment_date' => now()->format('Y-m-d\TH:i'),
        'type' => $type,
        'reason' => $reason,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
        ],
    ];
}

it('lists stock adjustments', function () {
    actingAsSuperAdmin();

    $adjustment = StockAdjustment::factory()->create();

    $this->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertSee($adjustment->reference_no)
        ->assertSee('Adjustment type', false);
});

it('shows the add stock adjustment form', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-adjustments.create'))
        ->assertOk()
        ->assertSee('Adjustment type', false)
        ->assertSee('Search products to adjust', false)
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

it('adds stock to a product', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'add', 5, 200.0, 'Found in back store'))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    expect($adjustment->reference_no)->toStartWith('ADJ')
        ->and((float) $adjustment->total_amount)->toBe(1000.0)
        ->and((int) $unit->fresh()->quantity)->toBe(35);

    $item = $adjustment->items->first();

    expect($item->quantity_before)->toBe(30)
        ->and($item->quantity_after)->toBe(35);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect($movement->quantity)->toBe(5);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => StockAdjustment::class,
        'auditable_id' => $adjustment->id,
    ]);
});

it('removes stock from a product', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'remove', 5, 200.0, 'Damaged in flood'))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and(StockAdjustment::first()->items->first()->quantity_after)->toBe(25)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-5);
});

it('sets stock to the counted quantity', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22, 200.0, 'Monthly stock take'))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(22)
        ->and($adjustment->items->first()->quantity_before)->toBe(30)
        ->and($adjustment->items->first()->quantity_after)->toBe(22)
        ->and($unit->fresh()->stockMovements()->latest('id')->first()->quantity)->toBe(-8);
});

it('validates the adjustment form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();

    $this->post(route('stock-adjustments.store'), [
        'items' => [],
    ])->assertSessionHasErrors(['adjustment_date', 'type', 'reason', 'items']);
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

it('deletes an adjustment and restores stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

it('prevents deleting an adjustment once stock has moved since posting', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    // Another stock event happens after the adjustment was posted.
    StockService::adjust($unit, 25);

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('stock_adjustments', ['id' => $adjustment->id, 'deleted_at' => null]);
    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('updates an adjustment and re-posts stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 25))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and((int) $adjustment->fresh()->items->first()->quantity_after)->toBe(25);
});

it('blocks editing an adjustment once stock has moved since posting', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'set', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    StockService::adjust($unit, 25);

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'set', 26))
        ->assertSessionHas('error');

    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('denies access without the stock-adjustments.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('stock-adjustments.index'))->assertForbidden();
});
