<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A sales order with one line item.
 */
function salesOrderWithItem(Unit $unit, int $quantity = 3, float $rate = 250.0, array $attributes = []): SalesOrder
{
    $locationId = $attributes['location_id'] ?? Location::factory()->create()->id;

    stockAtLocation($unit, $quantity, $locationId);

    $order = SalesOrder::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => $locationId,
    ], $attributes));

    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $rate,
        'discount' => 0,
        'subtotal' => $quantity * $rate,
    ]);

    $order->update([
        'subtotal' => $quantity * $rate,
        'grand_total' => $quantity * $rate,
    ]);

    return $order->fresh();
}

/**
 * A minimal valid order payload for the given stock row. The customer is
 * created up front so the payload carries a consistent customer_id /
 * customer_name / mobile_no trio (the phone is the customer's own, so
 * the identity rules never flag it).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salesOrderPayload(Unit $unit, array $overrides = []): array
{
    $customer = Customer::factory()->create();
    $location = Location::factory()->create();

    stockAtLocation($unit, 3, $location->id);

    return array_merge([
        'order_date' => now()->format('Y-m-d\TH:i'),
        'expected_date' => now()->addDays(3)->toDateString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => $customer->mobile,
        'location_id' => $location->id,
        'tax_rate' => 0,
        'note' => null,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 3, 'unit_price' => 250, 'discount' => 0],
        ],
    ], $overrides);
}

it('lists sales orders with a Create Order button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->get(route('sales-orders.index'))
        ->assertOk()
        ->assertSee($order->order_no)
        ->assertSee('Create Order');
});

it('creates an order with computed totals and holds the invoice until the payment is confirmed', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit, [
        'tax_rate' => 10,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 100],
        ],
    ]))->assertRedirect();

    $order = SalesOrder::first();

    expect($order)->not->toBeNull()
        ->and($order->order_no)->toStartWith('SO')
        ->and($order->status->value)->toBe('draft')
        ->and((float) $order->subtotal)->toBe(900.0)
        ->and((float) $order->tax_amount)->toBe(90.0)
        ->and((float) $order->grand_total)->toBe(990.0)
        ->and($order->items)->toHaveCount(1);

    // The order is only captured: no invoice exists and the stock is
    // untouched until the payment is confirmed.
    expect(SalesInvoice::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('shows the confirm payment step on the order instead of a Create Invoice button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit));

    $order = SalesOrder::first();

    $this->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertSee('Confirm Payment &amp; Generate Invoice', false)
        ->assertDontSee('Create Invoice');

    $this->get(route('sales-orders.index'))
        ->assertOk()
        ->assertDontSee('Create Invoice');
});

it('voids the generated invoice and restores stock when a paid order is cancelled', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit));

    $order = SalesOrder::first();

    expect($unit->fresh()->quantity)->toBe(50);

    $this->patch(route('sales-orders.confirm', $order), [
        'payment_method' => 'cash',
        'amount_received' => $order->grand_total,
    ])->assertRedirect(route('sales-orders.show', $order));

    $invoice = SalesInvoice::first();

    expect($unit->fresh()->quantity)->toBe(47);

    $this->patch(route('sales-orders.cancel', $order))
        ->assertRedirect(route('sales-orders.show', $order));

    $order->refresh();
    $invoice->refresh();

    expect($order->status->value)->toBe('cancelled')
        ->and($invoice->status->value)->toBe('voided')
        ->and($invoice->voided_at)->not->toBeNull()
        ->and((float) $invoice->due_amount)->toBe(0.0);

    expect($unit->fresh()->quantity)->toBe(50);

    // A cancelled order can no longer be edited or re-confirmed.
    $this->get(route('sales-orders.edit', $order))->assertRedirect(route('sales-orders.show', $order));
    $this->patch(route('sales-orders.confirm', $order), [
        'payment_method' => 'cash',
        'amount_received' => $order->grand_total,
    ])->assertSessionHas('error');
});

it('persists the order without touching stock when nothing is confirmed yet', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit, [
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 250, 'discount' => 0],
        ],
    ]))->assertRedirect();

    // Capturing the order posts nothing: the stock-out only happens when the
    // payment is confirmed and the invoice is generated.
    expect(SalesOrder::count())->toBe(1)
        ->and(SalesInvoice::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(1);
});

it('cancels a draft order that has no invoice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->patch(route('sales-orders.cancel', $order))
        ->assertRedirect(route('sales-orders.show', $order));

    $order->refresh();

    expect($order->status->value)->toBe('cancelled')
        ->and(SalesInvoice::count())->toBe(0);

    // A cancelled order can no longer be edited or re-confirmed.
    $this->get(route('sales-orders.edit', $order))->assertRedirect(route('sales-orders.show', $order));
    $this->patch(route('sales-orders.confirm', $order), [
        'payment_method' => 'cash',
        'amount_received' => $order->grand_total,
    ])->assertSessionHas('error');
});

it('edits a draft order and recomputes the totals', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->patch(route('sales-orders.update', $order), salesOrderPayload($unit, [
        'customer_id' => $order->customer_id,
        'customer_name' => $order->customer->name,
        'mobile_no' => $order->customer->mobile,
        'location_id' => $order->location_id,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 100, 'discount' => 50],
        ],
    ]))->assertRedirect(route('sales-orders.show', $order));

    $order->refresh();

    expect((float) $order->subtotal)->toBe(450.0)
        ->and((float) $order->grand_total)->toBe(450.0);
});

it('keeps the legacy order-to-invoice conversion for old draft orders', function () {
    // The manual conversion path (pre-filled from the order) is retained for
    // compatibility with legacy draft orders; new orders are invoiced when
    // their payment is confirmed.
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit, 3, 250.0);

    $this->get(route('sales-invoices.create', ['sales_order' => $order->id]))
        ->assertOk()
        ->assertSee('From Order '.$order->order_no)
        ->assertSee('Converted from Sales Order '.$order->order_no);
});

it('marks the order invoiced when its invoice is posted and confirmed again after a void', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit, 3, 250.0);

    $this->post(route('sales-invoices.store'), [
        'invoice_date' => now()->format('Y-m-d\TH:i'),
        'customer_id' => $order->customer_id,
        'sales_order_id' => $order->id,
        'location_id' => $order->location_id,
        'tax_rate' => 0,
        'paid_amount' => 0,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 3, 'unit_price' => 250, 'discount' => 0],
        ],
    ]);

    $invoice = SalesInvoice::first();

    expect($order->fresh()->status->value)->toBe('invoiced');

    $this->patch(route('sales-invoices.post', $invoice));
    $this->patch(route('sales-invoices.void', $invoice));

    expect($order->fresh()->status->value)->toBe('confirmed');

    // The invoiced order is protected from deletion.
    $this->post(route('sales-invoices.store'), [
        'invoice_date' => now()->format('Y-m-d\TH:i'),
        'customer_id' => $order->customer_id,
        'sales_order_id' => $order->id,
        'location_id' => $order->location_id,
        'items' => [['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 250, 'discount' => 0]],
    ]);

    $this->delete(route('sales-orders.destroy', $order->fresh()))
        ->assertSessionHas('error');
});

it('deletes a draft order', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->delete(route('sales-orders.destroy', $order))
        ->assertRedirect(route('sales-orders.index'));

    expect(SalesOrder::withTrashed()->find($order->id)->trashed())->toBeTrue();
});

it('validates the order payload', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit, ['items' => []]))
        ->assertSessionHasErrors('items');

    $this->post(route('sales-orders.store'), salesOrderPayload($unit, [
        'customer_id' => 999999,
    ]))->assertSessionHasErrors('customer_id');

    expect(SalesOrder::count())->toBe(0);
});

it('scopes the order register to the assigned location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myOrder = salesOrderWithItem($unit, 1, 100.0, ['location_id' => $mine->id]);
    $otherOrder = salesOrderWithItem($unit, 1, 100.0, ['location_id' => $other->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo('sales-orders.manage');

    $this->actingAs($manager)
        ->get(route('sales-orders.index'))
        ->assertOk()
        ->assertSee($myOrder->order_no)
        ->assertDontSee($otherOrder->order_no);

    $this->actingAs($manager)
        ->get(route('sales-orders.show', $otherOrder))
        ->assertForbidden();
});

it('searches units by product name for the order item picker', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create([
        'quantity' => 50,
        'product_id' => Product::factory()->create([
            'name' => 'Samsung Nebula X1 Cover',
            'storage' => null,
            'color' => null,
        ])->id,
    ]);
    Unit::factory()->create(['quantity' => 10]);

    $this->get(route('sales-orders.search-units', ['search' => 'Nebula X1']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $unit->id)
        ->assertJsonPath('units.0.display_name', 'Samsung Nebula X1 Cover');
});

it('searches units by barcode for the order item picker', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create([
        'quantity' => 25,
        'product_id' => Product::factory()->create([
            'name' => 'Aurora Bluetooth Speaker',
            'storage' => null,
            'color' => null,
            'barcode' => '889912345678',
        ])->id,
    ]);
    Unit::factory()->create(['quantity' => 10]);

    $this->get(route('sales-orders.search-units', ['search' => '889912345678']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $unit->id)
        ->assertJsonPath('units.0.barcode', '889912345678');
});

it('searches units by product code and model for the order item picker', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create([
        'quantity' => 25,
        'product_id' => Product::factory()->create([
            'name' => 'Orion Smart Watch',
            'storage' => null,
            'color' => null,
            'product_id' => 'PRD-7788',
            'model' => 'OSW-2000',
        ])->id,
    ]);

    $this->get(route('sales-orders.search-units', ['search' => 'PRD-7788']))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $unit->id);

    $this->get(route('sales-orders.search-units', ['search' => 'OSW-2000']))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $unit->id);
});

it('lists products in the picker when no search term is given', function () {
    actingAsSuperAdmin();

    Unit::factory()->count(3)->create(['quantity' => 10]);
    Unit::factory()->create(['quantity' => 0]);

    $this->get(route('sales-orders.search-units'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(4, 'units');
});

it('restricts the order item picker to products stocked at the selected branch', function () {
    actingAsSuperAdmin();

    $branchA = Location::factory()->brandShop()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->brandShop()->create(['name' => 'Branch B']);

    $stockedHere = Unit::factory()->create([
        'quantity' => 50,
        'product_id' => Product::factory()->create(['name' => 'Zephyr Headphones', 'storage' => null, 'color' => null])->id,
    ]);
    $stockedElsewhere = Unit::factory()->create([
        'quantity' => 50,
        'product_id' => Product::factory()->create(['name' => 'Volt Power Bank', 'storage' => null, 'color' => null])->id,
    ]);

    stockAtLocation($stockedHere, 5, $branchA->id);
    stockAtLocation($stockedElsewhere, 7, $branchB->id);

    $this->get(route('sales-orders.search-units', ['search' => 'Zephyr', 'location_id' => $branchA->id]))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $stockedHere->id)
        ->assertJsonPath('units.0.quantity', 5);

    // A product only present at another branch is not offered at all.
    $this->get(route('sales-orders.search-units', ['search' => 'Volt', 'location_id' => $branchA->id]))
        ->assertOk()
        ->assertJsonCount(0, 'units');

    // A branch that holds no stock at all offers nothing.
    $emptyBranch = Location::factory()->brandShop()->create(['name' => 'Branch C']);

    $this->get(route('sales-orders.search-units', ['location_id' => $emptyBranch->id]))
        ->assertOk()
        ->assertJsonCount(0, 'units');
});

it('filters the sales order register by the outlet location', function () {
    actingAsSuperAdmin();

    $branchA = Location::factory()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->create(['name' => 'Branch B']);
    $unit = Unit::factory()->create(['quantity' => 50]);

    $orderAtA = salesOrderWithItem($unit, 1, 100.0, ['location_id' => $branchA->id]);
    $orderAtB = salesOrderWithItem($unit, 1, 100.0, ['location_id' => $branchB->id]);

    $this->get(route('sales-orders.index', ['location_id' => $branchA->id]))
        ->assertOk()
        ->assertSee($orderAtA->order_no)
        ->assertDontSee($orderAtB->order_no);
});

it('lists the outlet locations in the sales order branch filter', function () {
    actingAsSuperAdmin();

    Location::factory()->create(['name' => 'Bandarban Outlet']);

    $this->get(route('sales-orders.index'))
        ->assertOk()
        ->assertSee('name="location_id"', false)
        ->assertDontSee('name="branch_id"', false)
        ->assertSee('>Bandarban Outlet</option>', false);
});

it('offers unassigned opening stock in the warehouse picker', function () {
    actingAsSuperAdmin();

    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $outlet = Location::factory()->brandShop()->create(['name' => 'Outlet']);

    // Opening stock is posted to the central pool (no location), which a
    // warehouse holds; the outlet-only unit has no unassigned stock at all.
    $openingOnly = Unit::factory()->create([
        'quantity' => 4,
        'product_id' => Product::factory()->create(['name' => 'Opening Stock Item', 'storage' => null, 'color' => null])->id,
    ]);
    $outletOnly = Unit::factory()->create([
        'quantity' => 0,
        'product_id' => Product::factory()->create(['name' => 'Outlet Only Item', 'storage' => null, 'color' => null])->id,
    ]);

    StockMovement::factory()->create(['unit_id' => $outletOnly->id, 'location_id' => $outlet->id, 'quantity' => 3]);

    $this->get(route('sales-orders.search-units', ['location_id' => $warehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $openingOnly->id)
        ->assertJsonPath('units.0.quantity', 4);
});

it('returns the stock of the requested units at the selected location', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->brandShop()->create(['name' => 'Branch X']);
    $other = Location::factory()->brandShop()->create(['name' => 'Branch Y']);
    $stocked = Unit::factory()->create(['quantity' => 9]);
    $elsewhere = Unit::factory()->create(['quantity' => 9]);

    stockAtLocation($stocked, 6, $branch->id);
    stockAtLocation($elsewhere, 9, $other->id);

    $this->get(route('sales-orders.search-units', [
        'location_id' => $branch->id,
        'unit_ids' => [$stocked->id, $elsewhere->id],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'units')
        ->assertJsonPath('units.0.id', (int) $stocked->id)
        ->assertJsonPath('units.0.quantity', 6);
});

it('renders the product search field on the create and edit forms', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $this->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('Search product or scan barcode')
        ->assertSee('searchUnits', false);

    $order = salesOrderWithItem($unit);

    $this->get(route('sales-orders.edit', $order))
        ->assertOk()
        ->assertSee('Search product or scan barcode')
        ->assertSee('searchUnits', false);
});
