<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A purchase invoice with money still outstanding (partially paid).
 *
 * @param  array<string, mixed>  $attributes
 */
function duePurchase(array $attributes = [], ?Supplier $supplier = null, ?Location $location = null): PurchaseOrder
{
    $supplier ??= Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);
    $location ??= Location::factory()->create();

    return PurchaseOrder::factory()->create(array_merge([
        'supplier_id' => $supplier->id,
        'location_id' => $location->id,
        'purchase_status' => 'received',
        'approval_status' => 'approved',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'grand_total' => 5000,
        'paid_amount' => 2000,
    ], $attributes));
}

it('lists partially paid purchases by reference with supplier, branch and due', function () {
    actingAsSuperAdmin();

    $branch = Branch::create(['name' => 'Chakaria Branch', 'code' => 'CHB', 'status' => 'active']);
    $location = Location::factory()->create(['branch_id' => $branch->id]);
    $supplier = Supplier::factory()->create(['name' => 'Bismillah Telecom', 'opening_balance' => 0, 'purchase_due' => 0, 'purchase_return_due' => 0]);

    $outstanding = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier, $location);
    $settled = duePurchase(['grand_total' => 1000, 'paid_amount' => 1000], $supplier, $location);
    $cancelled = duePurchase(['grand_total' => 1000, 'paid_amount' => 0, 'purchase_status' => 'cancelled'], $supplier, $location);

    $this->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertSee('Purchase Dues')
        ->assertSee($outstanding->reference_no)
        ->assertSee('Bismillah Telecom')
        ->assertSee('Chakaria Branch')
        ->assertSee('3,000.00')
        ->assertSee('<span class="badge badge-neutral">No Request</span>', false)
        ->assertSee(route('purchase-invoices.show', $outstanding))
        ->assertDontSee($settled->reference_no)
        ->assertDontSee($cancelled->reference_no);
});

it('filters the purchase dues list by payment request status and branch', function () {
    actingAsSuperAdmin();

    $branch = Branch::create(['name' => 'Ukhia Branch', 'code' => 'UKB', 'status' => 'active']);
    $branchLocation = Location::factory()->create(['branch_id' => $branch->id]);
    $otherLocation = Location::factory()->create();

    $requested = duePurchase([], null, $branchLocation);
    $plain = duePurchase([], null, $otherLocation);

    PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $requested->id,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->get(route('purchase-dues.index', ['request_status' => 'pending']))
        ->assertOk()
        ->assertSee($requested->reference_no)
        ->assertSee('<span class="badge badge-info">Awaiting Approval</span>', false)
        ->assertDontSee($plain->reference_no);

    $this->get(route('purchase-dues.index', ['request_status' => 'none']))
        ->assertOk()
        ->assertSee($plain->reference_no)
        ->assertDontSee($requested->reference_no);

    $this->get(route('purchase-dues.index', ['location_id' => $branchLocation->id]))
        ->assertOk()
        ->assertSee($requested->reference_no)
        ->assertDontSee($plain->reference_no);
});

it('scopes the purchase dues list to the own location for outlet managers', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $mineOrder = duePurchase([], null, $mine);
    $otherOrder = duePurchase([], null, $other);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertSee($mineOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);
});

it('submits a pending due payment request capped at the due and alerts the responsible managers', function () {
    $location = Location::factory()->create();

    $creator = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $creator->assignRole('Accountant');

    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], null, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Manager');

    $this->actingAs($creator)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000,
        'method' => 'bkash',
        'note' => 'Part payment via bKash',
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(3000.0)
        ->and($payment->status)->toBe(ApprovalStatus::Pending)
        ->and((int) $payment->created_by)->toBe($creator->id)
        ->and($payment->method)->toBe('bkash');

    // A pending request has no financial effect yet.
    expect((float) $order->fresh()->paid_amount)->toBe(2000.0);

    expect($manager->notifications()->count())->toBe(1)
        ->and($manager->notifications->first()->data['type'])->toBe('warning');

    // Amounts above the remaining due are rejected.
    $this->actingAs($creator)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000.01,
    ])->assertSessionHasErrors('amount');

    expect(PurchasePayment::query()->count())->toBe(1);
});

