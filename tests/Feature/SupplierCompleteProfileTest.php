<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Helpers\Money;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase order for the supplier at the branch, built from factories with
 * the given money figures (the dues board reads these columns directly).
 *
 * @param  array<string, mixed>  $attributes
 */
function completeOrder(Supplier $supplier, Location $location, array $attributes = []): PurchaseOrder
{
    return PurchaseOrder::factory()->create(array_merge([
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'supplier_status' => SupplierOrderStatus::Accepted,
        'subtotal' => 1000,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ], $attributes));
}

/**
 * Add a product line to a purchase order.
 */
function completeOrderLine(PurchaseOrder $order, string $productName, int $quantity, float $unitPrice): PurchaseOrderItem
{
    $unit = Unit::factory()->create([
        'product_id' => Product::factory()->create(['name' => $productName])->id,
        'quantity' => 100,
    ]);

    return PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => round($quantity * $unitPrice, 2),
    ]);
}

/**
 * A purchase return of the order with one product line.
 */
function completeReturn(PurchaseOrder $order, string $approval, float $grandTotal, float $refunded, string $productName, int $quantity, float $unitPrice): PurchaseReturn
{
    $return = PurchaseReturn::factory()->forPurchase($order)->create([
        'approval_status' => $approval,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
        'paid_amount' => $refunded,
    ]);

    $unit = Unit::factory()->create([
        'product_id' => Product::factory()->create(['name' => $productName])->id,
        'quantity' => 100,
    ]);

    PurchaseReturnItem::factory()->create([
        'purchase_return_id' => $return->id,
        'unit_id' => $unit->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => round($quantity * $unitPrice, 2),
    ]);

    return $return;
}

/**
 * A verified Supplier-role login linked to a supplier of the given name.
 *
 * @return array{0: User, 1: Supplier}
 */
function completeSupplierLogin(string $name): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    $supplier = Supplier::factory()->create(['name' => $name, 'user_id' => $user->id]);

    return [$user, $supplier];
}

/**
 * A verified Manager assigned to the given branch.
 */
function completeBranchManager(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'active',
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Manager');

    return $user;
}

it('shows the supplier details, the summary and every purchase order with its product lines', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'name' => 'Walton Hub',
        'tax_number' => 'TIN998877',
        'email' => 'sales@walton.test',
        'address' => 'Hashim Road',
        'opening_balance' => 0,
        'purchase_due' => 0,
        'purchase_return_due' => 0,
    ]);
    $chakaria = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $teknaf = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $open = completeOrder($supplier, $chakaria, [
        'subtotal' => 1000, 'tax_rate' => 5, 'tax_amount' => 50, 'grand_total' => 1050, 'paid_amount' => 50,
    ]);
    completeOrderLine($open, 'Samsung A15', 5, 200);

    $delivered = completeOrder($supplier, $teknaf, [
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 500,
        'purchase_status' => PurchaseStatus::Received,
        'supplier_status' => SupplierOrderStatus::Delivered,
    ]);
    completeOrderLine($delivered, 'Redmi 9', 2, 250);

    $cancelled = completeOrder($supplier, $chakaria, [
        'subtotal' => 300, 'grand_total' => 300, 'purchase_status' => PurchaseStatus::Cancelled,
    ]);
    completeOrderLine($cancelled, 'Nokia 105', 1, 300);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Walton Hub')
        ->assertSee('TIN998877')
        ->assertSee('sales@walton.test')
        ->assertSee('Hashim Road')
        ->assertSee('Samsung A15')
        ->assertSee('Redmi 9')
        ->assertSee('Nokia 105')
        ->assertSee('Cancelled')
        ->assertSee('Delivered')
        ->assertSee('Show Items')
        ->assertViewHas('details', fn (array $details) => $details['identity'][2]['value'] === 'TIN998877'
            && $details['ledger'] !== null)
        ->assertViewHas('summary', fn (array $summary) => $summary['purchased'] === 1550.0
            && $summary['paid'] === 550.0
            && $summary['due'] === 1000.0
            && $summary['returned'] === 0.0
            && $summary['open_orders'] === 1
            && $summary['branches_with_dues'] === 1
            && $summary['branch_count'] === 2)
        ->assertViewHas('orders', function ($orders) use ($open) {
            $row = $orders->firstWhere('order.id', $open->id);

            return $row !== null
                && $row['lines']->count() === 1
                && str_starts_with($row['lines']->first()['product'], 'Samsung A15')
                && $row['lines']->first()['quantity'] === 5
                && $row['lines']->first()['rate'] === 200.0
                && $row['lines']->first()['subtotal'] === 1000.0
                && $row['grand_total'] === 1050.0
                && $row['due'] === 1000.0;
        });
});

