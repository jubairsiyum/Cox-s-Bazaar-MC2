<?php

use App\Enums\StockMovementType;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the products list', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing products', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('products.index'))->assertForbidden();
});

it('lists products for a Super Admin', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create(['name' => 'iPhone 15 Pro Max']);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Products List')
        ->assertSee('iPhone 15 Pro Max');
});

it('shows the product create page with the barcode field', function () {
    actingAsSuperAdmin();

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('Product Name')
        ->assertSee('Barcode / IMEI');
});

it('creates a product and automatically assigns a PRD code', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('products.store'), [
        'name' => 'iPhone 15 Pro Max',
        'model' => 'A2890',
        'barcode' => '356789012345678',
        'sale_price' => '150000',
    ]);

    $response->assertRedirect(route('products.index'));

    $product = Product::where('name', 'iPhone 15 Pro Max')->first();

    expect($product)->not->toBeNull()
        ->and($product->product_id)->toStartWith('PRD')
        ->and($product->barcode)->toBe('356789012345678')
        ->and($product->status)->toBe('active');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Product::class,
        'auditable_id' => $product->id,
    ]);
});

it('stores the uploaded photo path when creating a product', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $response = $this->post(route('products.store'), [
        'name' => 'iPhone 15 Pro Max',
        'photo' => UploadedFile::fake()->image('iphone.png'),
    ]);

    $response->assertRedirect(route('products.index'));

    $product = Product::where('name', 'iPhone 15 Pro Max')->first();

    expect($product)->not->toBeNull()
        ->and($product->photo)->toStartWith('product-photos/');

    Storage::disk('public')->assertExists($product->photo);
});

it('allows uploading an svg photo when creating a product', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>';

    $response = $this->post(route('products.store'), [
        'name' => 'Pixel 9 Pro Svg',
        'photo' => UploadedFile::fake()->createWithContent('pixel.svg', $svgContent)->mimeType('image/svg+xml'),
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Pixel 9 Pro Svg')->first();

    expect($product)->not->toBeNull()
        ->and($product->photo)->toStartWith('product-photos/');

    Storage::disk('public')->assertExists($product->photo);
});

it('creates a product without a photo since the field is optional', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $this->post(route('products.store'), [
        'name' => 'iPhone 15 Pro Max',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'iPhone 15 Pro Max')->first();

    expect($product)->not->toBeNull()
        ->and($product->photo)->toBeNull();
});

it('fails validation when the product photo is not an image', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $this->post(route('products.store'), [
        'name' => 'iPhone 15 Pro Max',
        'photo' => UploadedFile::fake()->createWithContent('photo.txt', 'not an image'),
    ])->assertSessionHasErrors('photo');

    expect(Product::where('name', 'iPhone 15 Pro Max')->first())->toBeNull();
});

it('shows the product photo in the products list', function () {
    actingAsSuperAdmin();

    Product::factory()->create([
        'name' => 'iPhone 15 Pro Max',
        'photo' => 'product-photos/iphone.png',
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('product-photos/iphone.png', false);
});

it('fails validation when product name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => '',
    ])->assertSessionHasErrors('name');
});

it('fails validation when barcode is duplicated', function () {
    actingAsSuperAdmin();

    Product::factory()->create(['barcode' => '356789012345678']);

    $this->post(route('products.store'), [
        'name' => 'Another Phone',
        'barcode' => '356789012345678',
    ])->assertSessionHasErrors('barcode');
});

it('updates an existing product', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create(['name' => 'Old Phone Name']);

    $this->patch(route('products.update', $product), [
        'name' => 'Updated Phone Name',
        'barcode' => '998877665544332',
    ])->assertRedirect(route('products.index'));

    expect($product->fresh()->name)->toBe('Updated Phone Name')
        ->and($product->fresh()->barcode)->toBe('998877665544332');
});

it('keeps the current photo when updating a product without a new upload', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $product = Product::factory()->create([
        'name' => 'Old Phone Name',
        'photo' => 'product-photos/old.png',
    ]);

    Storage::disk('public')->put('product-photos/old.png', 'old-photo');

    $this->patch(route('products.update', $product), [
        'name' => 'Updated Phone Name',
    ])->assertRedirect(route('products.index'));

    expect($product->fresh()->photo)->toBe('product-photos/old.png');

    Storage::disk('public')->assertExists('product-photos/old.png');
});

it('replaces the product photo and removes the previous file on update', function () {
    actingAsSuperAdmin();
    Storage::fake('public');

    $product = Product::factory()->create([
        'name' => 'Old Phone Name',
        'photo' => 'product-photos/old.png',
    ]);

    Storage::disk('public')->put('product-photos/old.png', 'old-photo');

    $this->patch(route('products.update', $product), [
        'name' => 'Updated Phone Name',
        'photo' => UploadedFile::fake()->image('new-photo.png'),
    ])->assertRedirect(route('products.index'));

    $photo = $product->fresh()->photo;

    expect($photo)->not->toBe('product-photos/old.png')
        ->and($photo)->toStartWith('product-photos/');

    Storage::disk('public')->assertExists($photo);
    Storage::disk('public')->assertMissing('product-photos/old.png');
});

