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
    return array_merge([
        'customer_id' => null,
        'location_id' => Location::factory()->create()->id,
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
