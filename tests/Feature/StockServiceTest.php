<?php

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\StockService;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('deducts stock and records a sale movement when a product is sold', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);

    $movement = StockService::sell($unit, 3);

    expect((int) $unit->fresh()->quantity)->toBe(7)
        ->and($movement->type)->toBe(StockMovementType::Sale)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->quantity_after)->toBe(7);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Unit::class,
        'auditable_id' => $unit->id,
    ]);
});

it('restores stock and records a movement when a sold product is returned', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);
    StockService::sell($unit, 5);

    $movement = StockService::saleReturn($unit, 2);

    expect((int) $unit->fresh()->quantity)->toBe(7)
        ->and($movement->type)->toBe(StockMovementType::SaleReturn)
        ->and($movement->quantity)->toBe(2)
        ->and($movement->quantity_after)->toBe(7);
});

it('increases stock and records a movement when bulk items arrive', function () {
    $unit = Unit::factory()->create(['quantity' => 5, 'unit' => 'Box']);

    $movement = StockService::restock($unit, 20);

    expect((int) $unit->fresh()->quantity)->toBe(25)
        ->and($movement->type)->toBe(StockMovementType::Restock)
        ->and($movement->quantity)->toBe(20)
        ->and($movement->quantity_after)->toBe(25);
});

it('deducts stock and records a movement when items are returned to the supplier', function () {
    $unit = Unit::factory()->create(['quantity' => 12, 'unit' => 'Box']);

    $movement = StockService::purchaseReturn($unit, 4);

    expect((int) $unit->fresh()->quantity)->toBe(8)
        ->and($movement->type)->toBe(StockMovementType::PurchaseReturn)
        ->and($movement->quantity)->toBe(-4)
        ->and($movement->quantity_after)->toBe(8);
});

it('sets the stock to an absolute quantity when adjusted', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);

    $down = StockService::adjust($unit, 3);
    expect((int) $unit->fresh()->quantity)->toBe(3)
        ->and($down->type)->toBe(StockMovementType::Adjustment)
        ->and($down->quantity)->toBe(-7);

    $up = StockService::adjust($unit, 15);
    expect((int) $unit->fresh()->quantity)->toBe(15)
        ->and($up->quantity)->toBe(12)
        ->and($up->quantity_after)->toBe(15);
});

it('rejects a sale that would leave negative stock and keeps the quantity unchanged', function () {
    $unit = Unit::factory()->create(['quantity' => 5, 'unit' => 'Pcs']);

    expect(fn () => StockService::sell($unit, 6))->toThrow(InsufficientStockException::class);

    expect((int) $unit->fresh()->quantity)->toBe(5)
        ->and($unit->stockMovements()->count())->toBe(1); // opening only
});

it('allows negative stock when the allow negative stock setting is enabled', function () {
    Setting::updateOrCreate(['key' => 'allow_negative_stock'], ['value' => '1', 'group' => 'system']);

    $unit = Unit::factory()->create(['quantity' => 5, 'unit' => 'Pcs']);

    StockService::sell($unit, 8);

    expect((int) $unit->fresh()->quantity)->toBe(-3);
});

it('rejects stock operations with a non positive amount', function (string $method) {
    $unit = Unit::factory()->create(['quantity' => 10]);

    expect(fn () => StockService::$method($unit, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => StockService::$method($unit, -3))->toThrow(InvalidArgumentException::class);
})->with(['sell', 'saleReturn', 'restock', 'purchaseReturn']);

it('rejects adjusting the stock to a negative quantity', function () {
    $unit = Unit::factory()->create(['quantity' => 10]);

    expect(fn () => StockService::adjust($unit, -1))->toThrow(InvalidArgumentException::class);
});

it('records no movement when the stock is adjusted to its current quantity', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);

    $movement = StockService::adjust($unit, 10);

    expect($movement)->toBeNull()
        ->and($unit->stockMovements()->count())->toBe(1); // opening only
});

it('keeps the quantity equal to the sum of its movements', function () {
    $unit = Unit::factory()->create(['quantity' => 20, 'unit' => 'Pcs']);

    StockService::sell($unit, 5);
    StockService::saleReturn($unit, 2);
    StockService::restock($unit, 10);
    StockService::purchaseReturn($unit, 4);
    StockService::adjust($unit, 18);

    expect((int) $unit->fresh()->quantity)->toBe(18)
        ->and((int) $unit->stockMovements()->sum('quantity'))->toBe(18);
});

it('records the reference model on movements when provided', function () {
    $unit = Unit::factory()->create(['quantity' => 10]);
    $reference = Category::factory()->create();

    $movement = StockService::sell($unit, 2, reference: $reference);

    expect($movement->reference->is($reference))->toBeTrue();
});

it('records stock changes in the audit log', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);

    StockService::sell($unit, 3);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Unit::class,
        'auditable_id' => $unit->id,
        'description' => "Sold 3 Pcs of '{$unit->displayName()}' (7 remaining).",
    ]);
});
