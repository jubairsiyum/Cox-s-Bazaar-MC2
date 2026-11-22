<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Location;
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
 * A minimal valid invoice payload for the given stock row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salesInvoicePayload(Unit $unit, array $overrides = []): array
{
    return array_merge([
        'invoice_date' => now()->format('Y-m-d\TH:i'),
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
        'tax_rate' => 0,
        'paid_amount' => 0,
        'note' => null,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 0],
        ],
    ], $overrides);
}

/**
 * A draft invoice with one line item, persisted through the service stack.
 */
function salesDraftInvoice(Unit $unit, int $quantity = 2, float $rate = 500.0, array $attributes = []): SalesInvoice
{
    $locationId = $attributes['location_id'] ?? Location::factory()->create()->id;

    stockAtLocation($unit, $quantity, $locationId);

    $invoice = SalesInvoice::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => $locationId,
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
    ]);

    return $invoice->fresh();
}

it('lists only order-generated invoices on the register and hides the add button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $fromOrder = salesDraftInvoice($unit, 1, 500.0, ['sales_order_id' => SalesOrder::factory()->create()->id]);
    $direct = salesDraftInvoice($unit, 1, 500.0);

    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee($fromOrder->invoice_no)
        ->assertDontSee($direct->invoice_no)
        ->assertDontSee('Create Invoice')
        ->assertSee('Edit')
        ->assertSee('Delete');
});

it('creates a draft invoice with items and computes totals', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-invoices.store'), salesInvoicePayload($unit, [
        'tax_rate' => 5,
        'paid_amount' => 200,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 50],
        ],
    ]))->assertRedirect();

    $invoice = SalesInvoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->invoice_no)->toStartWith('INV')
        ->and($invoice->status->value)->toBe('draft')
        ->and((float) $invoice->subtotal)->toBe(950.0)
        ->and((float) $invoice->discount_amount)->toBe(50.0)
        ->and((float) $invoice->tax_amount)->toBe(47.5)
        ->and((float) $invoice->grand_total)->toBe(997.5)
        ->and((float) $invoice->paid_amount)->toBe(200.0)
        ->and((float) $invoice->due_amount)->toBe(797.5)
        ->and($invoice->payment_status->value)->toBe('partial')
        ->and($invoice->items)->toHaveCount(1);

    // A draft invoice never touches stock.
    expect($unit->fresh()->quantity)->toBe(50);
});

it('requires at least one valid item', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);

    $this->post(route('sales-invoices.store'), salesInvoicePayload($unit, ['items' => []]))
        ->assertSessionHasErrors('items');

    $this->post(route('sales-invoices.store'), salesInvoicePayload($unit, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 0, 'unit_price' => 100]],
    ]))->assertSessionHasErrors('items.0.quantity');

    expect(SalesInvoice::count())->toBe(0);
});

it('edits a draft invoice and recomputes the totals', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit);
    $customer = Customer::factory()->create();

    $this->patch(route('sales-invoices.update', $invoice), salesInvoicePayload($unit, [
        'customer_id' => $customer->id,
        'location_id' => $invoice->location_id,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 4, 'unit_price' => 250, 'discount' => 100],
        ],
    ]))->assertRedirect(route('sales-invoices.show', $invoice));

    $invoice->refresh();

    expect($invoice->customer_id)->toBe($customer->id)
        ->and((float) $invoice->subtotal)->toBe(900.0)
        ->and((float) $invoice->grand_total)->toBe(900.0);
});

it('posts a draft invoice and issues the stock-out', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit, 3);

    $this->patch(route('sales-invoices.post', $invoice))
        ->assertRedirect(route('sales-invoices.show', $invoice));

    $invoice->refresh();

    expect($invoice->status->value)->toBe('posted')
        ->and($invoice->posted_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(47);

    expect(AuditLog::where('event', 'posted')->where('auditable_id', $invoice->id)->exists())->toBeTrue();
});

it('blocks posting when stock is insufficient', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 1]);
    $invoice = salesDraftInvoice($unit, 5);

    $this->from(route('sales-invoices.show', $invoice))
        ->patch(route('sales-invoices.post', $invoice))
        ->assertSessionHas('error');

    expect($invoice->fresh()->status->value)->toBe('draft')
        ->and($unit->fresh()->quantity)->toBe(1);
});

it('voids a posted invoice and restores the stock', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit, 3);
    $invoice->update(['paid_amount' => 0]);
    $this->patch(route('sales-invoices.post', $invoice));

    $this->patch(route('sales-invoices.void', $invoice), ['void_reason' => 'Entered by mistake'])
        ->assertRedirect(route('sales-invoices.show', $invoice));

    $invoice->refresh();

    expect($invoice->status->value)->toBe('voided')
        ->and($invoice->voided_at)->not->toBeNull()
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->payment_status->value)->toBe('cancelled')
        ->and($unit->fresh()->quantity)->toBe(50);

    expect(AuditLog::where('event', 'voided')->where('auditable_id', $invoice->id)->exists())->toBeTrue();
});

