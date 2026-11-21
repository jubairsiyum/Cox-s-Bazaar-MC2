<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\StockService;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified user with the given role, assigned to the given branch.
 */
function branchUserAt(Location $location, string $role, string $name = 'Branch User'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole($role);

    return $user;
}

/**
 * A purchase order at the given location with money still outstanding.
 */
function branchPurchaseAt(Location $location): PurchaseOrder
{
    return PurchaseOrder::factory()->create([
        'location_id' => $location->id,
        'purchase_status' => 'received',
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);
}

it('shows an unassigned user no operational data at all', function () {
    $location = Location::factory()->create();
    $order = branchPurchaseAt($location);
    $invoice = SalesInvoice::factory()->posted()->create([
        'location_id' => $location->id,
        'sales_order_id' => SalesOrder::factory()->create(['location_id' => $location->id])->id,
    ]);
    $account = Account::factory()->cash()->withBalance(1000)->create(['location_id' => $location->id]);
    $transaction = AccountTransaction::factory()->expense()->create(['account_id' => $account->id]);
    $product = Product::factory()->create(['name' => 'Strictly Held Phone']);
    $unit = Unit::factory()->for($product)->create(['quantity' => 0]);
    StockService::restock($unit, 5, null, null, $location->id);

    $colleague = User::factory()->create(['name' => 'Assigned Colleague', 'branch_id' => $location->id]);

    // A Manager without a branch assignment is not a Super Admin: nothing
    // operational may leak to them until they are assigned a branch.
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Manager');

    // The Products section needs its own permission holder, also unassigned.
    $catalogUser = User::factory()->create(['email_verified_at' => now()]);
    $catalogUser->assignRole('Sales Staff');

    $this->actingAs($user)->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertDontSee($order->reference_no);

    $this->actingAs($user)->get(route('sales-invoices.index'))
        ->assertOk()
        ->assertDontSee($invoice->invoice_no);

    $this->actingAs($catalogUser)->get(route('products.index'))
        ->assertOk()
        ->assertDontSee('Strictly Held Phone');

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertOk()
        ->assertDontSee($transaction->reference_no);

    $this->actingAs($user)->get(route('cash-accounts.index'))
        ->assertOk()
        ->assertDontSee($account->name);

    $this->actingAs($user)->get(route('supplier-payments.index'))
        ->assertOk();

    $this->actingAs($user)->get(route('inventory.index'))
        ->assertOk()
        ->assertDontSee('Strictly Held Phone');

    $this->actingAs($user)->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('Assigned Colleague');

    $this->actingAs($user)->get(route('audit-logs.index'))
        ->assertOk();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($invoice->invoice_no);
});

it('scopes purchases, dues and supplier payments to their own branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myOrder = branchPurchaseAt($mine);
    $otherOrder = branchPurchaseAt($other);

    PurchasePayment::factory()->approved()->create(['purchase_order_id' => $myOrder->id]);
    $otherPayment = PurchasePayment::factory()->approved()->create(['purchase_order_id' => $otherOrder->id]);

    $manager = branchUserAt($mine, 'Manager', 'Branch A Manager');

    $this->actingAs($manager)->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);

    $this->actingAs($manager)->get(route('purchase-dues.index'))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);

    $this->actingAs($manager)->get(route('supplier-payments.index'))
        ->assertOk()
        ->assertDontSee($otherOrder->reference_no);

    // The other branch's payment detail stays out of reach entirely.
    $this->actingAs($manager)->get(route('supplier-payments.show', $otherPayment))
        ->assertForbidden();
});

it('scopes finance accounts, entries and transfers to their own branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myAccount = Account::factory()->cash()->withBalance(1000)->create(['name' => 'Branch A Cash', 'location_id' => $mine->id]);
    $otherAccount = Account::factory()->cash()->withBalance(1000)->create(['name' => 'Branch B Cash', 'location_id' => $other->id]);
    $sharedAccount = Account::factory()->cash()->withBalance(1000)->create(['name' => 'Head Office Vault']);

    $myEntry = AccountTransaction::factory()->expense()->create(['account_id' => $myAccount->id]);
    $otherEntry = AccountTransaction::factory()->expense()->create(['account_id' => $otherAccount->id]);
    $sharedEntry = AccountTransaction::factory()->expense()->create(['account_id' => $sharedAccount->id]);

    $accountant = branchUserAt($mine, 'Accountant', 'Branch A Accountant');

    $this->actingAs($accountant)->get(route('cash-accounts.index'))
        ->assertOk()
        ->assertSee('Branch A Cash')
        ->assertDontSee('Branch B Cash')
        // Shared/head-office accounts (no branch) are Super Admin only.
        ->assertDontSee('Head Office Vault');

    $this->actingAs($accountant)->get(route('transactions.index'))
        ->assertOk()
        ->assertSee($myEntry->reference_no)
        ->assertDontSee($otherEntry->reference_no)
        ->assertDontSee($sharedEntry->reference_no);

    // Cross-branch account detail is not reachable by id either.
    $this->actingAs($accountant)->get(route('transactions.edit', $otherEntry))
        ->assertForbidden();
});

