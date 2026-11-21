<?php

declare(strict_types=1);

use App\Exports\SupplierExport;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Pagination\AbstractPaginator;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->branch = Location::factory()->create();

    $this->supplierOneUser = isolationSupplierLogin($this->branch, 'Alpha Telecom');
    $this->supplierOne = Supplier::query()->where('user_id', $this->supplierOneUser->id)->firstOrFail();

    $this->supplierTwoUser = isolationSupplierLogin($this->branch, 'Beta Mobiles');
    $this->supplierTwo = Supplier::query()->where('user_id', $this->supplierTwoUser->id)->firstOrFail();

    $this->orderOne = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierOne->id,
        'location_id' => $this->branch->id,
        'grand_total' => 1000,
    ]);
    $this->orderTwo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierTwo->id,
        'location_id' => $this->branch->id,
        'grand_total' => 2000,
    ]);

    $this->paymentOne = PurchasePayment::factory()->approved()->create([
        'purchase_order_id' => $this->orderOne->id,
        'amount' => 400,
    ]);
    $this->paymentTwo = PurchasePayment::factory()->approved()->create([
        'purchase_order_id' => $this->orderTwo->id,
        'amount' => 600,
    ]);

    $this->returnOne = PurchaseReturn::factory()->forPurchase($this->orderOne)->create();
    $this->returnTwo = PurchaseReturn::factory()->forPurchase($this->orderTwo)->create();

    $this->requestOne = SupplierPaymentRequest::factory()->forOrder($this->orderOne)->create();
    $this->requestTwo = SupplierPaymentRequest::factory()->forOrder($this->orderTwo)->create();
});

/**
 * A verified, active Supplier-role login at the given branch, linked to a supplier of the same name.
 */
function isolationSupplierLogin(Location $branch, string $supplierName): User
{
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    $user->assignRole('Supplier');

    Supplier::factory()->create([
        'name' => $supplierName,
        'user_id' => $user->id,
    ]);

    return $user;
}

/**
 * Grant a supplier login permissions a real account could be given on top of the Supplier role.
 */
function isolationGrant(User $user, array $permissions): User
{
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * The ids of a paginated or plain listing passed to the view.
 */
function isolationIds(mixed $listing): array
{
    $collection = $listing instanceof AbstractPaginator
        ? $listing->getCollection()
        : collect($listing);

    return $collection->pluck('id')->sort()->values()->all();
}

it('sends a supplier login from the dashboard to its own profile', function () {
    $this->actingAs($this->supplierOneUser)
        ->get(route('dashboard'))
        ->assertRedirect(route('suppliers.my-profile'));
});

it('shows a supplier login only its own profile without supplier-list links', function () {
    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee('Alpha Telecom')
        ->assertDontSee('Beta Mobiles')
        ->assertSee($this->orderOne->reference_no)
        ->assertDontSee($this->orderTwo->reference_no)
        ->assertDontSee('Back to Suppliers')
        ->assertDontSee('Parties / Suppliers')
        ->assertDontSee(route('purchase-invoices.show', $this->orderOne))
        ->assertDontSee(route('supplier-payments.show', $this->paymentOne));
});

it('refuses a supplier login the profile of another supplier', function () {
    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.profile', $this->supplierTwo))
        ->assertForbidden();

    isolationGrant($this->supplierOneUser, ['suppliers.view']);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.profile', $this->supplierTwo))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.profile', $this->supplierOne))
        ->assertOk()
        ->assertSee('Alpha Telecom');
});