it('soft deletes a product', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create(['name' => 'To Delete']);

    $this->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('toggles product status between active and inactive', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create(['status' => 'active']);

    $this->patch(route('products.toggle-status', $product))->assertRedirect();
    expect($product->fresh()->status)->toBe('inactive');

    $this->patch(route('products.toggle-status', $product))->assertRedirect();
    expect($product->fresh()->status)->toBe('active');
});

it('searches products by name', function () {
    actingAsSuperAdmin();

    Product::factory()->create(['name' => 'Galaxy S24 Ultra']);
    Product::factory()->create(['name' => 'Redmi Note 13']);

    $this->get(route('products.index', ['search' => 'Galaxy']))
        ->assertOk()
        ->assertSee('Galaxy S24 Ultra')
        ->assertDontSee('Redmi Note 13');
});

it('creates a stock row and an opening movement for a new product', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => 'iPhone 13',
        'storage' => '128GB',
        'color' => 'Black',
        'quantity' => 12,
        'unit' => 'Pcs',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'iPhone 13')->first();

    expect($product->stockRow)->not->toBeNull()
        ->and($product->stockRow->quantity)->toBe(12)
        ->and($product->stockRow->unit)->toBe('Pcs')
        ->and($product->stockRow->stockMovements()->count())->toBe(1);

    // The product now shows up in the Units section as a single row.
    $this->get(route('units.index'))
        ->assertOk()
        ->assertSee('iPhone 13 (128GB / Black)');
});

it('adds products without opening quantity to the units list', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => 'Galaxy A55 Case',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Galaxy A55 Case')->first();

    expect($product->stockRow)->not->toBeNull()
        ->and($product->stockRow->quantity)->toBe(0);
});

it('rejects a duplicate product variant', function () {
    actingAsSuperAdmin();

    Product::factory()->create(['name' => 'iPhone 13', 'storage' => '128GB', 'color' => 'Black']);

    $this->post(route('products.store'), [
        'name' => 'iPhone 13',
        'storage' => '128GB',
        'color' => 'Black',
    ])->assertSessionHasErrors('name');

    // A different variant of the same product name is a separate row.
    $this->post(route('products.store'), [
        'name' => 'iPhone 13',
        'storage' => '256GB',
        'color' => 'Black',
    ])->assertRedirect(route('products.index'));

    expect(Product::where('name', 'iPhone 13')->count())->toBe(2);
});

it('updates product quantity through the stock ledger', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), ['name' => 'iPhone 13', 'quantity' => 10]);

    $product = Product::where('name', 'iPhone 13')->first();

    $this->patch(route('products.update', $product), [
        'name' => 'iPhone 13',
        'quantity' => 7,
    ])->assertRedirect(route('products.index'));

    $stockRow = $product->stockRow()->first();

    expect((int) $stockRow->quantity)->toBe(7);

    $movement = $stockRow->stockMovements()->latest('id')->first();

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-3);
});

it('deleting a product removes its quantity entry', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), ['name' => 'iPhone 13', 'quantity' => 10]);
    $product = Product::where('name', 'iPhone 13')->first();
    $stockRowId = $product->stockRow->id;

    $this->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

    expect(Product::find($product->id))->toBeNull()
        ->and(Unit::find($stockRowId))->toBeNull();
});

it('updates a product keeping its own name and variant', function () {
    actingAsSuperAdmin();

    $this->post(route('products.store'), [
        'name' => 'iPhone 13',
        'storage' => '128GB',
        'color' => 'Black',
        'model' => 'A2890',
        'barcode' => '356789012345678',
        'purchase_price' => '60000',
        'sale_price' => '70000',
        'quantity' => 10,
        'unit' => 'Pcs',
        'status' => 'active',
        'note' => 'Original note',
    ]);

    $product = Product::where('name', 'iPhone 13')->first();

    // Editing from the products list keeps the same name and variant and only
    // changes another field — it must never trigger the uniqueness rule.
    $this->patch(route('products.update', $product), [
        'name' => 'iPhone 13',
        'storage' => '128GB',
        'color' => 'Black',
        'model' => 'A2890',
        'barcode' => '356789012345678',
        'purchase_price' => '60000',
        'sale_price' => '75000',
        'quantity' => 10,
        'unit' => 'Pcs',
        'status' => 'active',
        'note' => 'Updated note',
    ])->assertRedirect(route('products.index'));

    expect($product->fresh()->note)->toBe('Updated note')
        ->and((float) $product->fresh()->sale_price)->toBe(75000.0);
});

it('matches variants ignoring case and whitespace', function () {
    actingAsSuperAdmin();

    Product::factory()->create(['name' => 'iPhone 13', 'storage' => '128GB', 'color' => 'Black']);

    $this->post(route('products.store'), [
        'name' => '  iphone 13 ',
        'storage' => ' 128gb ',
        'color' => ' black ',
    ])->assertSessionHasErrors('name');
});