it('scopes inventory stock levels to their own branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myProduct = Product::factory()->create(['name' => 'My Branch Phone']);
    $myUnit = Unit::factory()->for($myProduct)->create(['quantity' => 0]);
    StockService::restock($myUnit, 5, null, null, $mine->id);

    $otherProduct = Product::factory()->create(['name' => 'Other Branch Phone']);
    $otherUnit = Unit::factory()->for($otherProduct)->create(['quantity' => 0]);
    StockService::restock($otherUnit, 7, null, null, $other->id);

    $storekeeper = branchUserAt($mine, 'Storekeeper', 'Branch A Storekeeper');

    $this->actingAs($storekeeper)->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('My Branch Phone')
        ->assertDontSee('Other Branch Phone');

    // Location/branch URL params cannot widen the inventory scope either.
    $this->actingAs($storekeeper)->get(route('inventory.index', [
        'location_id' => $other->id,
        'branch' => $other->branch_id,
    ]))
        ->assertOk()
        ->assertSee('My Branch Phone')
        ->assertDontSee('Other Branch Phone');
});

it('scopes the dashboard widgets to their own branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myInvoice = SalesInvoice::factory()->posted()->create([
        'location_id' => $mine->id,
        'grand_total' => 1234,
        'due_amount' => 1234,
    ]);
    $otherInvoice = SalesInvoice::factory()->posted()->create([
        'location_id' => $other->id,
        'grand_total' => 9999,
        'due_amount' => 9999,
    ]);

    $manager = branchUserAt($mine, 'Manager', 'Branch A Manager');

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertSee($myInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no)
        ->assertDontSee(number_format(9999));
});

it('locks branch users to their own branch despite branch and location URL params', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myOrder = branchPurchaseAt($mine);
    $otherOrder = branchPurchaseAt($other);

    $myInvoice = SalesInvoice::factory()->posted()->create([
        'location_id' => $mine->id,
        'sales_order_id' => SalesOrder::factory()->create(['location_id' => $mine->id])->id,
    ]);
    $otherInvoice = SalesInvoice::factory()->posted()->create([
        'location_id' => $other->id,
        'sales_order_id' => SalesOrder::factory()->create(['location_id' => $other->id])->id,
    ]);

    $otherAccount = Account::factory()->cash()->withBalance(500)->create(['location_id' => $other->id]);
    $otherEntry = AccountTransaction::factory()->expense()->create(['account_id' => $otherAccount->id]);

    $manager = branchUserAt($mine, 'Manager', 'Branch A Manager');

    // ?location_id= / ?branch_id= / ?branch= can never widen the scope.
    $this->actingAs($manager)->get(route('purchase-invoices.index', ['location_id' => $other->id]))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);

    $this->actingAs($manager)->get(route('sales-invoices.index', ['location_id' => $other->id]))
        ->assertOk()
        ->assertSee($myInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no);

    $this->actingAs($manager)->get(route('reports.sales', ['location_id' => $other->id]))
        ->assertOk()
        ->assertSee($myInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no);

    $this->actingAs($manager)->get(route('transactions.index', ['account_id' => $otherAccount->id]))
        ->assertOk()
        ->assertDontSee($otherEntry->reference_no);
});

it('shows Super Admin every branch and lets the branch filters narrow the view', function () {
    actingAsSuperAdmin();

    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myOrder = branchPurchaseAt($mine);
    $otherOrder = branchPurchaseAt($other);

    $this->get(route('purchase-invoices.index'))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertSee($otherOrder->reference_no);

    $this->get(route('purchase-invoices.index', ['location_id' => $mine->id]))
        ->assertOk()
        ->assertSee($myOrder->reference_no)
        ->assertDontSee($otherOrder->reference_no);
});

it('scopes audit log activity to the actor branch', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $actorMine = branchUserAt($mine, 'Manager', 'Branch A Actor');
    $actorOther = branchUserAt($other, 'Manager', 'Branch B Actor');

    $this->actingAs($actorMine);
    AuditService::log('created', null, [], [], 'Branch A marker');

    $this->actingAs($actorOther);
    AuditService::log('created', null, [], [], 'Branch B marker');

    $viewer = branchUserAt($mine, 'Manager', 'Branch A Manager');

    $this->actingAs($viewer)->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee('Branch A marker')
        ->assertDontSee('Branch B marker');
});

it('keeps branch-tagged notifications visible to their branch only', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $user = branchUserAt($mine, 'Manager', 'Branch A Manager');

    NotificationService::notify($user, 'Tagged for A', '', null, 'info', $mine->id);
    NotificationService::notify($user, 'Tagged for B', '', null, 'info', $other->id);
    NotificationService::notify($user, 'Personal entry');

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Tagged for A')
        ->assertSee('Personal entry')
        ->assertDontSee('Tagged for B');
});

it('scopes the users admin list to their own branch and hides the branch filter', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    User::factory()->create(['name' => 'Branch A Clerk', 'branch_id' => $mine->id]);
    User::factory()->create(['name' => 'Branch B Clerk', 'branch_id' => $other->id]);

    $manager = branchUserAt($mine, 'Manager', 'Branch A Manager');

    $this->actingAs($manager)->get(route('users.index'))
        ->assertOk()
        ->assertSee('Branch A Clerk')
        ->assertDontSee('Branch B Clerk')
        ->assertDontSee('name="branch_id"', false);

    actingAsSuperAdmin();

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Branch A Clerk')
        ->assertSee('Branch B Clerk')
        ->assertSee('name="branch_id"', false);
});

it('assigns the seeded demo accounts to the flagship demo branch', function () {
    $demoBranch = Location::factory()->create([
        'code' => 'BS01',
        'name' => 'Brand Shop - Cox\'s Bazar',
        'status' => 'active',
    ]);

    $this->seed(DemoUsersSeeder::class);

    foreach (['manager@cberp.local', 'accountant@cberp.local', 'sales@cberp.local', 'storekeeper@cberp.local'] as $email) {
        expect(User::query()->where('email', $email)->firstOrFail()->branch_id)
            ->toBe($demoBranch->id);
    }
});