it('lists every payment with its method and reference, newest first, with totals', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create(['name' => 'Ukhia Outlet']);
    $order = completeOrder($supplier, $location, ['grand_total' => 1000, 'paid_amount' => 50]);
    $manager = completeBranchManager($location);
    $manager->update(['name' => 'Karim Ahmed']);

    PurchasePayment::factory()->approved()->create([
        'purchase_order_id' => $order->id,
        'amount' => 50,
        'method' => 'bkash',
        'note' => 'TXN-778',
        'created_by' => $manager->id,
        'created_at' => now()->subDays(2),
    ]);

    PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 999,
        'method' => 'cash',
        'note' => 'Pending bKash',
        'created_at' => now()->subDay(),
    ]);

    $supplier->openingPayments()->create([
        'payment_date' => now()->toDateString(),
        'amount' => 300,
        'payment_type' => 'cash',
        'payment_note' => 'Return Due Payment',
        'created_by' => $admin->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('bKash')
        ->assertSee('TXN-778')
        ->assertSee('Karim Ahmed')
        ->assertSee('Return due payment')
        ->assertViewHas('payments', fn (array $payments) => $payments['invoice_total'] === 50.0
            && $payments['supplier_total'] === 300.0
            && $payments['total'] === 350.0
            && $payments['rows']->count() === 3
            && $payments['rows']->pluck('reference')->all() === ['Return Due Payment', 'Pending bKash', 'TXN-778']
            && $payments['rows']->pluck('method')->all() === ['Cash', 'Cash', 'bKash']);
});

it('lists purchase returns with product quantities, value and the return due they add', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create(['name' => 'Teknaf Outlet']);
    $order = completeOrder($supplier, $location, ['grand_total' => 1000, 'paid_amount' => 0]);

    completeReturn($order, 'approved', 400, 100, 'Samsung A15', 2, 200);
    completeReturn($order, 'pending', 100, 0, 'Redmi 9', 1, 100);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Samsung A15')
        ->assertSee('× 2')
        ->assertSee('Redmi 9')
        ->assertSee('Awaiting Approval')
        ->assertViewHas('returns', fn (array $returns) => $returns['count'] === 2
            && $returns['value_total'] === 400.0
            && $returns['refunded_total'] === 100.0
            && $returns['due_total'] === 300.0
            && $returns['quantity_total'] === 2)
        ->assertViewHas('summary', fn (array $summary) => $summary['returned'] === 400.0);
});

it('shows each branch with its ordered, returned, paid and due amounts, cleared or owing', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $owing = Location::factory()->create(['name' => 'Ukhia Outlet']);
    $cleared = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $owingOrder = completeOrder($supplier, $owing, ['grand_total' => 1000, 'paid_amount' => 400]);
    completeReturn($owingOrder, 'approved', 200, 0, 'Samsung A15', 1, 200);

    completeOrder($supplier, $cleared, ['grand_total' => 700, 'paid_amount' => 750]);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Still Owing')
        ->assertSee('Dues Cleared')
        ->assertViewHas('branches', function ($branches) {
            $ukhia = $branches->firstWhere('location_name', 'Ukhia Outlet');
            $teknaf = $branches->firstWhere('location_name', 'Teknaf Outlet');

            return $ukhia['grand_total'] === 1000.0
                && $ukhia['returned'] === 200.0
                && $ukhia['paid_amount'] === 400.0
                && $ukhia['due'] === 600.0
                && $ukhia['cleared'] === false
                && $teknaf['due'] === 0.0
                && $teknaf['cleared'] === true;
        });
});