it('lists only the own supplier in the supplier listings and exports', function () {
    isolationGrant($this->supplierOneUser, ['suppliers.view']);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertViewHas('suppliers', fn ($suppliers) => isolationIds($suppliers) === [$this->supplierOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.print-view'))
        ->assertOk()
        ->assertViewHas('suppliers', fn ($suppliers) => isolationIds($suppliers) === [$this->supplierOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.pdf-view'))
        ->assertOk()
        ->assertViewHas('suppliers', fn ($suppliers) => isolationIds($suppliers) === [$this->supplierOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.export', 'csv'))
        ->assertOk();

    expect(isolationIds((new SupplierExport(null, null, null, $this->supplierOneUser))->collection()))
        ->toBe([$this->supplierOne->id]);
});

it('limits the purchase invoices register and its views to the own supplier', function () {
    isolationGrant($this->supplierOneUser, ['purchase-invoices.view']);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => isolationIds($orders) === [$this->orderOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-invoices.pdf-view'))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => isolationIds($orders) === [$this->orderOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-invoices.print-view'))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => isolationIds($orders) === [$this->orderOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-invoices.show', $this->orderOne))
        ->assertOk();

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-invoices.show', $this->orderTwo))
        ->assertForbidden();
});

it('limits supplier payments to the own supplier', function () {
    isolationGrant($this->supplierOneUser, ['supplier-payments.view']);

    $this->actingAs($this->supplierOneUser)
        ->get(route('supplier-payments.index'))
        ->assertOk()
        ->assertViewHas('payments', fn ($payments) => isolationIds($payments) === [$this->paymentOne->id])
        ->assertViewHas('suppliers', fn ($suppliers) => isolationIds($suppliers) === [$this->supplierOne->id])
        ->assertViewHas('paymentRequests', fn ($requests) => isolationIds($requests) === [$this->requestOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('supplier-payments.show', $this->paymentOne))
        ->assertOk();

    $this->actingAs($this->supplierOneUser)
        ->get(route('supplier-payments.show', $this->paymentTwo))
        ->assertForbidden();
});

it('limits purchase dues and purchase returns to the own supplier', function () {
    isolationGrant($this->supplierOneUser, ['supplier-payments.view', 'purchase-returns.manage']);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => isolationIds($orders) === [$this->orderOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-returns.index'))
        ->assertOk()
        ->assertViewHas('returns', fn ($returns) => isolationIds($returns) === [$this->returnOne->id]);

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-returns.show', $this->returnTwo))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->getJson(route('purchase-returns.supplier-purchases', ['supplier_id' => $this->supplierTwo->id]))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->getJson(route('purchase-returns.supplier-purchases', ['supplier_id' => $this->supplierOne->id]))
        ->assertOk()
        ->assertJsonPath('purchases.0.id', $this->orderOne->id);
});

it('blocks supplier logins from changing any supplier data', function () {
    isolationGrant($this->supplierOneUser, [
        'suppliers.manage',
        'purchase-invoices.manage',
        'supplier-payments.manage',
        'purchase-returns.manage',
    ]);

    $this->actingAs($this->supplierOneUser)
        ->patch(route('suppliers.profile.accept-order', [$this->supplierTwo, $this->orderTwo]))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->post(route('suppliers.profile.payment-requests.store', $this->supplierTwo), [
            'location_id' => $this->branch->id,
            'message' => 'Please settle the dues.',
        ])
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->patch(route('suppliers.profile.payment-requests.cancel', [$this->supplierTwo, $this->requestTwo]))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->post(route('suppliers.pay-due', $this->supplierTwo), [
            'payment_date' => now()->toDateString(),
            'amount' => 10,
            'payment_type' => 'cash',
        ])
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->get(route('supplier-payments.create'))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->delete(route('supplier-payments.destroy', $this->paymentTwo))
        ->assertForbidden();

    $this->actingAs($this->supplierOneUser)
        ->get(route('purchase-returns.create'))
        ->assertForbidden();

    $this->assertDatabaseHas('purchase_payments', ['id' => $this->paymentTwo->id]);
    $this->assertDatabaseHas('suppliers', ['id' => $this->supplierTwo->id, 'name' => 'Beta Mobiles']);
});

it('shows a supplier login only its own payments, returns and requests on their pages', function () {
    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.my-profile.section', 'payments'))
        ->assertOk()
        ->assertViewHas('payments', fn (array $payments) => $payments['invoice_total'] === 400.0 && $payments['rows']->count() === 1);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.my-profile.section', 'returns'))
        ->assertOk()
        ->assertViewHas('returns', fn (array $returns) => $returns['count'] === 1);

    $this->actingAs($this->supplierOneUser)
        ->get(route('suppliers.my-profile.section', 'requests'))
        ->assertOk()
        ->assertViewHas('paymentRequests', fn ($requests) => isolationIds($requests) === [$this->requestOne->id]);
});

it('lets a supplier login request payment on its own profile', function () {
    $this->actingAs($this->supplierOneUser)
        ->post(route('suppliers.profile.payment-requests.store', $this->supplierOne), [
            'location_id' => $this->branch->id,
            'message' => 'Please settle the dues.',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(SupplierPaymentRequest::query()
        ->where('supplier_id', $this->supplierOne->id)
        ->where('created_by', $this->supplierOneUser->id)
        ->exists())->toBeTrue();
});

it('keeps the super admin and the branch rules unchanged', function () {
    actingAsSuperAdmin();

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertViewHas('suppliers', fn ($suppliers) => isolationIds($suppliers) === [$this->supplierOne->id, $this->supplierTwo->id]);

    $this->get(route('suppliers.profile', $this->supplierTwo))
        ->assertOk()
        ->assertSee('Beta Mobiles');

    $this->get(route('purchase-invoices.show', $this->orderTwo))
        ->assertOk();
});
