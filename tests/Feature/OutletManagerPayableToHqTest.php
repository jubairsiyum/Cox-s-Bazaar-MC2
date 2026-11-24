<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\DayClosingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('shows Payable to HQ instead of Retailer Dues for outlet managers', function () {
    $branch = Location::factory()->create(['name' => 'Cox Sadar Branch']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Payable to HQ')
        ->assertDontSee('Retailer Dues (Receivable)');
});

it('shows network receivable and collection cards for super admin viewing all locations network overview', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee("Today's Sales")
        ->assertSee('Collection')
        ->assertSee('Branch Closings (Receivable)');
});

it('shows Payable to HQ when super admin scopes to an outlet', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Kolatoli Branch']);
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->get(route('dashboard', ['location_id' => $branch->id]))
        ->assertOk()
        ->assertSee('Payable to HQ');
});

it('displays lefted amount and 0% progress when daily cash is closed with no transfers yet', function () {
    $branch = Location::factory()->create(['name' => 'Teknaf Outlet']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $today = now()->toDateString();
    DayClosingService::closeDay($branch->id, $today, '50000', $manager);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Payable to HQ')
        ->assertSee('50,000')
        ->assertSee('0%')
        ->assertSee('৳0 remitted of ৳50,000');
});

it('displays lefted amount and partial progress when some cash is transferred to HQ', function () {
    $branch = Location::factory()->create(['name' => 'Ramu Outlet']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $branchCash = Account::factory()->cash()->withBalance(50000)->create([
        'location_id' => $branch->id,
        'name' => 'Branch Till',
    ]);
    $hqBank = Account::factory()->bank()->withBalance(100000)->create([
        'location_id' => null,
        'name' => 'HQ Central Account',
    ]);

    $today = now()->toDateString();
    DayClosingService::closeDay($branch->id, $today, '50000', $manager);

    // Transfer 30,000 to HQ (Completed)
    AccountTransfer::factory()->completed()->create([
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'location_id' => $branch->id,
        'amount' => 30000,
        'transfer_date' => $today,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Payable to HQ')
        ->assertSee('20,000') // Lefted amount: 50,000 - 30,000 = 20,000
        ->assertSee('60%')    // Progress: 30,000 / 50,000 = 60%
        ->assertSee('৳30,000 remitted of ৳50,000');
});

it('displays 100% progress and 0 amount when all closed cash is transferred to HQ', function () {
    $branch = Location::factory()->create(['name' => 'Ukhia Outlet']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $branchCash = Account::factory()->cash()->withBalance(40000)->create([
        'location_id' => $branch->id,
        'name' => 'Branch Till',
    ]);
    $hqBank = Account::factory()->bank()->withBalance(200000)->create([
        'location_id' => null,
        'name' => 'HQ Central Account',
    ]);

    $today = now()->toDateString();
    DayClosingService::closeDay($branch->id, $today, '40000', $manager);

    // Full 40,000 transferred to HQ
    AccountTransfer::factory()->completed()->create([
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'location_id' => $branch->id,
        'amount' => 40000,
        'transfer_date' => $today,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Payable to HQ')
        ->assertSee('100%')
        ->assertSee('All clear · ৳40,000 remitted to HQ');
});

it('indicates pending approval amount in caption when transfer is submitted but pending', function () {
    $branch = Location::factory()->create(['name' => 'Inani Branch']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $branchCash = Account::factory()->cash()->withBalance(25000)->create([
        'location_id' => $branch->id,
        'name' => 'Branch Till',
    ]);
    $hqBank = Account::factory()->bank()->withBalance(50000)->create([
        'location_id' => null,
        'name' => 'HQ Central Bank',
    ]);

    $today = now()->toDateString();
    DayClosingService::closeDay($branch->id, $today, '25000', $manager);

    // Remittance submitted by manager but pending HQ approval
    AccountTransfer::factory()->pending()->create([
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'location_id' => $branch->id,
        'amount' => 25000,
        'transfer_date' => $today,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Payable to HQ')
        ->assertSee('25,000') // Left until approved
        ->assertSee('0%')
        ->assertSee('৳25,000 pending approval');
});

it('unit: DayClosingService returns expected summary array structure and calculations', function () {
    $branch = Location::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);

    // 1. When no closing exists
    $summary = DayClosingService::getPayableToHqSummary($branch->id);
    expect($summary['is_all_clear'])->toBeTrue()
        ->and($summary['progress_pct'])->toBe(100)
        ->and($summary['left_amount'])->toBe(0.0);

    // 2. When day is closed
    $today = now()->toDateString();
    DayClosingService::closeDay($branch->id, $today, '10000', $manager);

    $summary = DayClosingService::getPayableToHqSummary($branch->id);
    expect($summary['cash_close_amount'])->toBe(10000.0)
        ->and($summary['transferred_to_hq'])->toBe(0.0)
        ->and($summary['left_amount'])->toBe(10000.0)
        ->and($summary['progress_pct'])->toBe(0)
        ->and($summary['is_all_clear'])->toBeFalse();
});

it('links Payable to HQ card to Clear Day create when today is not closed, and to day closings index when closed', function () {
    $branch = Location::factory()->create(['name' => 'Cox Main Branch']);
    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    // 1. When today is not closed yet
    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('day-closings.create'))
        ->assertSee('Clear Day →');

    // 2. Once today is closed
    DayClosingService::closeDay($branch->id, now()->toDateString(), '30000', $manager);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('day-closings.index'))
        ->assertSee('Cash locked');
});

it('calculates network collection and receivable correctly for super admin across multiple branches', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $branch1 = Location::factory()->brandShop()->create(['name' => 'Branch One']);
    $branch2 = Location::factory()->brandShop()->create(['name' => 'Branch Two']);

    $mgr1 = User::factory()->create(['branch_id' => $branch1->id, 'status' => 'active']);
    $mgr2 = User::factory()->create(['branch_id' => $branch2->id, 'status' => 'active']);

    $accBranch1 = Account::factory()->cash()->withBalance(50000)->create(['location_id' => $branch1->id]);
    $accHq = Account::factory()->bank()->withBalance(100000)->create(['location_id' => null]);

    $today = now()->toDateString();
    // Branch 1 closes 50,000; Branch 2 closes 30,000 -> Total closings = 80,000
    DayClosingService::closeDay($branch1->id, $today, '50000', $mgr1);
    DayClosingService::closeDay($branch2->id, $today, '30000', $mgr2);

    // Branch 1 remits 20,000 to HQ
    AccountTransfer::factory()->completed()->create([
        'from_account_id' => $accBranch1->id,
        'to_account_id' => $accHq->id,
        'location_id' => $branch1->id,
        'amount' => 20000,
        'transfer_date' => $today,
        'created_by' => $mgr1->id,
    ]);

    // Network summary: Total closings = 80,000, Sent = 20,000, Receivable = 60,000, Progress = 25%
    $summary = DayClosingService::getNetworkPayableSummary();
    expect($summary['total_closing_amount'])->toBe(80000.0)
        ->and($summary['total_sent_to_hq'])->toBe(20000.0)
        ->and($summary['total_receivable'])->toBe(60000.0)
        ->and($summary['progress_pct'])->toBe(25);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Branch Closings (Receivable)')
        ->assertSee('60,000') // Receivable left
        ->assertSee('20,000') // Collection card
        ->assertSee('25%');   // Progress
});

it('displays account transfers in the unified transactions page and filters by type=transfer', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $branchAcc = Account::factory()->cash()->withBalance(50000)->create(['name' => 'Branch Till Account']);
    $hqAcc = Account::factory()->bank()->withBalance(100000)->create(['name' => 'Central HQ Bank']);

    $transfer = AccountTransfer::factory()->completed()->create([
        'from_account_id' => $branchAcc->id,
        'to_account_id' => $hqAcc->id,
        'amount' => 15000,
        'reference_no' => 'TRF-TEST-999',
        'note' => 'Daily cash transfer',
        'transfer_date' => now()->toDateString(),
    ]);

    $incomeTx = AccountTransaction::factory()->completed()->create([
        'account_id' => $branchAcc->id,
        'type' => 'income',
        'amount' => 5000,
        'reference_no' => 'INC-TEST-888',
        'transaction_date' => now()->toDateString(),
    ]);

    // 1. Unified feed shows both transfer and transaction
    $this->actingAs($admin)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('TRF-TEST-999')
        ->assertSee('Branch Till Account')
        ->assertSee('Central HQ Bank')
        ->assertSee('Branch Remittance')
        ->assertSee('INC-TEST-888');

    // 2. Filter by type=transfer shows only the transfer
    $this->actingAs($admin)
        ->get(route('transactions.index', ['type' => 'transfer']))
        ->assertOk()
        ->assertSee('TRF-TEST-999')
        ->assertDontSee('INC-TEST-888');
});

it('only aggregates retail branch sales and excludes central warehouse sales on All Network view', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Sadar Branch']);
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);

    $today = now()->toDateString();

    // Branch sale: 15,000
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 15000,
        'paid_amount' => 15000,
        'invoice_date' => $today,
    ]);

    // Central warehouse wholesale: 80,000
    SalesInvoice::factory()->posted()->create([
        'location_id' => $warehouse->id,
        'grand_total' => 80000,
        'paid_amount' => 80000,
        'invoice_date' => $today,
    ]);

    // 1. All Network view shows ONLY branch sales (15,000) on Today's Sales
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('15,000')
        ->assertDontSee('95,000');

    // 2. Scoping to Main Warehouse explicitly shows warehouse sales (80,000)
    $this->actingAs($admin)
        ->get(route('dashboard', ['location_id' => $warehouse->id]))
        ->assertOk()
        ->assertSee('80,000');
});

it('renders a dismissible daily closing notice for super admin with unclosed branches', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    Location::factory()->brandShop()->create(['name' => 'Cox Sadar Branch']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Daily Closing Notice:')
        ->assertSee('Dismiss notice');
});

it('shows 0 collection and 0% change when no sales or collections occurred today despite yesterday sales', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Sadar Branch']);

    // Yesterday had sales and collection
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 50000,
        'paid_amount' => 50000,
        'invoice_date' => now()->subDay()->toDateString(),
    ]);

    // Today has 0 sales and 0 collection
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee("Today's Sales")
        ->assertSee('Collection')
        ->assertSee('No sales posted today')
        ->assertSee('No remittances collected at HQ today');
});

