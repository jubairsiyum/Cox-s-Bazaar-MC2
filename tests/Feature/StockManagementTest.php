<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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
 * Create a product variant with a stock row at the given quantity/cost.
 */
function stockLineWithCost(string $name, int $quantity, float $purchasePrice): Unit
{
    $product = Product::factory()->create([
        'name' => $name,
        'storage' => null,
        'color' => null,
        'purchase_price' => $purchasePrice,
    ]);

    return Unit::factory()->for($product)->create(['quantity' => $quantity, 'unit' => 'Pcs']);
}

it('lists stock levels with valuation and totals', function () {
    actingAsSuperAdmin();

    stockLineWithCost('iPhone 13', 10, 1000.0);

    $this->get(route('stocks.index'))
        ->assertOk()
        ->assertSee('Stocks')
        ->assertSee('iPhone 13')
        ->assertSee('Stock Value')
        ->assertSee('10,000.00');
});

it('flags low and out of stock products', function () {
    actingAsSuperAdmin();

    stockLineWithCost('Low Stock Item', 3, 100.0);
    stockLineWithCost('Gone Item', 0, 100.0);
    stockLineWithCost('Healthy Item', 50, 100.0);

    $this->get(route('stocks.index'))
        ->assertOk()
        ->assertSee('Low stock')
        ->assertSee('Out of stock')
        ->assertSee('In stock');
});

it('filters by stock status', function () {
    actingAsSuperAdmin();

    stockLineWithCost('Gone Item', 0, 100.0);
    stockLineWithCost('Healthy Item', 50, 100.0);

    $this->get(route('stocks.index', ['stock_status' => 'out']))
        ->assertOk()
        ->assertSee('Gone Item')
        ->assertDontSee('Healthy Item');
});

it('returns the stock ledger for a product', function () {
    actingAsSuperAdmin();

    $unit = stockLineWithCost('iPhone 13', 10, 1000.0);

    StockService::adjust($unit, 7);

    $this->getJson(route('stocks.movements', $unit))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('product_name', 'iPhone 13')
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.quantity', -3)
        ->assertJsonPath('items.0.quantity_after', 7);
});

it('filters by search term', function () {
    actingAsSuperAdmin();

    stockLineWithCost('iPhone 13', 10, 1000.0);
    stockLineWithCost('Galaxy Charger', 20, 200.0);

    $this->get(route('stocks.index', ['search' => 'iPhone']))
        ->assertOk()
        ->assertSee('iPhone 13')
        ->assertDontSee('Galaxy Charger');
});

it('filters by brand and category', function () {
    actingAsSuperAdmin();

    $brand = Brand::factory()->create(['name' => 'Samsung']);
    $category = Category::factory()->create(['name' => 'Chargers']);

    $product = Product::factory()->create([
        'name' => 'Samsung Charger',
        'storage' => null,
        'color' => null,
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'purchase_price' => 200.0,
    ]);
    Unit::factory()->for($product)->create(['quantity' => 20, 'unit' => 'Pcs']);

    stockLineWithCost('iPhone 13', 10, 1000.0);

    $this->get(route('stocks.index', ['brand_id' => $brand->id]))
        ->assertOk()
        ->assertSee('Samsung Charger')
        ->assertDontSee('iPhone 13');

    $this->get(route('stocks.index', ['category_id' => $category->id]))
        ->assertOk()
        ->assertSee('Samsung Charger')
        ->assertDontSee('iPhone 13');
});

it('offers an adjust stock shortcut for every product', function () {
    actingAsSuperAdmin();

    stockLineWithCost('iPhone 13', 10, 1000.0);

    $this->get(route('stocks.index'))
        ->assertOk()
        ->assertSee('Adjust Stock');
});

it('denies access without the inventory.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('stocks.index'))->assertForbidden();
});
