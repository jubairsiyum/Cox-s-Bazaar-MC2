<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Models\Account;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\Unit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A minimal "placed with the supplier" purchase order payload (grand total
 * ৳1,050 = 5 × ৳200 + 5% tax), referencing the given supplier and location.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function workflowOrderPayload(Unit $unit, Supplier $supplier, Location $location, array $overrides = []): array
{
    return array_merge([
        'order_date' => now()->format('Y-m-d\TH:i'),
        'reference_no' => null,
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'purchase_status' => 'ordered',
        'tax_rate' => 5,
        'paid_amount' => 50,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ], $overrides);
}

/**
 * Edit-form payload for an existing order. The form submits the whole
 * order, so the current paid amount is carried over unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function workflowEditPayload(PurchaseOrder $order, Unit $unit, array $overrides = []): array
{
    return array_merge([
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'ordered',
        'tax_rate' => 5,
        'paid_amount' => (float) $order->fresh()->paid_amount,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ], $overrides);
}

/**
 * Supplier + location pair for the workflow tests.
 *
 * @return array{0: Supplier, 1: Location}
 */
function workflowSupplierAndLocation(): array
{
    return [
        Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]),
        Location::factory()->create(),
    ];
}

it('displays a placed order as Pending until the supplier accepts, then Ordered dynamically', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    // "Ordered" places the order: stored as ordered, awaiting the supplier.
    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->managerDisplayStatus()[1])->toBe('Pending');

    foreach ([route('purchase-invoices.index'), route('purchase-invoices.show', $order)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('>Pending</span>', false)
            ->assertDontSee('>Ordered</span>', false);
    }

    // The supplier accepts on their profile: the branch side flips to Ordered.
    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertRedirect();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->accepted_at)->not->toBeNull()
        ->and($order->managerDisplayStatus()[1])->toBe('Ordered');

    $this->get(route('purchase-invoices.show', $order))
        ->assertOk()
        ->assertSee('>Ordered</span>', false)
        ->assertDontSee('>Pending</span>', false);
});

it('creates a direct receipt that reads Received on the branch side and stays Accepted on the supplier side until fully paid', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location, [
        'purchase_status' => 'received',
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->managerDisplayStatus()[1])->toBe('Received')
        ->and($order->supplierDisplayStatus()[1])->toBe('Accepted')
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('keeps the branch side Ordered when the due is cleared through purchase dues', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    // The manager clears the remaining ৳1,000 from Purchase Dues.
    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
        'method' => 'cash',
    ])->assertRedirect();

    $order->refresh();

    // A payment alone never receives the goods: the branch side stays Ordered.
    expect((float) $order->paid_amount)->toBe(1050.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->received_at)->toBeNull()
        ->and($order->managerDisplayStatus()[1])->toBe('Ordered')
        ->and($order->supplierDisplayStatus()[1])->toBe('Accepted');

    expect($unit->fresh()->quantity)->toBe(50);

    $this->assertDatabaseMissing('stock_movements', [
        'unit_id' => $unit->id,
        'type' => 'restock',
    ]);
});

it('keeps the branch side Ordered when a finance supplier payment settles the order', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location, [
        'paid_amount' => 0,
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $account = Account::factory()->cash()->withBalance(5000)->create();

    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => 1050,
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'method' => 'bank',
    ])->assertRedirect(route('supplier-payments.index'));

    $order->refresh();

    expect((float) $order->paid_amount)->toBe(1050.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($unit->fresh()->quantity)->toBe(50)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(0.0);
});