it('applies the approved due payment to the purchase and supplier and notifies the creator', function () {
    $creator = User::factory()->create(['email_verified_at' => now()]);

    $location = Location::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier, $location);

    $payment = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 1000,
        'created_by' => $creator->id,
    ]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->patch(route('purchase-dues.approve', $payment))
        ->assertRedirect();

    $order->refresh();
    $payment->refresh();

    expect((float) $order->paid_amount)->toBe(3000.0)
        ->and($order->payment_status)->toBe(PaymentStatus::Partial)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(2000.0)
        ->and($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->approved_by)->toBe($manager->id)
        ->and($payment->approved_at)->not->toBeNull();

    expect($creator->notifications()->count())->toBe(1)
        ->and($creator->notifications->first()->data['type'])->toBe('success');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'approved',
        'auditable_id' => $payment->id,
    ]);

    // A second approved request settles the invoice completely.
    $settlement = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 2000,
        'created_by' => $creator->id,
    ]);

    $this->actingAs($manager)
        ->patch(route('purchase-dues.approve', $settlement))
        ->assertRedirect();

    $order->refresh();

    expect((float) $order->paid_amount)->toBe(5000.0)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(0.0);
});

it('only lets the responsible branch manager approve or reject a due payment request', function () {
    $location = Location::factory()->create();
    $order = duePurchase([], null, $location);
    $payment = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'created_by' => User::factory()->create()->id,
    ]);

    // An outlet manager of another location is an outsider.
    $outsider = User::factory()->create(['email_verified_at' => now(), 'branch_id' => Location::factory()->create()->id]);
    $outsider->assignRole('Outlet Manager');

    $this->actingAs($outsider)
        ->patch(route('purchase-dues.approve', $payment))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('purchase-dues.reject', $payment))
        ->assertForbidden();

    // Staff without a manager role may not approve either.
    $staff = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $staff->assignRole('Accountant');

    $this->actingAs($staff)
        ->patch(route('purchase-dues.approve', $payment))
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and((float) $order->fresh()->paid_amount)->toBe(2000.0);
});

it('applies nothing when a due payment request is rejected', function () {
    $creator = User::factory()->create(['email_verified_at' => now()]);

    $location = Location::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier, $location);

    $payment = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 3000,
        'created_by' => $creator->id,
    ]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->patch(route('purchase-dues.reject', $payment))
        ->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(ApprovalStatus::Rejected)
        ->and((int) $payment->approved_by)->toBe($manager->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(2000.0)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(3000.0);

    expect($creator->notifications()->count())->toBe(1)
        ->and($creator->notifications->first()->data['type'])->toBe('error');
});

it('lets the creator edit or cancel their own pending request only', function () {
    $location = Location::factory()->create();

    $creator = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $creator->assignRole('Accountant');

    $order = duePurchase([], null, $location);

    // A second responsible user at the branch: without one, the request would
    // complete directly (nobody else could ever sign it off) and could not be
    // edited or cancelled as a pending request.
    $other = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $other->assignRole('Accountant');

    $payment = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'created_by' => $creator->id,
    ]);

    $this->actingAs($creator)
        ->patch(route('purchase-dues.update', $payment), ['amount' => 750, 'method' => 'cash'])
        ->assertRedirect();

    expect((float) $payment->fresh()->amount)->toBe(750.0);

    // Another staff member of the same branch may not touch somebody else's
    // request either — the creator-only rule is what blocks them.
    $this->actingAs($other)
        ->patch(route('purchase-dues.update', $payment), ['amount' => 100])
        ->assertForbidden();

    $this->actingAs($creator)
        ->delete(route('purchase-dues.destroy', $payment))
        ->assertRedirect();

    $this->assertDatabaseMissing('purchase_payments', ['id' => $payment->id]);
});

it('completes a due payment directly when the responsible outlet manager records it', function () {
    $location = Location::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000,
        'method' => 'cash',
        'note' => 'Paid the supplier directly',
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    // No self-approval theater: the payment is applied and completed at once.
    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->created_by)->toBe($manager->id)
        ->and((int) $payment->approved_by)->toBe($manager->id)
        ->and($payment->approved_at)->not->toBeNull()
        ->and((float) $order->fresh()->paid_amount)->toBe(5000.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(0.0);

    // The manager is never notified to approve their own task.
    expect($manager->fresh()->notifications()->count())->toBe(0);

    $audit = AuditLog::query()->where('auditable_id', $payment->id)->latest('id')->first();

    expect($audit->description)->toContain('completed directly by the responsible manager');
});

it('completes a due payment directly when a super admin records it', function () {
    $admin = actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier);

    $this->actingAs($admin)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000,
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    expect($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->approved_by)->toBe($admin->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(5000.0)
        ->and($admin->fresh()->notifications()->count())->toBe(0);
});

