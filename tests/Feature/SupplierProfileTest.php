<?php

declare(strict_types=1);

use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Models\Account;
use App\Models\Location;
use App\Models\PurchaseOrder;
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
 * A placed purchase order (grand total ৳1,050) for the given supplier and
 * location, created through the real controller flow so the supplier feed
 * and due bookkeeping run exactly as in production.
 *
 * @param  array<string, mixed>  $overrides
 */
function profilePlaceOrder(Supplier $supplier, Location $location, array $overrides = []): PurchaseOrder
{
    $unit = Unit::factory()->create(['quantity' => 50]);

    test()->post(route('purchase-orders.store'), array_merge([
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
    ], $overrides))->assertRedirect(route('purchase-invoices.index'));

    return PurchaseOrder::query()->latest('id')->firstOrFail();
}

it('shows the supplier profile with the activity feed, dues board and purchase orders', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['name' => 'Bismillah Telecom', 'opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = profilePlaceOrder($supplier, $location);

    // A partial payment recorded through Purchase Dues lands on the feed too.
    $this->actingAs($admin)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'method' => 'bkash',
    ])->assertRedirect();

    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Bismillah Telecom')
        ->assertSee('Notifications &amp; Activity', false)
        // 1.1 — the new purchase order announcement.
        ->assertSee('New purchase order')
        ->assertSee($order->reference_no)
        ->assertSee('Chakaria Outlet')
        ->assertSee('1,050.00')
        // Issue 6 — every payment recorded shows its full detail.
        ->assertSee('500.00')
        ->assertSee('bKash')
        ->assertSee('recorded by')
        ->assertSee('Branches with dues cleared')
        ->assertSee('still owing')
        // The branch-wise board and the order answer section.
        ->assertSee('Branch-wise Dues')
        ->assertSee('Purchase Orders')
        ->assertSee('Payment Requests')
        ->assertSee('Accept Order')
        ->assertDontSee('Mark Delivered');
});

it('shows the branch-wise dues board with per-order breakdown and partial payments', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $owingLocation = Location::factory()->create(['name' => 'Ukhia Outlet']);
    $clearedLocation = Location::factory()->create(['name' => 'Teknaf Outlet']);
    $manager = User::factory()->create(['name' => 'Rahim Uddin']);

    $owingOrder = profilePlaceOrder($supplier, $owingLocation, ['paid_amount' => 50]);
    $owingOrder->update(['created_by' => $manager->id]);

    $clearedOrder = profilePlaceOrder($supplier, $clearedLocation, ['paid_amount' => 1050]);

    // One partial payment against the owing order.
    $this->post(route('purchase-dues.store'), [
        'purchase_order_id' => $owingOrder->id,
        'amount' => 500,
        'method' => 'cash',
    ])->assertRedirect();

    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Ukhia Outlet')
        ->assertSee('Teknaf Outlet')
        ->assertSee('Still Owing')
        ->assertSee('Dues Cleared')
        ->assertSee('Rahim Uddin')
        ->assertSee($owingOrder->reference_no)
        ->assertSee($clearedOrder->reference_no)
        ->assertSee('Partial Payments')
        ->assertSee('500.00')
        ->assertSee('Show Orders');
});

it('records supplier acceptance from the profile and mirrors the manager side', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create();
    $order = profilePlaceOrder($supplier, $location);

    // The profile offers Accept for the placed order and no delivery action.
    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Accept Order')
        ->assertDontSee('Mark Delivered')
        ->assertSee('>Pending</span>', false);

    $this->actingAs($admin)
        ->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertSessionHas('success');

    expect($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Accepted);

    // The supplier sees Accepted (never "Received") until the order is delivered.
    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('>Accepted</span>', false)
        ->assertDontSee('Accept Order');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => PurchaseOrder::class,
        'auditable_id' => $order->id,
    ]);
});

