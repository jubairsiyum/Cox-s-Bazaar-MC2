<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\Unit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A minimal order payload carrying free-text customer fields (name + phone),
 * as the customer picker submits them for a typed-in new customer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerEntryPayload(Unit $unit, array $overrides = []): array
{
    return array_merge([
        'order_date' => now()->format('Y-m-d\TH:i'),
        'expected_date' => now()->addDays(3)->toDateString(),
        'customer_name' => 'New Walk-In Shop',
        'customer_phone' => '01711000555',
        'location_id' => Location::factory()->create()->id,
        'tax_rate' => 0,
        'note' => null,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
        ],
    ], $overrides);
}

it('creates a new customer from a typed name and phone', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit))
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'New Walk-In Shop')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->tel_no)->toBe('01711000555');

    $order = SalesOrder::first();

    expect($order->customer_id)->toBe($customer->id);
});

it('resolves a typed name to the existing customer instead of duplicating it', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['name' => 'Rahim Store', 'tel_no' => '01711000111']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Rahim Store',
        'customer_phone' => '01711000111',
    ]))->assertRedirect();

    expect(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('saves an edited phone back to the selected customer profile', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['tel_no' => '01711000123']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => '01711000987',
    ]))->assertRedirect();

    $customer->refresh();

    expect($customer->tel_no)->toBe('01711000987')
        ->and(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('requires a phone number with the customer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, ['customer_phone' => null]))
        ->assertSessionHasErrors('customer_phone');

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, ['customer_name' => null]))
        ->assertSessionHasErrors('customer_name');

    expect(SalesOrder::count())->toBe(0)
        ->and(Customer::count())->toBe(0);
});

it('rejects a phone that already belongs to another customer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    Customer::factory()->create(['tel_no' => '01711000321']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Some Other Shop',
        'customer_phone' => '01711000321',
    ]))->assertSessionHasErrors('customer_phone');

    expect(SalesOrder::count())->toBe(0);
});

it('restores a soft-deleted customer instead of leaving a duplicate', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['name' => 'Deleted Corner Shop']);
    $customer->delete();

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Deleted Corner Shop',
        'customer_phone' => '01711000654',
    ]))->assertRedirect();

    expect(Customer::withTrashed()->count())->toBe(1);

    $customer->refresh();

    expect($customer->trashed())->toBeFalse()
        ->and($customer->tel_no)->toBe('01711000654')
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('syncs the customer name and phone when the order is edited', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = SalesOrder::factory()->create([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
    ]);
    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'subtotal' => 100,
    ]);

    $this->patch(route('sales-orders.update', $order), customerEntryPayload($unit, [
        'location_id' => $order->location_id,
    ]))->assertRedirect(route('sales-orders.show', $order));

    $customer = Customer::query()->where('name', 'New Walk-In Shop')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->tel_no)->toBe('01711000555')
        ->and($order->fresh()->customer_id)->toBe($customer->id);
});

it('renders the customer picker with a required phone field on both forms', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['tel_no' => '01711000999']);

    $this->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('customer_select')
        ->assertSee('customer_phone')
        ->assertSee('data-phone="01711000999"', false)
        ->assertSee('cdn.jsdelivr.net/npm/select2', false)
        ->assertSee('search-customers', false);

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = SalesOrder::factory()->create([
        'customer_id' => $customer->id,
        'location_id' => Location::factory()->create()->id,
    ]);
    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'subtotal' => 100,
    ]);

    $this->get(route('sales-orders.edit', $order))
        ->assertOk()
        ->assertSee('customer_select')
        ->assertSee('value="01711000999"', false)
        ->assertSee('cdn.jsdelivr.net/npm/select2', false);
});

it('looks up customers with their phone numbers for the picker', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['name' => 'Karim Telecom', 'tel_no' => '01711000147']);

    $this->get(route('sales-orders.search-customers', ['q' => 'Karim Telecom']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'customers')
        ->assertJsonPath('customers.0.id', (int) $customer->id)
        ->assertJsonPath('customers.0.name', 'Karim Telecom')
        ->assertJsonPath('customers.0.tel_no', '01711000147');
});
