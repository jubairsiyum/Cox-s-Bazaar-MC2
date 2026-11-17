<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase order record with one line item, ready for the register tests.
 * Stock and supplier due effects are posted as a real approved creation would.
 */
function registerOrder(Unit $unit, string $status = 'ordered', int $quantity = 5, float $unitPrice = 200.0, float $paid = 0.0): PurchaseOrder
{
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'purchase_status' => $status,
        'paid_amount' => $paid,
        'approval_status' => $status === 'received' ? ApprovalStatus::Approved->value : ApprovalStatus::Pending->value,
    ]);

    $order->items()->create([
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $quantity * $unitPrice,
    ]);

    $order->update([
        'subtotal' => $quantity * $unitPrice,
        'tax_amount' => 0,
        'grand_total' => $quantity * $unitPrice,
    ]);

    $order = $order->fresh();

    PurchaseOrderService::applyStatusChange($order, null, $order->purchase_status);
    PurchaseOrderService::applySupplierDue($order);

    return $order;
}

it('lists the purchase records with an add button to the purchase order form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit);

    $this->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertSee($order->reference_no)
        ->assertSee('Payment Status', false)
        ->assertSee(route('purchase-orders.create'));
});

it('shows the edit, detail, pdf and print views', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit);

    $this->get(route('purchase-invoices.edit', $order))->assertOk()->assertSee('Purchase Tax', false);
    $this->get(route('purchase-invoices.show', $order))->assertOk()->assertSee($order->reference_no);
    $this->get(route('purchase-invoices.pdf-view'))->assertOk()->assertSee('Purchase Orders List', false);
    $this->get(route('purchase-invoices.print-view'))->assertOk()->assertSee('Purchase Orders List', false);
});

it('moves stock and supplier dues when a purchase is approved and again when it is cancelled', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit, 'ordered');

    expect($unit->fresh()->quantity)->toBe(50);

    // Approval receives the goods and registers the payable.
    $this->patch(route('purchase-invoices.approve', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    expect($unit->fresh()->quantity)->toBe(55)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(1000.0);

    // Cancelling afterwards reverses both.
    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'cancelled',
        'paid_amount' => 0,
    ])->assertRedirect(route('purchase-invoices.index'));

    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(0.0);
});

it('keeps line items frozen once the purchase is received', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit, 'received');

    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'received',
        'paid_amount' => 500,
    ])->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect((float) $order->paid_amount)->toBe(500.0)
        ->and($order->items)->toHaveCount(1)
        ->and((int) $order->items->first()->quantity)->toBe(5);
});

it('updates payment amounts and keeps the supplier due in step', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit, 'received', 5, 200.0, 1000.0);

    expect((float) $order->supplier->fresh()->purchase_due)->toBe(0.0)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid);

    $this->patch(route('purchase-invoices.update', $order), [
        'order_date' => $order->order_date->format('Y-m-d\TH:i'),
        'supplier_id' => $order->supplier_id,
        'location_id' => $order->location_id,
        'purchase_status' => 'received',
        'paid_amount' => 0,
    ])->assertRedirect(route('purchase-invoices.index'));

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Due)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(1000.0);
});

it('reverses stock and the supplier due when a received purchase is deleted', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $order = registerOrder($unit, 'received');

    expect($unit->fresh()->quantity)->toBe(55);

    $this->delete(route('purchase-invoices.destroy', $order))
        ->assertRedirect(route('purchase-invoices.index'));

    $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);

    expect($unit->fresh()->quantity)->toBe(50)
        ->and((float) $order->supplier->fresh()->purchase_due)->toBe(0.0);
});

it('lets accountants view the register but not add purchases', function () {
    $accountant = User::factory()->create(['email_verified_at' => now()]);
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant);

    $this->get(route('purchase-invoices.index'))->assertOk();
    $this->get(route('purchase-orders.create'))->assertForbidden();
});
