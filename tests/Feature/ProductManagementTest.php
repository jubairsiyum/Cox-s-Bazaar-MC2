<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

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
