<?php

declare(strict_types=1);

use App\Models\Location;
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
function adjustmentPayload(Unit $unit, string $type = 'normal', int $quantity = 5, float $unitPrice = 200.0, float $recovered = 0.0, ?string $reason = null): array
{
    return [
        'location_id' => Location::factory()->create()->id,
        'reference_no' => null,
        'adjustment_date' => now()->format('Y-m-d\TH:i'),
        'type' => $type,
        'total_amount_recovered' => $recovered,
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
        ->assertSee('Adjustment type', false)
        ->assertSee('Total amount recovered', false);
});

it('shows the add stock adjustment form', function () {
    actingAsSuperAdmin();

    $this->get(route('stock-adjustments.create'))
        ->assertOk()
        ->assertSee('Business Location', false)
        ->assertSee('Adjustment type', false)
        ->assertSee('Search products for stock adjustment', false)
        ->assertSee('Total amount recovered', false)
        ->assertSee('Reason');
});

it('creates a normal adjustment that corrects stock to the counted quantity', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $response = $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'normal', 22, 200.0));

    $response->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    expect($adjustment)->not->toBeNull()
        ->and($adjustment->reference_no)->toStartWith('ADJ')
        ->and((float) $adjustment->total_amount)->toBe(4400.0)
        ->and((int) $unit->fresh()->quantity)->toBe(22);

    $item = $adjustment->items->first();

    expect($item->quantity_before)->toBe(30)
        ->and($item->quantity_after)->toBe(22);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect($movement->quantity)->toBe(-8);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => StockAdjustment::class,
        'auditable_id' => $adjustment->id,
    ]);
});

it('creates an abnormal adjustment that removes stock and records recovery', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'abnormal', 5, 200.0, 500.0, 'Damaged in flood'))
        ->assertRedirect(route('stock-adjustments.index'));

    $adjustment = StockAdjustment::first();

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and((float) $adjustment->total_amount)->toBe(1000.0)
        ->and((float) $adjustment->total_amount_recovered)->toBe(500.0)
        ->and($adjustment->reason)->toBe('Damaged in flood');
});

it('validates the adjustment form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create();

    $this->post(route('stock-adjustments.store'), [
        'adjustment_date' => now()->format('Y-m-d\TH:i'),
        'items' => [],
    ])->assertSessionHasErrors(['location_id', 'type', 'items']);
});

it('requires a reason for abnormal adjustments', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'abnormal', 5))
        ->assertSessionHasErrors('reason');
});

it('rejects removing more than the available stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 3]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'abnormal', 5, 200.0, 0.0, 'Damaged'))
        ->assertSessionHasErrors('items.0.quantity');

    expect((int) $unit->fresh()->quantity)->toBe(3);
});

it('rejects recovery above the total amount', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'abnormal', 2, 100.0, 500.0, 'Damaged'))
        ->assertSessionHasErrors('total_amount_recovered');
});

it('rejects duplicate products in the items', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $payload = adjustmentPayload($unit, 'normal', 5);
    $payload['items'][] = ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 100.0];

    $this->post(route('stock-adjustments.store'), $payload)
        ->assertSessionHasErrors('items.1.unit_id');
});

it('deletes an adjustment and restores stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'normal', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(30);

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

it('prevents deleting an adjustment once stock has moved since posting', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'normal', 22))->assertRedirect();
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

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'normal', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'normal', 25))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and((int) $adjustment->fresh()->items->first()->quantity_after)->toBe(25);
});

it('blocks editing an adjustment once stock has moved since posting', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 30]);

    $this->post(route('stock-adjustments.store'), adjustmentPayload($unit, 'normal', 22))->assertRedirect();
    $adjustment = StockAdjustment::first();

    StockService::adjust($unit, 25);

    $this->patch(route('stock-adjustments.update', $adjustment), adjustmentPayload($unit, 'normal', 26))
        ->assertSessionHas('error');

    expect((int) $unit->fresh()->quantity)->toBe(25);
});

it('denies access without the stock-adjustments.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('stock-adjustments.index'))->assertForbidden();
});
