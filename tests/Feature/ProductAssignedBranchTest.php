<?php

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('assigns a super admin created product to the Main Warehouse and records stock there', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);

    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => 'Warehouse Phone',
        'quantity' => 12,
        'unit' => 'Pcs',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Warehouse Phone')->first();

    expect($product)->not->toBeNull()
        ->and($product->location_id)->toBe($warehouse->id)
        ->and((int) $product->stockRow->quantity)->toBe(12)
        ->and(StockService::quantityAt($product->stockRow, $warehouse->id))->toBe(12)
        ->and(StockService::availableAt($product->stockRow, null))->toBe(0);
});

it('shows the Assigned Branch field on create and persists the chosen branch', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Outlet']);

    actingAsSuperAdmin();

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('Assigned Branch')
        ->assertSee('Main Warehouse');

    $this->post(route('products.store'), [
        'name' => 'Outlet Phone',
        'location_id' => $branch->id,
        'quantity' => 5,
        'unit' => 'Pcs',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Outlet Phone')->first();

    expect($product->location_id)->toBe($branch->id)
        ->and(StockService::quantityAt($product->stockRow, $branch->id))->toBe(5);
});

it('defaults the products index to Main Warehouse products and switches branch via the filter', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Outlet']);

    Product::factory()->create(['name' => 'Warehouse Phone', 'location_id' => $warehouse->id]);
    Product::factory()->create(['name' => 'Outlet Phone', 'location_id' => $branch->id]);

    actingAsSuperAdmin();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Warehouse Phone')
        ->assertDontSee('Outlet Phone');

    $this->get(route('products.index', ['location_id' => $branch->id]))
        ->assertOk()
        ->assertSee('Outlet Phone')
        ->assertDontSee('Warehouse Phone');
});

it('falls back to the Main Warehouse when the assigned branch is null', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);

    $product = ProductService::create(
        ['name' => 'Unassigned Phone', 'created_by' => null, 'location_id' => null],
        0,
        'Pcs',
    );

    expect($product->location_id)->toBe($warehouse->id);
});

it('reassigns a product to another branch on update without moving its stock', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $branch = Location::factory()->brandShop()->create(['name' => 'Brand Shop Outlet']);

    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => 'Reassignable Phone',
        'quantity' => 10,
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Reassignable Phone')->first();
    expect($product->location_id)->toBe($warehouse->id);

    $this->patch(route('products.update', $product), [
        'name' => 'Reassignable Phone',
        'location_id' => $branch->id,
    ])->assertRedirect(route('products.index'));

    expect($product->fresh()->location_id)->toBe($branch->id)
        ->and(StockService::quantityAt($product->stockRow, $warehouse->id))->toBe(10)
        ->and(StockService::quantityAt($product->stockRow, $branch->id))->toBe(0);
});

it('resolves the Main Warehouse by name, then warehouse type, then first active location', function () {
    // No locations: null.
    expect(Location::mainWarehouse())->toBeNull();

    // Name match wins.
    $named = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    expect(Location::mainWarehouse()->id)->toBe($named->id);

    // Without the canonical name, the first warehouse-type location wins.
    Location::query()->whereKey($named->id)->update(['name' => 'Central Depot']);
    $warehouseType = Location::factory()->warehouse()->create(['name' => 'Backup Warehouse']);
    expect(Location::mainWarehouse()->id)->toBe($named->id);

    // Without any warehouse, the first active location wins.
    Location::query()->whereKey($named->id)->update(['type' => LocationType::BrandShop->value]);
    Location::query()->whereKey($warehouseType->id)->update(['type' => LocationType::BrandShop->value]);
    expect(Location::mainWarehouse()->id)->toBe($named->id);
});
