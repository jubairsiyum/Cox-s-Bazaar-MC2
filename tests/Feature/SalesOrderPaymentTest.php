<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A captured (payment-pending) order with one line item.
 */
function unpaidOrderWithItem(Unit $unit, int $quantity = 3, float $rate = 250.0): SalesOrder
{
    $order = SalesOrder::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
    ]);

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
 * A full-payment confirmation payload for the given order.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fullPaymentPayload(SalesOrder $order, array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cash',
        'amount_received' => (float) $order->grand_total,
    ], $overrides);
}

it('generates a fully paid invoice and posts the stock-out once the payment is confirmed', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = unpaidOrderWithItem($unit);

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order, ['payment_method' => 'bank']))
        ->assertRedirect(route('sales-orders.show', $order));

    $invoice = SalesInvoice::first();

    // The invoice exists only now, and it is settled in full — it must never
    // read "Payment Due" from this flow.
    expect($invoice)->not->toBeNull()
        ->and($invoice->sales_order_id)->toBe($order->id)
        ->and($invoice->invoice_no)->toStartWith('INV')
        ->and($invoice->status->value)->toBe('posted')
        ->and($invoice->posted_at)->not->toBeNull()
        ->and((float) $invoice->grand_total)->toBe(750.0)
        ->and((float) $invoice->paid_amount)->toBe(750.0)
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->payment_status->value)->toBe('paid');

    $order->refresh();

    expect($order->status->value)->toBe('invoiced')
        ->and($order->payment_method)->toBe('bank')
        ->and($order->paid_at)->not->toBeNull();

    // Exactly one coherent stock effect: the single posted stock-out.
    expect($unit->fresh()->quantity)->toBe(47);

    $this->assertDatabaseHas('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'sale',
        'quantity' => -3,
    ]);

    $this->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertSee('Generated Invoice')
        ->assertSee($invoice->invoice_no)
        ->assertSee('Paid At');

    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee($invoice->invoice_no);
});

it('rejects a partial payment and generates no invoice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = unpaidOrderWithItem($unit);

    $this->from(route('sales-orders.show', $order))
        ->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order, ['amount_received' => 300]))
        ->assertSessionHasErrors('amount_received');

    expect(SalesInvoice::count())->toBe(0)
        ->and($order->fresh()->status->value)->toBe('draft')
        ->and($order->fresh()->payment_method)->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('rejects overpayment and a missing or invalid payment method', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = unpaidOrderWithItem($unit);

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order, ['amount_received' => 900]))
        ->assertSessionHasErrors('amount_received');

    $this->patch(route('sales-orders.confirm', $order), ['amount_received' => $order->grand_total])
        ->assertSessionHasErrors('payment_method');

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order, ['payment_method' => 'barter']))
        ->assertSessionHasErrors('payment_method');

    expect(SalesInvoice::count())->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('cannot confirm the payment of an order twice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = unpaidOrderWithItem($unit);

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order))
        ->assertRedirect(route('sales-orders.show', $order));

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order))
        ->assertSessionHas('error');

    expect(SalesInvoice::count())->toBe(1)
        ->and($unit->fresh()->quantity)->toBe(47);
});

it('rolls the whole sale back when the stock is insufficient at payment confirmation', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);
    $order = unpaidOrderWithItem($unit, 5);

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order))
        ->assertSessionHas('error');

    expect(SalesInvoice::count())->toBe(0)
        ->and($order->fresh()->status->value)->toBe('draft')
        ->and($unit->fresh()->quantity)->toBe(1);
});

it('generates a fresh paid invoice when a voided one is re-confirmed', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = unpaidOrderWithItem($unit);

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order));

    $invoice = SalesInvoice::first();

    expect($unit->fresh()->quantity)->toBe(47);

    // Voiding the invoice restores the stock and frees the order for a new
    // payment confirmation.
    $this->patch(route('sales-invoices.void', $invoice), ['void_reason' => 'Entered by mistake']);

    expect($unit->fresh()->quantity)->toBe(50)
        ->and($order->fresh()->status->value)->toBe('confirmed');

    $this->patch(route('sales-orders.confirm', $order), fullPaymentPayload($order, ['payment_method' => 'bkash']))
        ->assertRedirect(route('sales-orders.show', $order));

    $fresh = SalesInvoice::query()->orderByDesc('id')->first();

    expect(SalesInvoice::count())->toBe(2)
        ->and($fresh->status->value)->toBe('posted')
        ->and((float) $fresh->due_amount)->toBe(0.0)
        ->and($fresh->payment_status->value)->toBe('paid')
        ->and($order->fresh()->payment_method)->toBe('bkash')
        ->and($unit->fresh()->quantity)->toBe(47);
});

it('keeps the POS quick sale as an already paid register sale', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('pos.store'), [
        'location_id' => Location::factory()->create()->id,
        'paid_amount' => 500,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 500, 'discount' => 0],
        ],
    ])->assertRedirect();

    $invoice = SalesInvoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status->value)->toBe('posted')
        ->and($invoice->sales_order_id)->toBeNull()
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($unit->fresh()->quantity)->toBe(49);
});