it('lists cancelled orders in the history but leaves them out of dues and totals', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create(['name' => 'Cox Outlet']);

    $live = completeOrder($supplier, $location, ['grand_total' => 1000, 'paid_amount' => 0]);
    $cancelled = completeOrder($supplier, $location, [
        'grand_total' => 500, 'paid_amount' => 0, 'purchase_status' => PurchaseStatus::Cancelled,
    ]);
    completeOrderLine($cancelled, 'Nokia 105', 3, 150);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('This order was cancelled')
        ->assertViewHas('orders', fn ($orders) => $orders->count() === 2
            && $orders->contains(fn (array $row) => $row['cancelled'] && $row['order']->id === $cancelled->id))
        ->assertViewHas('branches', fn ($branches) => $branches->count() === 1
            && $branches->first()['due'] === 1000.0
            && $branches->first()['orders']->pluck('id')->all() === [$live->id])
        ->assertViewHas('summary', fn (array $summary) => $summary['purchased'] === 1000.0
            && $summary['live_orders'] === 1
            && $summary['open_orders'] === 1);
});

it('shows empty states for a supplier with no purchase activity', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('No purchase orders recorded for this supplier yet.')
        ->assertSee('No payments recorded for this supplier yet.')
        ->assertSee('No purchase returns recorded for this supplier yet.')
        ->assertSee('No payment requests sent yet.')
        ->assertSee('No branch currently owes')
        ->assertViewHas('summary', fn (array $summary) => $summary['purchased'] === 0.0
            && $summary['due'] === 0.0
            && $summary['open_orders'] === 0
            && $summary['branch_count'] === 0);
});

it('lets a supplier login request payment from every branch that owes it, but not from a branch with no dues', function () {
    [$supplierUser, $supplier] = completeSupplierLogin('Realme Hub Bangladesh');

    $chakaria = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $teknaf = Location::factory()->create(['name' => 'Teknaf Outlet']);
    $settled = Location::factory()->create(['name' => 'Cox Outlet']);

    completeOrder($supplier, $chakaria, ['grand_total' => 1000, 'paid_amount' => 0]);
    $teknafOrder = completeOrder($supplier, $teknaf, ['grand_total' => 800, 'paid_amount' => 300]);
    completeOrder($supplier, $settled, ['grand_total' => 500, 'paid_amount' => 500]);

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'requests'))
        ->assertOk()
        ->assertViewHas('requestBranches', fn ($branches) => $branches->pluck('id')->sort()->values()->all()
            === collect([$chakaria->id, $teknaf->id])->sort()->values()->all());

    $this->actingAs($supplierUser)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $chakaria->id,
            'message' => 'Please clear the Chakaria dues.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->actingAs($supplierUser)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $teknaf->id,
            'purchase_order_id' => $teknafOrder->id,
            'amount' => 500,
            'message' => 'Teknaf balance please.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->actingAs($supplierUser)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $settled->id,
            'message' => 'Pay me.',
        ])
        ->assertSessionHasErrors(['location_id' => 'This branch has no outstanding dues with the supplier.']);

    expect(SupplierPaymentRequest::query()->where('created_by', $supplierUser->id)->count())->toBe(2)
        ->and(SupplierPaymentRequest::query()->where('location_id', $settled->id)->exists())->toBeFalse();

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'requests'))
        ->assertOk()
        ->assertSee('Please clear the Chakaria dues.')
        ->assertSee('Teknaf balance please.')
        ->assertSee('>Open</span>', false)
        ->assertSee('Cancel Request')
        ->assertDontSee('Record Payment');
});

