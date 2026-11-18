<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\Location;
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
 * A posted invoice with one line item, optionally partially paid.
 */
function collectionParentInvoice(Unit $unit, int $quantity = 5, float $rate = 200.0, float $paid = 0, array $attributes = []): SalesInvoice
{
    $invoice = SalesInvoice::factory()->posted()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
    ], $attributes));

    $invoice->items()->create([
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $rate,
        'discount' => 0,
        'subtotal' => $quantity * $rate,
    ]);

    $invoice->update([
        'subtotal' => $quantity * $rate,
        'grand_total' => $quantity * $rate,
        'paid_amount' => $paid,
    ]);

    return $invoice->fresh();
}

/**
 * A minimal valid collection payload allocating to the given invoice.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function collectionPayload(SalesInvoice $invoice, float $amount, array $overrides = []): array
{
    return array_merge([
        'collection_date' => now()->format('Y-m-d\TH:i'),
        'customer_id' => $invoice->customer_id,
        'amount' => $amount,
        'method' => 'cash',
        'location_id' => $invoice->location_id,
        'allocations' => [
            ['sales_invoice_id' => $invoice->id, 'amount' => $amount],
        ],
    ], $overrides);
}

it('lists collections with a Record Payment button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0, 1000);
    $collection = CustomerCollection::factory()->create([
        'customer_id' => $invoice->customer_id,
        'location_id' => $invoice->location_id,
        'amount' => 1000,
    ]);

    $this->get(route('customer-collections.index'))
        ->assertOk()
        ->assertSee($collection->collection_no)
        ->assertSee('Record Payment');
});

it('lists the customer open invoices for the payment form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0, 400);

    $this->get(route('customer-collections.customer-invoices', ['customer_id' => $invoice->customer_id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('invoices.0.id', $invoice->id)
        ->assertJsonPath('invoices.0.payment_due', 600);
});

it('records a partial payment and updates the invoice payment status', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0);

    $this->post(route('customer-collections.store'), collectionPayload($invoice, 300))
        ->assertRedirect();

    $collection = CustomerCollection::first();

    expect($collection)->not->toBeNull()
        ->and($collection->collection_no)->toStartWith('COL')
        ->and((float) $collection->amount)->toBe(300.0)
        ->and($collection->allocations)->toHaveCount(1);

    $invoice = $invoice->fresh();

    expect((float) $invoice->paid_amount)->toBe(300.0)
        ->and((float) $invoice->due_amount)->toBe(700.0)
        ->and($invoice->payment_status->value)->toBe('partial');
});

it('settles an invoice fully with one allocation', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0, 200);

    $this->post(route('customer-collections.store'), collectionPayload($invoice, 800));

    $invoice = $invoice->fresh();

    expect((float) $invoice->paid_amount)->toBe(1000.0)
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->payment_status->value)->toBe('paid');
});

it('allocates one payment across several invoices', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create();
    $first = collectionParentInvoice($unit, 5, 200.0, 0, ['customer_id' => $customer->id]);
    $second = collectionParentInvoice($unit, 5, 200.0, 0, ['customer_id' => $customer->id]);

    $this->post(route('customer-collections.store'), collectionPayload($first, 700, [
        'customer_id' => $customer->id,
        'allocations' => [
            ['sales_invoice_id' => $first->id, 'amount' => 400],
            ['sales_invoice_id' => $second->id, 'amount' => 300],
        ],
    ]))->assertRedirect();

    expect((float) $first->fresh()->due_amount)->toBe(600.0)
        ->and((float) $second->fresh()->due_amount)->toBe(700.0)
        ->and((float) $first->fresh()->paid_amount)->toBe(400.0);
});

it('validates the allocation rules', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0);
    $stranger = collectionParentInvoice($unit, 5, 200.0);

    // Amount must equal the allocation sum.
    $this->post(route('customer-collections.store'), collectionPayload($invoice, 300, ['amount' => 500]))
        ->assertSessionHasErrors('amount');

    // Allocation cannot exceed the invoice due.
    $this->post(route('customer-collections.store'), collectionPayload($invoice, 2000))
        ->assertSessionHasErrors('allocations.0.amount');

    // Invoice must belong to the customer.
    $this->post(route('customer-collections.store'), collectionPayload($stranger, 100, [
        'customer_id' => $invoice->customer_id,
    ]))->assertSessionHasErrors('allocations.0.sales_invoice_id');

    expect(CustomerCollection::count())->toBe(0)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(0.0);
});

it('blocks allocations against draft or voided invoices', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0);
    $invoice->update(['status' => 'draft']);

    $this->post(route('customer-collections.store'), collectionPayload($invoice, 100))
        ->assertSessionHasErrors('allocations.0.sales_invoice_id');

    expect(CustomerCollection::count())->toBe(0);
});

it('shows the collection detail and printable receipt', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0);

    $this->post(route('customer-collections.store'), collectionPayload($invoice, 500));

    $collection = CustomerCollection::first();

    $this->get(route('customer-collections.show', $collection))
        ->assertOk()
        ->assertSee($collection->collection_no)
        ->assertSee('Invoice Allocations');

    $this->get(route('customer-collections.receipt', $collection))
        ->assertOk()
        ->assertSee('Payment Receipt')
        ->assertSee($collection->collection_no);
});

it('reverses the invoice payments when a collection is deleted', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = collectionParentInvoice($unit, 5, 200.0);

    $this->post(route('customer-collections.store'), collectionPayload($invoice, 400));

    $collection = CustomerCollection::first();

    $this->delete(route('customer-collections.destroy', $collection))
        ->assertRedirect(route('customer-collections.index'));

    $invoice = $invoice->fresh();

    expect((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) $invoice->due_amount)->toBe(1000.0)
        ->and($invoice->payment_status->value)->toBe('due');

    expect(CustomerCollection::withTrashed()->find($collection->id)->trashed())->toBeTrue();
});

it('scopes the collections register to the assigned location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $myInvoice = collectionParentInvoice($unit, 5, 200.0, 0, ['location_id' => $mine->id]);
    $otherInvoice = collectionParentInvoice($unit, 5, 200.0, 0, ['location_id' => $other->id]);

    $myCollection = CustomerCollection::factory()->create([
        'customer_id' => $myInvoice->customer_id,
        'location_id' => $mine->id,
        'amount' => 100,
    ]);
    $otherCollection = CustomerCollection::factory()->create([
        'customer_id' => $otherInvoice->customer_id,
        'location_id' => $other->id,
        'amount' => 100,
    ]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo('customer-collections.manage');

    $this->actingAs($manager)
        ->get(route('customer-collections.index'))
        ->assertOk()
        ->assertSee($myCollection->collection_no)
        ->assertDontSee($otherCollection->collection_no);

    $this->actingAs($manager)
        ->get(route('customer-collections.show', $otherCollection))
        ->assertForbidden();
});