it('merges legacy duplicate product variants into a single row', function () {
    actingAsSuperAdmin();

    // Two historical twins of the same variant (created before the rule existed).
    $first = Product::factory()->create(['name' => 'Phone Charger 20W', 'storage' => null, 'color' => null]);
    $second = Product::factory()->create(['name' => 'phone charger 20w', 'storage' => null, 'color' => null]);

    $firstRow = Unit::factory()->for($first)->create(['quantity' => 30, 'unit' => 'Pcs']);
    $secondRow = Unit::factory()->for($second)->create(['quantity' => 12, 'unit' => 'Pcs']);

    $migration = require collect(glob(database_path('migrations/*_dedupe_product_variants.php')))->first();
    $migration->up();

    // A single live variant remains, with the merged quantity and ledger intact.
    expect(Product::query()->whereNull('deleted_at')->matchingVariant('Phone Charger 20W', null, null)->count())->toBe(1)
        ->and(Product::find($second->id))->toBeNull()
        ->and((int) $firstRow->fresh()->quantity)->toBe(42)
        ->and(Unit::find($secondRow->id))->toBeNull()
        ->and((int) $firstRow->fresh()->stockMovements()->sum('quantity'))->toBe(42);

    // And the surviving product can be edited without name conflicts.
    $this->patch(route('products.update', $first), [
        'name' => 'Phone Charger 20W',
        'quantity' => 42,
    ])->assertRedirect(route('products.index'));
});

/**
 * A verified user with products.view at the given branch.
 */
function productBranchUser(Location $location, string $name = 'Branch Viewer'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Sales Staff');

    return $user;
}

/**
 * A product with $quantity units in stock at the given location.
 */
function productStockedAt(string $name, Location $location, int $quantity): Product
{
    $product = Product::factory()->create(['name' => $name, 'storage' => null, 'color' => null]);
    $unit = Unit::factory()->for($product)->create(['quantity' => 0]);
    StockService::restock($unit, $quantity, null, null, $location->id);

    return $product;
}

it('lists only products in stock at the own branch for branch users', function () {
    $mine = Location::factory()->create(['name' => 'Branch A Outlet']);
    $other = Location::factory()->create(['name' => 'Branch B Outlet']);

    productStockedAt('Local Phone', $mine, 5);
    productStockedAt('Away Phone', $other, 7);

    $this->actingAs(productBranchUser($mine))
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Local Phone')
        ->assertDontSee('Away Phone');

    $this->actingAs(productBranchUser($other))
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Away Phone')
        ->assertDontSee('Local Phone');
});

it('shows cross-branch availability when a searched product is not in stock at the own branch', function () {
    $mine = Location::factory()->create(['name' => 'Branch A Outlet']);
    $other = Location::factory()->create(['name' => 'Branch B Outlet']);

    productStockedAt('Local Phone', $mine, 5);
    productStockedAt('Away Phone', $other, 7);

    $ghost = Product::factory()->create(['name' => 'Ghost Phone', 'storage' => null, 'color' => null]);
    Unit::factory()->for($ghost)->create(['quantity' => 0]);

    $viewer = productBranchUser($mine);

    // The unsearched list stays availability-based: own branch only.
    $this->actingAs($viewer)->get(route('products.index'))
        ->assertOk()
        ->assertSee('Local Phone')
        ->assertDontSee('Away Phone')
        ->assertDontSee('Not in stock at your branch');

    // Searching a product that is out of stock here shows its details with
    // the branch that does have it — name and available quantity.
    $this->actingAs($viewer)->get(route('products.index', ['search' => 'Away']))
        ->assertOk()
        ->assertSee('Not in stock at your branch')
        ->assertSee('Away Phone')
        ->assertSee('Branch B Outlet')
        ->assertSee('7 pcs available');

    // A searched product nobody stocks is still identified as unavailable.
    $this->actingAs($viewer)->get(route('products.index', ['search' => 'Ghost']))
        ->assertOk()
        ->assertSee('Ghost Phone')
        ->assertSee('Not available at any branch');
});

it('defaults the Super Admin to the Main Warehouse and shows nothing to unassigned users', function () {
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $other = Location::factory()->create(['name' => 'Branch B Outlet']);

    Product::factory()->create(['name' => 'Warehouse Phone', 'location_id' => $warehouse->id]);
    Product::factory()->create(['name' => 'Away Phone', 'location_id' => $other->id]);

    // Super Admin sees the Main Warehouse by default and can switch branches.
    actingAsSuperAdmin();

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('Warehouse Phone')
        ->assertDontSee('Away Phone')
        ->assertDontSee('Not in stock at your branch');

    $this->get(route('products.index', ['location_id' => $other->id]))
        ->assertOk()
        ->assertSee('Away Phone')
        ->assertDontSee('Warehouse Phone');

    // A restricted user without a branch sees nothing — not even on search.
    $unassigned = User::factory()->create(['email_verified_at' => now()]);
    $unassigned->assignRole('Sales Staff');

    $this->actingAs($unassigned)->get(route('products.index', ['search' => 'Away']))
        ->assertOk()
        ->assertDontSee('Away Phone')
        ->assertDontSee('Branch B Outlet')
        ->assertDontSee('Not in stock at your branch');
});
