<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('refuses a sale that would drive branch stock below zero and leaves the quantity unchanged', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 5, null, null, $location->id);

    expect(fn () => StockService::sell($unit, 6, null, null, $location->id))
        ->toThrow(InsufficientStockException::class, 'only 5 available there');

    expect($unit->fresh()->quantity)->toBe(5)
        ->and(StockService::availableAt($unit, $location->id))->toBe(5);
});

it('refuses a sale that would drive overall stock below zero', function () {
    $unit = Unit::factory()->create(['quantity' => 3]);

    expect(fn () => StockService::sell($unit, 5))
        ->toThrow(InsufficientStockException::class, 'requested 5, available 3');

    expect($unit->fresh()->quantity)->toBe(3);
});

it('refuses a POS sale of stock the branch does not hold and keeps the quantity', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 1, null, null, $location->id);

    $this->from(route('pos.index'))
        ->post(route('pos.store'), [
            'customer_id' => null,
            'location_id' => $location->id,
            'paid_amount' => 1000,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 0],
            ],
        ])
        ->assertSessionHas('error');

    expect($unit->fresh()->quantity)->toBe(1)
        ->and(StockService::availableAt($unit, $location->id))->toBe(1)
        ->and(SalesInvoice::count())->toBe(0);
});

it('refuses a purchase return that would drive branch stock below zero', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 2, null, null, $location->id);

    expect(fn () => StockService::purchaseReturn($unit, 3, null, null, $location->id))
        ->toThrow(InsufficientStockException::class);

    expect($unit->fresh()->quantity)->toBe(2);
});

it('refuses a stock adjustment that would take a branch below zero', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 5, null, null, null);

    expect(fn () => StockService::adjust($unit, 2, null, $location->id))
        ->toThrow(InsufficientStockException::class);

    expect($unit->fresh()->quantity)->toBe(5)
        ->and(StockService::availableAt($unit, $location->id))->toBe(0);
});

it('refuses a direct write that would take the stock below zero', function () {
    $unit = Unit::factory()->create(['quantity' => 4]);

    expect(fn () => $unit->update(['quantity' => -1]))
        ->toThrow(InsufficientStockException::class);

    expect($unit->fresh()->quantity)->toBe(4);
});

it('allows negative stock only when the settings permit it', function () {
    Setting::set('allow_negative_stock', '1');

    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 5, null, null, $location->id);

    StockService::sell($unit, 6, null, null, $location->id);

    expect($unit->fresh()->quantity)->toBe(-1)
        ->and(StockService::availableAt($unit, $location->id))->toBe(-1);
});

it('clamps negative branch stock to zero through the migration without changing overall stock', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    StockService::restock($unit, 100, null, null, null);

    DB::table('stock_movements')->insert([
        'unit_id' => $unit->id,
        'location_id' => $location->id,
        'type' => 'adjustment',
        'quantity' => -60,
        'quantity_after' => 40,
        'note' => 'Legacy negative branch stock.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('units')->where('id', $unit->id)->update(['quantity' => 40]);

    expect(StockService::availableAt($unit, $location->id))->toBe(-60);

    $migration = require glob(database_path('migrations/*_clamp_negative_location_stock.php'))[0];
    $migration->up();

    expect(StockService::availableAt($unit, $location->id))->toBe(0)
        ->and(StockService::availableAt($unit, null))->toBe(40)
        ->and($unit->fresh()->quantity)->toBe(40)
        ->and((int) StockMovement::query()->where('unit_id', $unit->id)->sum('quantity'))->toBe(40);
});
