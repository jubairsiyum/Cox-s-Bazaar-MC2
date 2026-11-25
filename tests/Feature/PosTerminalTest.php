<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A minimal valid POS sale payload for the given stock row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function posSalePayload(Unit $unit, float $paid, array $overrides = []): array
{
    $location = Location::factory()->create();

    stockAtLocation($unit, 2, $location->id);

    return array_merge([
        'customer_id' => null,
        'location_id' => $location->id,
        'paid_amount' => $paid,
        'note' => null,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 0],
        ],
    ], $overrides);
}

it('renders the POS terminal for POS users with the walk-in option', function () {
    actingAsSuperAdmin();

    $this->get(route('pos.index'))
        ->assertOk()
        ->assertSee('POS Terminal')
        ->assertSee('Walk-in Customer')
        ->assertSee('Complete Sale');
});

it('records a quick sale: creates AND posts the invoice with the stock-out', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 1000))
        ->assertRedirect(route('pos.index'));

    $invoice = SalesInvoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->invoice_no)->toStartWith('INV')
        ->and($invoice->status->value)->toBe('posted')
        ->and($invoice->posted_at)->not->toBeNull()
        ->and($invoice->customer_id)->toBeNull()
        ->and((float) $invoice->grand_total)->toBe(1000.0)
        ->and((float) $invoice->paid_amount)->toBe(1000.0)
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->payment_status->value)->toBe('paid')
        ->and($unit->fresh()->quantity)->toBe(48);
});

it('returns change without tracking it as a customer advance', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 1500))
        ->assertRedirect(route('pos.index'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Change returned'));

    $invoice = SalesInvoice::first();

    expect((float) $invoice->paid_amount)->toBe(1000.0)
        ->and((float) $invoice->due_amount)->toBe(0.0);
});

it('keeps a due when the walk-in underpays', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 400));

    $invoice = SalesInvoice::first();

    expect((float) $invoice->paid_amount)->toBe(400.0)
        ->and((float) $invoice->due_amount)->toBe(600.0)
        ->and($invoice->payment_status->value)->toBe('partial');
});

it('links the sale to a known customer when given', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create();

    $this->post(route('pos.store'), posSalePayload($unit, 1000, ['customer_id' => $customer->id]));

    expect(SalesInvoice::first()->customer_id)->toBe($customer->id);
});

it('blocks the sale when stock is insufficient', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);

    $this->from(route('pos.index'))
        ->post(route('pos.store'), posSalePayload($unit, 1000))
        ->assertSessionHas('error');

    expect(SalesInvoice::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(1);
});

it('requires at least one cart item and a tendered amount', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 1000, ['items' => []]))
        ->assertSessionHasErrors('items');

    $this->post(route('pos.store'), posSalePayload($unit, -5))
        ->assertSessionHasErrors('paid_amount');

    expect(SalesInvoice::count())->toBe(0);
});

it('blocks users without pos.access', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Accountant');

    $this->actingAs($user)->get(route('pos.index'))->assertForbidden();
    $this->actingAs($user)->post(route('pos.store'), posSalePayload(Unit::factory()->create(['quantity' => 10]), 100))->assertForbidden();
});

it('searches products by name and barcode for the POS picker', function () {
    actingAsSuperAdmin();

    $product = Product::factory()->create([
        'name' => 'Samsung Galaxy A55',
        'storage' => null,
        'color' => null,
    ]);
    $unit = Unit::factory()->for($product)->create(['quantity' => 12]);

    $this->get(route('pos.search-units', ['search' => 'Samsung']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('units.0.id', $unit->id)
        ->assertJsonPath('units.0.quantity', 12);
});

it('applies a cart-level discount to the grand total', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 900, ['cart_discount' => 100]))
        ->assertRedirect(route('pos.index'));

    $invoice = SalesInvoice::first();

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->cart_discount)->toBe(100.0)
        ->and((float) $invoice->discount_amount)->toBe(100.0)
        ->and((float) $invoice->grand_total)->toBe(900.0)
        ->and((float) $invoice->paid_amount)->toBe(900.0)
        ->and((float) $invoice->due_amount)->toBe(0.0);
});

