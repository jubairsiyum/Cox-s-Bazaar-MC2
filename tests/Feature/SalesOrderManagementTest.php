<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
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
    $order = SalesOrder::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
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
 * A minimal valid order payload for the given stock row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salesOrderPayload(Unit $unit, array $overrides = []): array
{
    return array_merge([
        'order_date' => now()->format('Y-m-d\TH:i'),
        'expected_date' => now()->addDays(3)->toDateString(),
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
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

it('creates an order and auto-generates its posted invoice with computed totals', function () {
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
        ->and($order->status->value)->toBe('invoiced')
        ->and((float) $order->subtotal)->toBe(900.0)
        ->and((float) $order->tax_amount)->toBe(90.0)
        ->and((float) $order->grand_total)->toBe(990.0)
        ->and($order->items)->toHaveCount(1);

    // The invoice is generated and posted on the spot: a completed sale moves
    // stock and produces its invoice, like a POS quick sale.
    $invoice = SalesInvoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->sales_order_id)->toBe($order->id)
        ->and($invoice->invoice_no)->toStartWith('INV')
        ->and($invoice->status->value)->toBe('posted')
        ->and($invoice->posted_at)->not->toBeNull()
        ->and((float) $invoice->grand_total)->toBe(990.0)
        ->and((float) $invoice->due_amount)->toBe(990.0)
        ->and($invoice->items)->toHaveCount(1);

    expect($unit->fresh()->quantity)->toBe(48);

    // The generated invoice shows up in the Sales Invoice register.
    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee($invoice->invoice_no);
});

it('shows the generated invoice on the order instead of a Create Invoice button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit));

    $order = SalesOrder::first();
    $invoice = SalesInvoice::first();

    $this->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertSee('Generated Invoice')
        ->assertSee($invoice->invoice_no)
        ->assertSee('Posted')
        ->assertDontSee('Create Invoice');

    $this->get(route('sales-orders.index'))
        ->assertOk()
        ->assertDontSee('Create Invoice');
});

it('voids the generated invoice and restores stock when the order is cancelled', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit));

    $order = SalesOrder::first();
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
    $this->patch(route('sales-orders.confirm', $order))->assertSessionHas('error');
});

it('persists neither order nor invoice when the stock is insufficient', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);

    $this->post(route('sales-orders.store'), salesOrderPayload($unit, [
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 250, 'discount' => 0],
        ],
    ]))->assertSessionHas('error');

    expect(SalesOrder::count())->toBe(0)
        ->and(SalesInvoice::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(1);
});

it('confirms and cancels a legacy draft order without an invoice', function () {
    // Draft orders only exist as legacy data: new orders are invoiced the
    // moment they are saved. Their confirm/cancel lifecycle is kept here.
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->patch(route('sales-orders.confirm', $order))
        ->assertRedirect(route('sales-orders.show', $order));

    expect($order->fresh()->status->value)->toBe('confirmed');

    $this->patch(route('sales-orders.cancel', $order))
        ->assertRedirect(route('sales-orders.show', $order));

    $order->refresh();

    expect($order->status->value)->toBe('cancelled');

    // A cancelled order can no longer be edited or re-confirmed.
    $this->get(route('sales-orders.edit', $order))->assertRedirect(route('sales-orders.show', $order));
    $this->patch(route('sales-orders.confirm', $order))->assertSessionHas('error');
});

it('edits a draft order and recomputes the totals', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit);

    $this->patch(route('sales-orders.update', $order), salesOrderPayload($unit, [
        'customer_id' => $order->customer_id,
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
    // compatibility with legacy draft orders; new orders are auto-invoiced.
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit, 3, 250.0);
    $this->patch(route('sales-orders.confirm', $order));

    $this->get(route('sales-invoices.create', ['sales_order' => $order->id]))
        ->assertOk()
        ->assertSee('From Order '.$order->order_no)
        ->assertSee('Converted from Sales Order '.$order->order_no);
});

it('marks the order invoiced when its invoice is posted and confirmed again after a void', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = salesOrderWithItem($unit, 3, 250.0);
    $this->patch(route('sales-orders.confirm', $order));

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

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $mine->id]);
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
