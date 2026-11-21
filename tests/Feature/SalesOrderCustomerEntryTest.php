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
 * as the customer picker submits them for a typed-in new customer. The email
 * is optional and only sent when a test overrides it.
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
        'mobile_no' => '01711000555',
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
        ->and($customer->mobile)->toBe('01711000555')
        ->and($customer->email)->toBeNull();

    $order = SalesOrder::first();

    expect($order->customer_id)->toBe($customer->id);
});

it('resolves a typed name to the existing customer instead of duplicating it', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['name' => 'Rahim Store', 'mobile' => '01711000111']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Rahim Store',
        'mobile_no' => '01711000111',
    ]))->assertRedirect();

    expect(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('saves an edited phone back to the selected customer profile', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['mobile' => '01711000123']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => '01711000987',
    ]))->assertRedirect();

    $customer->refresh();

    expect($customer->mobile)->toBe('01711000987')
        ->and(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('requires a mobile number with the customer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, ['mobile_no' => null]))
        ->assertSessionHasErrors(['mobile_no' => 'The Mobile No field is required.']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, ['customer_name' => null]))
        ->assertSessionHasErrors('customer_name');

    expect(SalesOrder::count())->toBe(0)
        ->and(Customer::count())->toBe(0);
});

it('rejects a phone that already belongs to another customer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    Customer::factory()->create(['mobile' => '01711000321']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Some Other Shop',
        'mobile_no' => '01711000321',
    ]))->assertSessionHasErrors('mobile_no');

    expect(SalesOrder::count())->toBe(0);
});

it('restores a soft-deleted customer instead of leaving a duplicate', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['name' => 'Deleted Corner Shop']);
    $customer->delete();

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Deleted Corner Shop',
        'mobile_no' => '01711000654',
    ]))->assertRedirect();

    expect(Customer::withTrashed()->count())->toBe(1);

    $customer->refresh();

    expect($customer->trashed())->toBeFalse()
        ->and($customer->mobile)->toBe('01711000654')
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
        ->and($customer->mobile)->toBe('01711000555')
        ->and($order->fresh()->customer_id)->toBe($customer->id);
});

it('renders the customer picker with a required phone field on both forms', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['mobile' => '01711000999']);

    $this->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('list="customer_options"', false)
        ->assertSee('mobile_no')
        ->assertSee('"mobile":"01711000999"', false);

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
        ->assertSee('list="customer_options"', false)
        ->assertSee('value="01711000999"', false);
});

it('looks up customers with their phone numbers for the picker', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['name' => 'Karim Telecom', 'mobile' => '01711000147', 'email' => 'karim@example.com']);

    $this->get(route('sales-orders.search-customers', ['q' => 'Karim Telecom']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'customers')
        ->assertJsonPath('customers.0.id', (int) $customer->id)
        ->assertJsonPath('customers.0.name', 'Karim Telecom')
        ->assertJsonPath('customers.0.mobile', '01711000147')
        ->assertJsonPath('customers.0.email', 'karim@example.com');
});

it('saves an optional email to a customer created from a typed name', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_email' => 'Shop@Example.com',
    ]))->assertRedirect();

    $customer = Customer::query()->where('name', 'New Walk-In Shop')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->email)->toBe('shop@example.com');
});

it('saves an edited email back to the selected customer profile', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['mobile' => '01711000123', 'email' => 'old@example.com']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => '01711000123',
        'customer_email' => 'new@example.com',
    ]))->assertRedirect();

    $customer->refresh();

    expect($customer->email)->toBe('new@example.com')
        ->and(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('saves an edited email back to a customer resolved by name', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['name' => 'Rahim Store', 'mobile' => '01711000111', 'email' => 'old@example.com']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Rahim Store',
        'mobile_no' => '01711000111',
        'customer_email' => 'new@example.com',
    ]))->assertRedirect();

    $customer->refresh();

    expect($customer->email)->toBe('new@example.com')
        ->and(Customer::query()->count())->toBe(1)
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('rejects an email that already belongs to another customer', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    Customer::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_name' => 'Some Other Shop',
        'customer_email' => 'Taken@Example.com',
    ]))->assertSessionHasErrors('customer_email');

    expect(SalesOrder::count())->toBe(0);
});

it('allows a customer to keep its own email on resubmission', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['mobile' => '01711000321', 'email' => 'mine@example.com']);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => '01711000321',
        'customer_email' => 'mine@example.com',
    ]))->assertRedirect();

    $customer->refresh();

    expect($customer->email)->toBe('mine@example.com')
        ->and(SalesOrder::count())->toBe(1);
});

it('rejects an email that is not a valid address', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_email' => 'not-an-email',
    ]))->assertSessionHasErrors('customer_email');

    expect(SalesOrder::count())->toBe(0)
        ->and(Customer::count())->toBe(0);
});

it('renders the customer contact autofill data attributes and JSON payload', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['mobile' => '01711000999', 'email' => 'auto@example.com']);

    // The customer picker's script autofills mobile/email from the JSON contact directory.
    $this->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('customer_email')
        ->assertSee('customer-contact-directory', false)
        ->assertSee('"mobile":"01711000999"', false)
        ->assertSee('"email":"auto@example.com"', false);

    // Edit form: the saved customer's contact details populate the fields.
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
        ->assertSee('value="01711000999"', false)
        ->assertSee('value="auto@example.com"', false)
        ->assertSee('"email":"auto@example.com"', false);
});

it('autofills the mobile number for a customer that has no telephone number', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['name' => 'Mobile Only Shop', 'mobile' => '01700111222', 'tel_no' => null]);

    $this->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('"mobile":"01700111222"', false);

    $this->get(route('sales-orders.search-customers', ['q' => 'Mobile Only Shop']))
        ->assertOk()
        ->assertJsonPath('customers.0.id', (int) $customer->id)
        ->assertJsonPath('customers.0.mobile', '01700111222')
        ->assertJsonPath('customers.0.tel_no', null);
});

it('does not copy the mobile number into the telephone number on order save', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['mobile' => '01700333444', 'tel_no' => null]);

    $this->post(route('sales-orders.store'), customerEntryPayload($unit, [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => '01700333444',
    ]))->assertRedirect();

    expect($customer->fresh()->tel_no)->toBeNull()
        ->and(SalesOrder::first()->customer_id)->toBe($customer->id);
});

it('renders the submitted customer contact after a validation error', function () {
    actingAsSuperAdmin();

    $this->withSession(['_old_input' => [
        'customer_name' => 'Typed New Shop',
        'mobile_no' => '01711000555',
        'customer_email' => 'typed@example.com',
    ]])->get(route('sales-orders.create'))
        ->assertOk()
        ->assertSee('value="01711000555"', false)
        ->assertSee('value="typed@example.com"', false);
});
