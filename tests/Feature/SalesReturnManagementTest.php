<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A posted invoice with one line item to apply returns against.
 */
function salesReturnParentInvoice(Unit $unit, int $quantity = 5, float $rate = 200.0, array $attributes = []): SalesInvoice
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
    ]);

    return $invoice->fresh();
}

/**
 * A minimal valid return payload against the given invoice.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salesReturnPayload(SalesInvoice $invoice, array $overrides = []): array
{
    return array_merge([
        'sales_invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'return_date' => now()->format('Y-m-d\TH:i'),
        'reason' => 'Defective or Damaged',
        'items' => [
            [
                'unit_id' => $invoice->items->first()->unit_id,
                'quantity' => 2,
                'condition' => 'good',
                'restock' => 1,
                'reason' => 'Screen issue',
            ],
        ],
    ], $overrides);
}

it('lists sales returns with the parent invoice and an Apply Return button', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit);
    $return = SalesReturn::factory()->forInvoice($invoice)->create();

    $this->get(route('sales-returns.index'))
        ->assertOk()
        ->assertSee($return->return_no)
        ->assertSee($invoice->invoice_no)
        ->assertSee('Apply Return');
});

it('lists the customer invoices and their details for the return form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['paid_amount' => 400]);

    $this->get(route('sales-returns.customer-invoices', ['customer_id' => $invoice->customer_id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('invoices.0.id', $invoice->id)
        ->assertJsonPath('invoices.0.invoice_no', $invoice->invoice_no);

    $this->get(route('sales-returns.invoice-details', ['sales_invoice_id' => $invoice->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('invoice.invoice_no', $invoice->invoice_no)
        ->assertJsonPath('invoice.paid_amount', 400)
        ->assertJsonPath('invoice.payment_due', 600)
        ->assertJsonPath('items.0.invoiced', 5)
        ->assertJsonPath('items.0.unit_price', 200);
});

it('creates a return awaiting approval without touching stock or the invoice', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['paid_amount' => 1000]);

    $this->post(route('sales-returns.store'), salesReturnPayload($invoice))
        ->assertRedirect(route('sales-returns.index'));

    $return = SalesReturn::first();

    expect($return)->not->toBeNull()
        ->and($return->return_no)->toStartWith('SR')
        ->and($return->status->value)->toBe('pending')
        ->and((float) $return->refund_amount)->toBe(400.0)
        ->and($return->items)->toHaveCount(1)
        ->and($return->items->first()->condition->value)->toBe('good')
        ->and($return->items->first()->restock)->toBeTrue();

    // Nothing takes effect before the responsible manager approves.
    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $invoice->fresh()->credit_amount)->toBe(0.0)
        ->and((float) $invoice->fresh()->due_amount)->toBe(0.0);

    expect(AuditLog::where('event', 'created')->where('auditable_id', $return->id)->exists())->toBeTrue();
});

it('notifies the responsible branch managers when a return awaits approval', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $location->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $salesUser = User::factory()->create(['email_verified_at' => now()]);
    $salesUser->assignRole('Sales Staff');
    $salesUser->givePermissionTo(['sales-returns.manage']);

    $this->actingAs($salesUser)
        ->post(route('sales-returns.store'), salesReturnPayload($invoice))
        ->assertRedirect(route('sales-returns.index'));

    $notifications = $manager->fresh()->notifications;

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['type'])->toBe('warning')
        ->and($notifications->first()->data['title'])->toContain('awaits your approval');
});

it('approves a restocked return: stock comes back and the invoice is credited', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $location->id, 'paid_amount' => 1000]);
    $return = SalesReturn::factory()->forInvoice($invoice)->create();

    $return->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 2,
        'unit_price' => 200,
        'subtotal' => 400,
        'condition' => 'good',
        'restock' => true,
    ]);
    $return->update(['refund_amount' => 400]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->patch(route('sales-returns.approve', $return))
        ->assertRedirect(route('sales-returns.index'));

    $return->refresh();
    $invoice = $invoice->fresh();

    expect($return->status->value)->toBe('completed')
        ->and($return->approved_by)->toBe($manager->id)
        ->and($return->restocked_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(52);

    // Credit note: due is eaten first, over-credit reduces recorded payments.
    expect((float) $invoice->credit_amount)->toBe(400.0)
        ->and((float) $invoice->paid_amount)->toBe(600.0)
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->payment_status->value)->toBe('paid');

    // The creator is told about the decision.
    $creatorNotifications = $return->creator->fresh()->notifications;
    expect($creatorNotifications->last()->data['type'])->toBe('success');
});

it('applies the credit to the due first on partially paid invoices', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['paid_amount' => 400]);
    $return = SalesReturn::factory()->forInvoice($invoice)->create();

    $return->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
        'condition' => 'damaged',
        'restock' => false,
        'reason' => 'Broken screen',
    ]);
    $return->update(['refund_amount' => 200]);

    $this->patch(route('sales-returns.approve', $return));

    $return->refresh();
    $invoice = $invoice->fresh();

    // Without restock the return settles as approved; stock is untouched.
    expect($return->status->value)->toBe('approved')
        ->and($return->restocked_at)->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(50);

    expect((float) $invoice->credit_amount)->toBe(200.0)
        ->and((float) $invoice->paid_amount)->toBe(400.0)
        ->and((float) $invoice->due_amount)->toBe(400.0)
        ->and($invoice->payment_status->value)->toBe('partial');
});

it('rejects a return without any effect and tells the creator', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $location->id]);
    $return = SalesReturn::factory()->forInvoice($invoice)->create();
    $return->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 2,
        'unit_price' => 200,
        'subtotal' => 400,
    ]);
    $return->update(['refund_amount' => 400]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->patch(route('sales-returns.reject', $return))
        ->assertRedirect(route('sales-returns.index'));

    $return->refresh();

    expect($return->status->value)->toBe('rejected')
        ->and($unit->fresh()->quantity)->toBe(50)
        ->and((float) $invoice->fresh()->credit_amount)->toBe(0.0);

    $creatorNotifications = $return->creator->fresh()->notifications;
    expect($creatorNotifications->last()->data['type'])->toBe('error');
});

it('lets the responsible branch manager approve but not another branch manager', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $myInvoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $mine->id]);
    $otherInvoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $other->id]);

    $myReturn = SalesReturn::factory()->forInvoice($myInvoice)->create(['refund_amount' => 200]);
    $otherReturn = SalesReturn::factory()->forInvoice($otherInvoice)->create(['refund_amount' => 200]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');

    // The branch manager can open the return from the notification link and
    // act on it without any extra sales permission (policy-based gate).
    $this->actingAs($manager)
        ->get(route('sales-returns.show', $myReturn))
        ->assertOk()
        ->assertSee('Awaiting Your Decision')
        ->assertSee('Accept Return');

    $this->actingAs($manager)
        ->patch(route('sales-returns.approve', $otherReturn))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('sales-returns.approve', $myReturn))
        ->assertRedirect(route('sales-returns.index'));

    expect($myReturn->fresh()->status->value)->toBe('approved')
        ->and($otherReturn->fresh()->status->value)->toBe('pending');
});

it('scopes the return register to the assigned branch/location', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $myInvoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $mine->id]);
    $otherInvoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $other->id]);

    $myReturn = SalesReturn::factory()->forInvoice($myInvoice)->create();
    $otherReturn = SalesReturn::factory()->forInvoice($otherInvoice)->create();

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo('sales-returns.manage');

    $this->actingAs($manager)
        ->get(route('sales-returns.index'))
        ->assertOk()
        ->assertSee($myReturn->return_no)
        ->assertDontSee($otherReturn->return_no);
});

it('validates the return quantities against the invoiced ones', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 2, 200.0);

    $this->post(route('sales-returns.store'), salesReturnPayload($invoice, [
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'condition' => 'good', 'restock' => 1],
        ],
    ]))->assertSessionHasErrors('items.0.quantity');

    $stranger = Unit::factory()->create(['quantity' => 50]);
    $this->post(route('sales-returns.store'), salesReturnPayload($invoice, [
        'items' => [
            ['unit_id' => $stranger->id, 'quantity' => 1, 'condition' => 'good', 'restock' => 1],
        ],
    ]))->assertSessionHasErrors('items.0.unit_id');

    $this->post(route('sales-returns.store'), salesReturnPayload($invoice, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 0, 'condition' => 'good', 'restock' => 1]],
    ]))->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(0);
});

it('requeues a rejected return when it is edited', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0);
    $return = SalesReturn::factory()->forInvoice($invoice)->rejected()->create();
    $return->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
    ]);
    $return->update(['refund_amount' => 200]);

    $this->patch(route('sales-returns.update', $return), salesReturnPayload($invoice, [
        'items' => [['unit_id' => $unit->id, 'quantity' => 2, 'condition' => 'good', 'restock' => 1]],
    ]))->assertRedirect(route('sales-returns.index'));

    $return->refresh();

    expect($return->status->value)->toBe('pending')
        ->and((float) $return->refund_amount)->toBe(400.0)
        ->and($return->items)->toHaveCount(1);
});

it('locks approved returns from editing and deletion', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0);
    $return = SalesReturn::factory()->forInvoice($invoice)->approved()->create();

    $this->get(route('sales-returns.edit', $return))->assertRedirect(route('sales-returns.show', $return));
    $this->patch(route('sales-returns.update', $return), salesReturnPayload($invoice))->assertRedirect(route('sales-returns.show', $return));
    $this->delete(route('sales-returns.destroy', $return))->assertSessionHas('error');

    expect($return->fresh()->status->value)->toBe('approved');
});

it('blocks double approval and shows the dynamic status labels', function () {
    $location = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);
    $invoice = salesReturnParentInvoice($unit, 5, 200.0, ['location_id' => $location->id]);
    $return = SalesReturn::factory()->forInvoice($invoice)->create();
    $return->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
    ]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'location_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    expect($return->displayStatusFor($manager)['label'])->toBe('Awaiting Approval')
        ->and($return->displayStatusFor(null)['label'])->toBe('Pending Review');

    $this->actingAs($manager)->patch(route('sales-returns.approve', $return));
    $this->actingAs($manager)->patch(route('sales-returns.approve', $return))
        ->assertSessionHas('error');

    $return->refresh();
    expect($return->displayStatusFor($manager)['label'])->toBe('Restocked & Completed');
});