it('keeps a staff request pending for the outlet manager and never lets the requester approve it', function () {
    $location = Location::factory()->create();
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], null, $location);

    $staff = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $staff->assignRole('Storekeeper');

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($staff)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 500,
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    expect($payment->status)->toBe(ApprovalStatus::Pending)
        ->and((int) $payment->created_by)->toBe($staff->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(2000.0);

    // The outlet manager is asked to sign off — never the requester.
    expect($manager->fresh()->notifications()->count())->toBe(1)
        ->and($staff->fresh()->notifications()->count())->toBe(0);

    $this->actingAs($staff)
        ->patch(route('purchase-dues.approve', $payment))
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($manager)
        ->patch(route('purchase-dues.approve', $payment))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and((float) $order->fresh()->paid_amount)->toBe(2500.0);
});

it('never lets a user decide their own due payment request', function () {
    $location = Location::factory()->create();
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], null, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $ownRequest = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'created_by' => $manager->id,
    ]);

    // A manager may not sign off their own request (policy).
    $this->actingAs($manager)
        ->patch(route('purchase-dues.approve', $ownRequest))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('purchase-dues.reject', $ownRequest))
        ->assertForbidden();

    expect($ownRequest->fresh()->status)->toBe(ApprovalStatus::Pending);

    // Even a Super Admin is stopped by the controller guard on self-approval.
    $admin = actingAsSuperAdmin();

    $adminRequest = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('purchase-dues.approve', $adminRequest))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($adminRequest->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and((float) $order->fresh()->paid_amount)->toBe(2000.0);
});

it('completes the request directly when the requester is the only responsible user', function () {
    $location = Location::factory()->create();
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], null, $location);

    // The sole user at the outlet: the resolved approver set would only ever
    // contain the requester, so no request is raised at all.
    $sole = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $sole->assignRole('Storekeeper');

    $this->actingAs($sole)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 1000,
    ])->assertRedirect();

    $payment = PurchasePayment::query()->first();

    expect($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((int) $payment->approved_by)->toBe($sole->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(3000.0)
        ->and($sole->fresh()->notifications()->count())->toBe(0);

    $audit = AuditLog::query()->where('auditable_id', $payment->id)->latest('id')->first();

    expect($audit->description)->toContain('completed directly');
});

it('completes a legacy pending request directly when its responsible manager updates it', function () {
    $location = Location::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase(['grand_total' => 5000, 'paid_amount' => 2000], $supplier, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $payment = PurchasePayment::factory()->pending()->create([
        'purchase_order_id' => $order->id,
        'amount' => 500,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->patch(route('purchase-dues.update', $payment), ['amount' => 1000, 'method' => 'cash'])
        ->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(ApprovalStatus::Approved)
        ->and((float) $payment->amount)->toBe(1000.0)
        ->and((int) $payment->approved_by)->toBe($manager->id)
        ->and((float) $order->fresh()->paid_amount)->toBe(3000.0)
        ->and((float) $supplier->fresh()->purchase_due)->toBe(2000.0);

    $audit = AuditLog::query()->where('auditable_id', $payment->id)->latest('id')->first();

    expect($audit->description)->toContain('completed directly by the responsible manager');
});

it('keeps the branch side Ordered when the due payment settles the order in full', function () {
    $location = Location::factory()->create();
    $supplier = Supplier::factory()->create(['opening_balance' => 0, 'purchase_due' => 3000, 'purchase_return_due' => 0]);
    $order = duePurchase([
        'grand_total' => 5000,
        'paid_amount' => 2000,
        'purchase_status' => 'ordered',
        'supplier_status' => 'accepted',
    ], $supplier, $location);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)->post(route('purchase-dues.store'), [
        'purchase_order_id' => $order->id,
        'amount' => 3000,
        'method' => 'cash',
    ])->assertRedirect();

    $order->refresh();

    // Settling the invoice does not receive the goods: the branch side stays
    // Ordered and the supplier side Accepted until the goods are received.
    expect((float) $order->paid_amount)->toBe(5000.0)
        ->and($order->purchase_status)->toBe(PurchaseStatus::Ordered)
        ->and($order->supplier_status)->toBe(SupplierOrderStatus::Accepted)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->managerDisplayStatus()[1])->toBe('Ordered')
        ->and($order->supplierDisplayStatus()[1])->toBe('Accepted');

    // The supplier's profile feed records the payment with full detail and
    // the branch-wise cleared / still owing summary.
    $entry = $supplier->notifications()
        ->get()
        ->first(fn ($notification) => str_contains($notification->data['body'] ?? '', 'recorded on'));

    expect($entry)->not->toBeNull()
        ->and($entry->data['body'])->toContain('3,000.00')
        ->and($entry->data['body'])->toContain('Branches with dues cleared')
        ->and($entry->data['body'])->toContain('still owing');
});