it('locks posted invoices from editing and deletion', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit);
    $this->patch(route('sales-invoices.post', $invoice));

    $this->get(route('sales-invoices.edit', $invoice))->assertForbidden();
    $this->patch(route('sales-invoices.update', $invoice), salesInvoicePayload($unit))->assertForbidden();
    $this->delete(route('sales-invoices.destroy', $invoice))->assertForbidden();
});

it('lets a super admin open the edit form of a draft invoice', function () {
    // Regression: the Super Admin's Gate bypass makes @can('update') true for
    // every invoice, which used to surface an Edit button on locked invoices
    // that then 403'd. Draft invoices must edit fine and locked ones must not
    // offer the action at all.
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit);

    $this->get(route('sales-invoices.edit', $invoice))
        ->assertOk()
        ->assertSee('Edit Sales Invoice')
        ->assertSee($invoice->invoice_no);
});

it('hides the edit and delete actions for invoices that are no longer editable', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $draft = salesDraftInvoice($unit, 1, 100.0, ['sales_order_id' => SalesOrder::factory()->create()->id]);
    $posted = salesDraftInvoice($unit, 1, 100.0, ['sales_order_id' => SalesOrder::factory()->create()->id, 'status' => 'posted']);

    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee(route('sales-invoices.edit', $draft), false)
        ->assertDontSee(route('sales-invoices.edit', $posted), false);

    $this->get(route('sales-invoices.show', $posted))
        ->assertOk()
        ->assertDontSee(route('sales-invoices.edit', $posted), false);

    $this->get(route('sales-invoices.show', $draft))
        ->assertOk()
        ->assertSee(route('sales-invoices.edit', $draft), false);
});

it('deletes a draft invoice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit);

    $this->delete(route('sales-invoices.destroy', $invoice))
        ->assertRedirect(route('sales-invoices.index'));

    expect(SalesInvoice::withTrashed()->find($invoice->id)->trashed())->toBeTrue();
});

it('shows the invoice detail and printable pages with payment info', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesDraftInvoice($unit, 2, 500.0, ['paid_amount' => 400]);

    $this->get(route('sales-invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->invoice_no)
        ->assertDontSee('Record Payment')
        ->assertSee('Due', false)
        ->assertSee('Post Invoice');

    $this->get(route('sales-invoices.print', $invoice))
        ->assertOk()
        ->assertSee('Sales Invoice')
        ->assertSee($invoice->invoice_no);
});

it('scopes the register to the assigned location for outlet managers', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myInvoice = salesDraftInvoice($unit, 1, 100.0, ['location_id' => $mine->id, 'sales_order_id' => SalesOrder::factory()->create()->id]);
    $otherInvoice = salesDraftInvoice($unit, 1, 100.0, ['location_id' => $other->id, 'sales_order_id' => SalesOrder::factory()->create()->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo(['sales-invoices.view', 'sales-invoices.manage', 'sales-invoices.post']);

    $this->actingAs($manager)
        ->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee($myInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no);

    $this->actingAs($manager)
        ->get(route('sales-invoices.show', $otherInvoice))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('sales-invoices.post', $otherInvoice))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('sales-invoices.post', $myInvoice))
        ->assertRedirect(route('sales-invoices.show', $myInvoice));
});

it('shows super admins every branch in the overview', function () {
    actingAsSuperAdmin();

    $a = salesDraftInvoice(Unit::factory()->create(['quantity' => 10]), 1, 100.0, ['sales_order_id' => SalesOrder::factory()->create()->id]);
    $b = salesDraftInvoice(Unit::factory()->create(['quantity' => 10]), 1, 100.0, ['sales_order_id' => SalesOrder::factory()->create()->id]);

    $this->get(route('sales-invoices.index', ['branch_id' => 999]))
        ->assertOk()
        ->assertDontSee($a->invoice_no);

    $this->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertSee($a->invoice_no)
        ->assertSee($b->invoice_no);
});

it('exports the filtered sales invoice register to excel and csv', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $posted = salesDraftInvoice($unit, 1, 100.0, ['status' => 'posted']);
    $draft = salesDraftInvoice($unit, 1, 200.0);

    $csv = $this->get(route('sales-invoices.export', ['format' => 'csv', 'status' => 'posted']));

    $csv->assertOk()->assertHeader('content-disposition');

    $content = file_get_contents($csv->baseResponse->getFile()->getPathname());

    expect($content)
        ->toContain($posted->invoice_no)
        ->not->toContain($draft->invoice_no);

    $this->get(route('sales-invoices.export', ['format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('scopes the sales invoice export to the assigned location for outlet managers', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myInvoice = salesDraftInvoice($unit, 1, 100.0, ['location_id' => $mine->id]);
    $otherInvoice = salesDraftInvoice($unit, 1, 100.0, ['location_id' => $other->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo(['sales-invoices.view', 'sales-invoices.manage', 'sales-invoices.post']);

    $response = $this->actingAs($manager)
        ->get(route('sales-invoices.export', ['format' => 'csv']));

    $response->assertOk()->assertHeader('content-disposition');

    $content = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($content)
        ->toContain($myInvoice->invoice_no)
        ->not->toContain($otherInvoice->invoice_no);
});

it('blocks sales invoice exports without the register permissions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('sales-invoices.export', ['format' => 'csv']))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('sales-invoices.export', ['format' => 'xlsx']))
        ->assertForbidden();
});