it('rejects a payment request amount above the due of the branch or of the chosen invoice', function () {
    [$supplierUser, $supplier] = completeSupplierLogin('Bismillah Telecom');

    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $other = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $order = completeOrder($supplier, $location, ['grand_total' => 1000, 'paid_amount' => 200]);
    completeOrder($supplier, $location, ['grand_total' => 500, 'paid_amount' => 0]);
    $otherOrder = completeOrder($supplier, $other, ['grand_total' => 300, 'paid_amount' => 0]);

    $route = route('suppliers.profile.payment-requests.store', $supplier);

    $this->actingAs($supplierUser)
        ->post($route, ['location_id' => $location->id, 'amount' => 1300.01, 'message' => 'Too much.'])
        ->assertSessionHasErrors(['amount' => 'The amount may not exceed the outstanding due of '.Money::format(1300).'.']);

    $this->actingAs($supplierUser)
        ->post($route, [
            'location_id' => $location->id,
            'purchase_order_id' => $order->id,
            'amount' => 800.01,
            'message' => 'Too much for this invoice.',
        ])
        ->assertSessionHasErrors(['amount' => 'The amount may not exceed the outstanding due of '.Money::format(800).'.']);

    $this->actingAs($supplierUser)
        ->post($route, [
            'location_id' => $location->id,
            'purchase_order_id' => $order->id,
            'amount' => 800,
            'message' => 'Exactly the invoice due.',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($supplierUser)
        ->post($route, [
            'location_id' => $location->id,
            'purchase_order_id' => $otherOrder->id,
            'message' => 'Invoice from another branch.',
        ])
        ->assertSessionHasErrors('purchase_order_id');

    expect(SupplierPaymentRequest::query()->count())->toBe(1)
        ->and((float) SupplierPaymentRequest::query()->firstOrFail()->amount)->toBe(800.0);
});

it('lets a supplier login cancel its own open payment request', function () {
    [$supplierUser, $supplier] = completeSupplierLogin('Realme Hub Bangladesh');

    $location = Location::factory()->create();
    $order = completeOrder($supplier, $location, ['grand_total' => 1000, 'paid_amount' => 0]);
    $paymentRequest = SupplierPaymentRequest::factory()->forOrder($order)->create();

    $this->actingAs($supplierUser)
        ->patch(route('suppliers.profile.payment-requests.cancel', [$supplier, $paymentRequest]))
        ->assertSessionHas('success');

    expect($paymentRequest->fresh()->status)->toBe(SupplierPaymentRequestStatus::Cancelled);
});

it('keeps branch users inside their own branch across the complete profile', function () {
    [, $supplier] = completeSupplierLogin('Realme Hub Bangladesh');

    $chakaria = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $teknaf = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $chakariaOrder = completeOrder($supplier, $chakaria, ['grand_total' => 1000, 'paid_amount' => 100]);
    $teknafOrder = completeOrder($supplier, $teknaf, ['grand_total' => 700, 'paid_amount' => 200]);

    completeOrderLine($teknafOrder, 'Redmi 9', 2, 350);
    completeReturn($teknafOrder, 'approved', 50, 0, 'Redmi 9', 1, 50);

    PurchasePayment::factory()->approved()->create(['purchase_order_id' => $chakariaOrder->id, 'amount' => 100]);
    PurchasePayment::factory()->approved()->create(['purchase_order_id' => $teknafOrder->id, 'amount' => 200]);
    $supplier->openingPayments()->create([
        'payment_date' => now()->toDateString(),
        'amount' => 300,
        'payment_type' => 'cash',
        'payment_note' => 'Due Payment',
    ]);

    $manager = completeBranchManager($chakaria);

    $this->actingAs($manager)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Chakaria Outlet')
        ->assertDontSee('Teknaf Outlet')
        ->assertDontSee(route('purchase-invoices.show', $teknafOrder))
        ->assertViewHas('branches', fn ($branches) => $branches->count() === 1
            && $branches->first()['location_id'] === $chakaria->id)
        ->assertViewHas('summary', fn (array $summary) => $summary['purchased'] === 1000.0
            && $summary['paid'] === 100.0
            && $summary['due'] === 900.0
            && $summary['returned'] === 0.0
            && $summary['branch_count'] === 1)
        ->assertViewHas('payments', fn (array $payments) => $payments['rows']->count() === 1
            && $payments['invoice_total'] === 100.0
            && $payments['supplier_total'] === 0.0)
        ->assertViewHas('returns', fn (array $returns) => $returns['count'] === 0)
        ->assertViewHas('details', fn (array $details) => $details['ledger'] === null);

    $this->actingAs($manager)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $teknaf->id,
            'message' => 'Not my branch.',
        ])
        ->assertSessionHasErrors(['location_id' => 'You can only send payment requests to your own branch.']);
});

it('refuses a supplier login without a linked supplier profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    $this->actingAs($user)
        ->get(route('suppliers.my-profile'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'dues'))
        ->assertForbidden();

    [, $otherSupplier] = completeSupplierLogin('Someone Else Telecom');

    $this->actingAs($user)
        ->post(route('suppliers.profile.payment-requests.store', $otherSupplier), [
            'location_id' => Location::factory()->create()->id,
            'message' => 'Pay up.',
        ])
        ->assertForbidden();
});