it('includes live unclosed branch cash sales in super admin receivable and updates when remitted', function () {
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $branch1 = Location::factory()->brandShop()->create(['name' => 'Branch Alpha']);
    $branch2 = Location::factory()->brandShop()->create(['name' => 'Branch Beta']);

    $branch1Acc = Account::factory()->cash()->withBalance(400000)->create(['location_id' => $branch1->id]);
    $hqAcc = Account::factory()->bank()->withBalance(500000)->create(['location_id' => null]);

    // Branch 1 sells 325,000 cash today (unclosed)
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch1->id,
        'grand_total' => 325000,
        'paid_amount' => 325000,
        'due_amount' => 0,
        'invoice_date' => now()->toDateString(),
    ]);

    // Branch 2 sells 265,000 cash today (unclosed)
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch2->id,
        'grand_total' => 265000,
        'paid_amount' => 265000,
        'due_amount' => 0,
        'invoice_date' => now()->toDateString(),
    ]);

    // 1. Before remittances: HQ has collected 0, and total receivable is 590,000
    $summary = DayClosingService::getNetworkPayableSummary();
    expect($summary['total_closing_amount'])->toBe(590000.0)
        ->and($summary['today_sent_to_hq'])->toBe(0.0)
        ->and($summary['total_receivable'])->toBe(590000.0)
        ->and($summary['progress_pct'])->toBe(0);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('590,000') // Today's sales and Branch Closings (Receivable)
        ->assertSee('Collection from Branches')
        ->assertSee('No remittances collected at HQ today');

    // 2. Branch 1 remits 325,000 to Central HQ
    AccountTransfer::factory()->completed()->create([
        'from_account_id' => $branch1Acc->id,
        'to_account_id' => $hqAcc->id,
        'location_id' => $branch1->id,
        'amount' => 325000,
        'transfer_date' => now()->toDateString(),
    ]);

    $summaryAfter = DayClosingService::getNetworkPayableSummary();
    expect($summaryAfter['total_closing_amount'])->toBe(590000.0)
        ->and($summaryAfter['today_sent_to_hq'])->toBe(325000.0)
        ->and($summaryAfter['total_receivable'])->toBe(265000.0) // 590k - 325k = 265k left so far
        ->and($summaryAfter['progress_pct'])->toBe(55);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('325,000') // Collected at HQ
        ->assertSee('265,000') // Left so far at Branch Beta
        ->assertSee('55%');
});