it('applies tax after the cart discount', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    // 1000 subtotal − 100 cart discount = 900, + 10% tax = 990.
    $this->post(route('pos.store'), posSalePayload($unit, 990, ['cart_discount' => 100, 'tax_rate' => 10]));

    $invoice = SalesInvoice::first();

    expect((float) $invoice->grand_total)->toBe(990.0)
        ->and((float) $invoice->tax_amount)->toBe(90.0);
});

it('never floors the grand total below zero when the discount exceeds the subtotal', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), posSalePayload($unit, 0, ['cart_discount' => 5000]));

    expect((float) SalesInvoice::first()->grand_total)->toBe(0.0);
});

it('shows only the branch products to a branch user in the POS search', function () {
    $branchA = Location::factory()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->create(['name' => 'Branch B']);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $branchA->id]);
    $manager->assignRole('Outlet Manager');

    $unitA = Unit::factory()->create(['quantity' => 5]);
    $unitB = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unitA, 5, $branchA->id);
    stockAtLocation($unitB, 5, $branchB->id);

    $this->actingAs($manager)
        ->get(route('pos.search-units'))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', $unitA->id)
        ->assertJsonPath('units.0.quantity', 5);
});

it('shows branch availability to the super admin in the POS search', function () {
    actingAsSuperAdmin();

    $branchA = Location::factory()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->create(['name' => 'Branch B']);
    $unit = Unit::factory()->create(['quantity' => 5]);
    stockAtLocation($unit, 3, $branchA->id);
    stockAtLocation($unit, 2, $branchB->id);

    $this->get(route('pos.search-units'))
        ->assertOk()
        ->assertJsonPath('units.0.quantity', 5)
        ->assertJsonPath('units.0.branches.0.name', 'Branch A')
        ->assertJsonPath('units.0.branches.0.quantity', 3)
        ->assertJsonPath('units.0.branches.1.name', 'Branch B')
        ->assertJsonPath('units.0.branches.1.quantity', 2);
});

it('blocks a restricted branch user from spoofing location_id to sell from another branch', function () {
    $branchA = Location::factory()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->create(['name' => 'Branch B']);

    $cashier = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $branchA->id,
    ]);
    $cashier->givePermissionTo(['pos.access', 'sales-invoices.manage']);

    $unit = Unit::factory()->create(['quantity' => 10]);
    stockAtLocation($unit, 5, $branchB->id);

    // Cashier from Branch A attempts to sell from Branch B
    $payload = posSalePayload($unit, 1000, [
        'location_id' => $branchB->id,
    ]);

    $this->actingAs($cashier)
        ->post(route('pos.store'), $payload)
        ->assertSessionHas('error', 'You can only sell from your own branch/location.');

    expect(SalesInvoice::count())->toBe(0);
});

it('enforces catalog unit price and discount bounds for branch staff on POS sales', function () {
    $branch = Location::factory()->brandShop()->create();
    $cashier = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branch->id]);
    $cashier->assignRole('Sales Staff');

    $product = Product::factory()->create(['sale_price' => 15000]);
    $unit = Unit::factory()->for($product)->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $branch->id);

    // Attempt to tamper unit_price down to 1.00
    $this->actingAs($cashier)
        ->post(route('pos.store'), [
            'location_id' => $branch->id,
            'paid_amount' => 15000,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 1, 'discount' => 0],
            ],
        ])
        ->assertRedirect(route('pos.index'));

    $invoice = SalesInvoice::latest('id')->firstOrFail();

    expect((float) $invoice->grand_total)->toBe(15000.0)
        ->and((float) $invoice->items->first()->unit_price)->toBe(15000.0);

    // Attempt to pass cart_discount > subtotal
    $this->actingAs($cashier)
        ->post(route('pos.store'), [
            'location_id' => $branch->id,
            'cart_discount' => 20000,
            'paid_amount' => 0,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 15000, 'discount' => 0],
            ],
        ])
        ->assertSessionHasErrors('cart_discount');
});