it('sends a payment request to a branch and notifies its users with a direct action link', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['name' => 'Bismillah Telecom', 'opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create(['name' => 'Chakaria Outlet']);
    $order = profilePlaceOrder($supplier, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $outsider = User::factory()->create(['email_verified_at' => now(), 'branch_id' => Location::factory()->create()->id]);
    $outsider->assignRole('Outlet Manager');

    $this->actingAs($admin)->post(route('suppliers.profile.payment-requests.store', $supplier), [
        'location_id' => $location->id,
        'purchase_order_id' => $order->id,
        'message' => 'Please settle the outstanding dues for this month.',
        'amount' => 1000,
    ])->assertSessionHas('success');

    $paymentRequest = SupplierPaymentRequest::query()->firstOrFail();

    expect($paymentRequest->status)->toBe(SupplierPaymentRequestStatus::Open)
        ->and((float) $paymentRequest->amount)->toBe(1000.0)
        ->and((int) $paymentRequest->created_by)->toBe($admin->id);

    // The targeted branch receives an in-app message notification.
    $notification = $manager->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Payment request from Bismillah Telecom')
        ->and($notification->data['body'])->toContain('1,000.00')
        ->and($notification->data['body'])->toContain('Chakaria Outlet')
        ->and($notification->data['body'])->toContain($order->reference_no)
        ->and($notification->data['body'])->toContain('Please settle the outstanding dues')
        ->and($notification->data['url'])->toContain('/supplier-payments/create')
        ->and($notification->data['url'])->toContain('payment_request_id='.$paymentRequest->id)
        ->and($notification->data['type'])->toBe('warning');

    // Other branches hear nothing.
    expect($outsider->notifications()->count())->toBe(0);

    // The request state shows on the supplier profile.
    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Payment Requests')
        ->assertSee('Please settle the outstanding dues')
        ->assertSee('>Open</span>', false);
});

it('actions a payment request when the branch records the payment from Finance', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create();
    $order = profilePlaceOrder($supplier, $location, ['paid_amount' => 0]);

    $this->post(route('suppliers.profile.payment-requests.store', $supplier), [
        'location_id' => $location->id,
        'purchase_order_id' => $order->id,
        'message' => 'Please pay the remaining dues.',
        'amount' => 1050,
    ])->assertSessionHas('success');

    $paymentRequest = SupplierPaymentRequest::query()->firstOrFail();
    $account = Account::factory()->cash()->withBalance(5000)->create();

    // The notification link lands on the pre-filled recording screen.
    $this->get(route('supplier-payments.create', [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => 1050,
        'payment_request_id' => $paymentRequest->id,
    ]))
        ->assertOk()
        ->assertSee('Payment request from')
        ->assertSee('Please pay the remaining dues.')
        ->assertSee('value="1050"', false);

    // Recording the payment actions the request and links it to the payment.
    $this->post(route('supplier-payments.store'), [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'amount' => 1050,
        'payment_date' => now()->toDateString(),
        'account_id' => $account->id,
        'payment_request_id' => $paymentRequest->id,
    ])->assertRedirect(route('supplier-payments.index'));

    $paymentRequest->refresh();

    expect($paymentRequest->status)->toBe(SupplierPaymentRequestStatus::Actioned)
        ->and((int) $paymentRequest->actioned_by)->toBe($admin->id)
        ->and($paymentRequest->payment_id)->not->toBeNull()
        ->and($order->fresh()->purchase_status->value)->toBe('ordered');

    // The state shows both on the profile and in Finance > Supplier Payments.
    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('>Actioned</span>', false);

    $this->get(route('supplier-payments.index'))
        ->assertOk()
        ->assertSee('Supplier Payment Requests')
        ->assertSee('>Actioned</span>', false)
        ->assertSee(route('supplier-payments.show', $paymentRequest->payment));
});

it('cancels an open payment request from the supplier profile', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create();
    $paymentRequest = SupplierPaymentRequest::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('suppliers.profile.payment-requests.cancel', [$supplier, $paymentRequest]))
        ->assertSessionHas('success');

    expect($paymentRequest->fresh()->status)->toBe(SupplierPaymentRequestStatus::Cancelled);
});

it('guards the supplier profile actions by permission', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create();
    $order = profilePlaceOrder($supplier, $location);

    // Accountants may read the profile but not act on it.
    $accountant = User::factory()->create(['email_verified_at' => now()]);
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk();

    $this->actingAs($accountant)
        ->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertForbidden();

    $this->actingAs($accountant)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $location->id,
            'message' => 'Pay up',
        ])
        ->assertForbidden();

    // Outlet Managers answer purchase orders on the supplier's behalf…
    $outletManager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $outletManager->assignRole('Outlet Manager');

    $this->actingAs($outletManager)
        ->patch(route('suppliers.profile.accept-order', [$supplier, $order]))
        ->assertRedirect();

    expect($order->fresh()->supplier_status)->toBe(SupplierOrderStatus::Accepted);

    // …while payment requests stay with suppliers.manage (Manager / Super Admin).
    $this->actingAs($outletManager)
        ->post(route('suppliers.profile.payment-requests.store', $supplier), [
            'location_id' => $location->id,
            'message' => 'Pay up',
        ])
        ->assertForbidden();
});

it('lists each purchase order with its product, quantity, rate and totals', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location = Location::factory()->create();
    $order = profilePlaceOrder($supplier, $location);
    $productName = $order->items()->firstOrFail()->unit->displayName();

    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Supplier Details')
        ->assertSee('Purchase Returns')
        ->assertSee($productName)
        ->assertSee('Show Items')
        ->assertViewHas('orders', fn ($orders) => $orders->first()['lines']->first()['quantity'] === 5
            && $orders->first()['lines']->first()['rate'] === 200.0
            && $orders->first()['lines']->first()['subtotal'] === 1000.0
            && $orders->first()['grand_total'] === 1050.0
            && $orders->first()['paid'] === 50.0
            && $orders->first()['due'] === 1000.0);
});

it('summarises which branches cleared and which are still owing on every payment notification', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $owingLocation = Location::factory()->create(['name' => 'Ukhia Outlet']);
    $clearedLocation = Location::factory()->create(['name' => 'Teknaf Outlet']);

    $owingOrder = profilePlaceOrder($supplier, $owingLocation);
    $clearedOrder = profilePlaceOrder($supplier, $clearedLocation, ['paid_amount' => 1050]);

    // A payment against the owing order that still leaves a balance.
    $this->actingAs($admin)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $owingOrder->id,
        'amount' => 500,
    ])->assertRedirect();

    // Grab the payment feed entry (notifications within one second share a
    // timestamp, so pick by content instead of ordering).
    $paymentEntry = $supplier->notifications()
        ->get()
        ->first(fn ($notification) => str_contains($notification->data['body'] ?? '', 'recorded on'));

    expect($paymentEntry)->not->toBeNull()
        ->and($paymentEntry->data['body'])->toContain('Branches with dues cleared: Teknaf Outlet')
        ->and($paymentEntry->data['body'])->toContain('still owing: Ukhia Outlet (৳500.00)');
});