it('delivers an accepted order when it is marked received after full payment', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    // Full payment before acceptance: the supplier side stays Pending.
    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
    ])->assertRedirect();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->managerDisplayStatus()[1])->toBe('Pending');

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->supplierDisplayStatus()[1])->toBe('Accepted');

    // The branch marks the goods received on the edit form: Delivered.
    $this->patch(route('purchase-invoices.update', $order), workflowEditPayload($order, $unit, [
        'purchase_status' => 'received',
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect((float) $order->paid_amount)->toBe(1050.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Delivered)
        ->and($order->accepted_at)->not->toBeNull()
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->received_at)->not->toBeNull()
        ->and($order->managerDisplayStatus()[1])->toBe('Received')
        ->and($order->supplierDisplayStatus()[1])->toBe('Delivered')
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('delivers an accepted order as soon as the goods are received, even before full payment', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $this->patch(route('purchase-invoices.update', $order), workflowEditPayload($order, $unit, [
        'purchase_status' => 'received',
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->delivered_at)->toBeNull()
        ->and($order->received_at)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Partial)
        ->and((float) $order->payment_due)->toBe(1000.0)
        ->and($order->managerDisplayStatus()[1])->toBe('Received')
        ->and($order->supplierDisplayStatus()[1])->toBe('Accepted')
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('keeps a received order delivered when it is fully paid afterwards', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $this->patch(route('purchase-invoices.update', $order), workflowEditPayload($order, $unit, [
        'purchase_status' => 'received',
    ]))->assertRedirect(route('purchase-invoices.index'));

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
    ])->assertRedirect();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Delivered)
        ->and($order->delivered_at)->not->toBeNull()
        ->and($order->received_at)->not->toBeNull()
        ->and($order->supplierDisplayStatus()[1])->toBe('Delivered')
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('keeps an upfront fully paid order pending until the supplier accepts it after receipt', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    // Full payment upfront: the supplier side still waits for acceptance.
    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location, [
        'paid_amount' => 1050,
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->managerDisplayStatus()[1])->toBe('Pending')
        ->and($unit->fresh()->quantity)->toBe(50);

    // The branch receives the goods before the supplier has accepted.
    $this->patch(route('purchase-invoices.update', $order), workflowEditPayload($order, $unit, [
        'purchase_status' => 'received',
        'paid_amount' => 1050,
    ]))->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->managerDisplayStatus()[1])->toBe('Received')
        ->and($order->supplierDisplayStatus()[1])->toBe('Pending')
        ->and($unit->fresh()->quantity)->toBe(55);

    // Accepting a fully paid, already received order completes its delivery.
    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $order->refresh();

    expect($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Delivered)
        ->and($order->supplierDisplayStatus()[1])->toBe('Delivered')
        ->and($unit->fresh()->quantity)->toBe(55);
});

it('cancellation releases the supplier due and retires open payment requests', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    expect((float) $supplier->fresh()->purchase_due)->toBe(1000.0);

    $request = SupplierPaymentRequest::factory()->forOrder($order)->create();

    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'cancelled',
        'paid_amount' => 50,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    // Cancelled purchases release their dues and carry no payment requests.
    expect((float) $supplier->fresh()->purchase_due)->toBe(0.0)
        ->and($order->fresh()->purchase_status)->toBe(PurchaseStatus::Cancelled)
        ->and($request->fresh()->status)->toBe(SupplierPaymentRequestStatus::Cancelled);
});

it('does not auto-advance an order whose paid amount is edited up to the grand total', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    // Editing the paid amount is an entry value, not a payment application.
    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'ordered',
        'paid_amount' => 1050,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 5, 'unit_price' => 200],
        ],
    ])->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect((float) $order->paid_amount)->toBe(1050.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Pending)
        ->and($order->managerDisplayStatus()[1])->toBe('Pending');
});

it('keeps the order Delivered when a payment is reversed, because payment does not affect delivery', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    [$supplier, $location] = workflowSupplierAndLocation();

    $this->post(route('purchase-orders.store'), workflowOrderPayload($unit, $supplier, $location))
        ->assertRedirect(route('purchase-invoices.index'));

    $order = PurchaseOrder::query()->firstOrFail();

    $this->patch(route('suppliers.profile.accept-order', [$supplier, $order]))->assertRedirect();

    $this->patch(route('purchase-invoices.update', $order), workflowEditPayload($order, $unit, [
        'purchase_status' => 'received',
    ]))->assertRedirect(route('purchase-invoices.index'));

    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
    ])->assertRedirect();

    $order->refresh();

    expect($order->supplier_status)->toBe(SupplierOrderStatus::Delivered);

    $payment = $order->payments()->latest('id')->firstOrFail();

    $this->delete(route('supplier-payments.destroy', $payment))
        ->assertRedirect();

    $order->refresh();

    // The branch side stays received; the supplier side drops back to Accepted
    // because the order is no longer fully paid.
    expect((float) $order->paid_amount)->toBe(50.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Received)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->delivered_at)->toBeNull()
        ->and($order->received_at)->not->toBeNull()
        ->and($unit->fresh()->quantity)->toBe(55);
});
