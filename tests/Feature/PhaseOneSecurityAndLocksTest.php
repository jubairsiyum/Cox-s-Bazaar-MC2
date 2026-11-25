<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Enums\AccountTransactionType;
use App\Enums\StockTransferStatus;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\DayClosingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('blocks a branch manager from editing Super Admin or escalating roles', function () {
    $branchA = Location::factory()->brandShop()->create(['name' => 'Branch A']);
    $branchB = Location::factory()->brandShop()->create(['name' => 'Branch B']);

    $superAdmin = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $superAdmin->assignRole('Super Admin');

    $manager = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $colleague = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branchA->id]);
    $colleague->assignRole('Sales Staff');

    $otherBranchUser = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branchB->id]);
    $otherBranchUser->assignRole('Sales Staff');

    // Manager does not see Super Admin role in create/edit UI
    $this->actingAs($manager)->get(route('users.create'))
        ->assertOk()
        ->assertDontSee('value="Super Admin"', false);

    // Manager cannot update Super Admin (IDOR) or another branch's user
    $this->actingAs($manager)->patch(route('users.update', $superAdmin), [
        'name' => 'Hacked Admin',
        'email' => $superAdmin->email,
        'status' => 'active',
    ])->assertForbidden();

    $this->actingAs($manager)->patch(route('users.update', $otherBranchUser), [
        'name' => 'Other Staff',
        'email' => $otherBranchUser->email,
        'status' => 'active',
    ])->assertForbidden();

    // Manager cannot create a user with Super Admin role
    $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Rogue Admin',
        'email' => 'rogue@example.com',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
        'status' => 'active',
        'branch_id' => $branchA->id,
        'roles' => ['Super Admin'],
    ])->assertSessionHasErrors('roles.0');

    expect(User::where('email', 'rogue@example.com')->exists())->toBeFalse();

    // Manager cannot promote colleague or self to Super Admin
    $this->actingAs($manager)->patch(route('users.update', $colleague), [
        'name' => $colleague->name,
        'email' => $colleague->email,
        'status' => 'active',
        'roles' => ['Super Admin'],
    ])->assertSessionHasErrors('roles.0');

    expect($colleague->fresh()->hasRole('Super Admin'))->toBeFalse();

    $this->actingAs($manager)->patch(route('users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'status' => 'active',
        'roles' => ['Manager', 'Outlet Manager'],
    ])->assertSessionHasErrors('roles');

    expect($manager->fresh()->roles->pluck('name')->all())->toBe(['Manager']);
});

it('deducts completed cash-account expenses in DayClosingService calculations', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Branch']);
    $today = now()->toDateString();

    $cashAccount = Account::factory()->cash()->withBalance(10000)->create(['location_id' => $branch->id]);
    $bankAccount = Account::factory()->bank()->withBalance(10000)->create(['location_id' => $branch->id]);

    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 10000,
        'paid_amount' => 10000,
        'due_amount' => 0,
        'invoice_date' => $today,
    ]);

    // Only completed cash-account expenses count toward cash drawer deduction
    AccountTransaction::factory()->completed()->create([
        'account_id' => $cashAccount->id,
        'location_id' => $branch->id,
        'type' => AccountTransactionType::Expense,
        'amount' => 2500,
        'transaction_date' => $today,
    ]);
    AccountTransaction::factory()->pending()->create([
        'account_id' => $cashAccount->id,
        'location_id' => $branch->id,
        'type' => AccountTransactionType::Expense,
        'amount' => 500,
        'transaction_date' => $today,
    ]);
    AccountTransaction::factory()->completed()->create([
        'account_id' => $bankAccount->id,
        'location_id' => $branch->id,
        'type' => AccountTransactionType::Expense,
        'amount' => 1000,
        'transaction_date' => $today,
    ]);

    $summary = DayClosingService::calculateSummary($branch->id, $today);
    $payable = DayClosingService::getPayableToHqSummary($branch->id);

    expect((float) $summary['cash_sales'])->toBe(10000.0)
        ->and((float) $summary['cash_expenses'])->toBe(2500.0)
        ->and((float) $summary['system_cash'])->toBe(7500.0)
        ->and($payable['cash_close_amount'])->toBe(7500.0);
});

it('blocks branch users from editing completed transfers or transactions and hides HQ destination balances', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Branch One']);
    $outletManager = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branch->id]);
    $outletManager->assignRole('Outlet Manager');

    $accountant = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branch->id]);
    $accountant->assignRole('Accountant');

    $branchCash = Account::factory()->cash()->withBalance(15000)->create([
        'location_id' => $branch->id,
        'name' => 'Till Branch One',
    ]);
    $hqBank = Account::factory()->bank()->withBalance(987654)->create([
        'location_id' => null,
        'name' => 'Central HQ Vault',
    ]);

    // Branch user cannot see HQ balance in To Account dropdown
    $this->actingAs($outletManager)
        ->get(route('account-transfers.create'))
        ->assertOk()
        ->assertSee('Till Branch One')
        ->assertSee('Central HQ Vault')
        ->assertDontSee('987,654.00');

    // Completed transfer cannot be modified by branch users
    $completedTransfer = AccountTransfer::factory()->completed()->create([
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'location_id' => $branch->id,
        'amount' => 5000,
        'created_by' => $outletManager->id,
    ]);

    $this->actingAs($outletManager)->patch(route('account-transfers.update', $completedTransfer), [
        'from_account_id' => $branchCash->id,
        'to_account_id' => $hqBank->id,
        'amount' => '100',
        'transfer_date' => now()->toDateString(),
    ])->assertForbidden();

    // Completed transaction cannot be edited or deleted by branch Accountant
    $completedTx = AccountTransaction::factory()->completed()->create([
        'account_id' => $branchCash->id,
        'location_id' => $branch->id,
        'type' => AccountTransactionType::Expense,
        'amount' => 500,
        'created_by' => $accountant->id,
    ]);

    $this->actingAs($accountant)->patch(route('transactions.update', $completedTx), [
        'account_id' => $branchCash->id,
        'type' => 'expense',
        'category' => 'Rent',
        'amount' => '5000',
        'transaction_date' => now()->toDateString(),
    ])->assertForbidden();

    $this->actingAs($accountant)->delete(route('transactions.destroy', $completedTx))
        ->assertForbidden();
});

it('keeps approvals idempotent and prevents double stock or balance updates on repeated requests', function () {
    $admin = actingAsSuperAdmin();

    // 1. Account transfer double-approve check
    $fromAcc = Account::factory()->cash()->withBalance(5000)->create();
    $toAcc = Account::factory()->bank()->withBalance(1000)->create();
    $transfer = AccountTransfer::factory()->pending()->create([
        'from_account_id' => $fromAcc->id,
        'to_account_id' => $toAcc->id,
        'amount' => 1000,
        'status' => AccountEntryStatus::Pending,
    ]);

    $this->patch(route('account-transfers.approve', $transfer))->assertSessionHas('success');
    $this->patch(route('account-transfers.approve', $transfer))->assertSessionHas('error');

    expect((float) $fromAcc->fresh()->current_balance)->toBe(4000.0)
        ->and((float) $toAcc->fresh()->current_balance)->toBe(2000.0);

    // 2. Stock transfer double-approve check
    $src = Location::factory()->warehouse()->create();
    $dst = Location::factory()->brandShop()->create();
    $unit = Unit::factory()->create(['quantity' => 10]);
    stockAtLocation($unit, 10, $src->id);

    $stockTransfer = StockTransfer::factory()->inTransit()->create([
        'from_location_id' => $src->id,
        'to_location_id' => $dst->id,
    ]);
    $stockTransfer->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 4,
        'unit_price' => 1000,
        'subtotal' => 4000,
    ]);

    $this->patch(route('stock-transfers.approve', $stockTransfer))->assertRedirect(route('stock-transfers.index'));
    $this->patch(route('stock-transfers.approve', $stockTransfer))->assertSessionHas('error');

    expect($stockTransfer->fresh()->status)->toBe(StockTransferStatus::Received)
        ->and($unit->fresh()->quantity)->toBe(14);

    // 3. Sales return double-approve check
    $invoice = SalesInvoice::factory()->posted()->create([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => $dst->id,
        'subtotal' => 2000,
        'grand_total' => 2000,
        'paid_amount' => 2000,
        'due_amount' => 0,
    ]);
    $salesReturn = SalesReturn::factory()->forInvoice($invoice)->create([
        'refund_amount' => 1000,
    ]);
    $salesReturn->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
        'condition' => 'good',
        'restock' => true,
    ]);

    $this->patch(route('sales-returns.approve', $salesReturn))->assertRedirect(route('sales-returns.index'));
    $this->patch(route('sales-returns.approve', $salesReturn))->assertSessionHas('error');

    expect((float) $invoice->fresh()->credit_amount)->toBe(1000.0)
        ->and($unit->fresh()->quantity)->toBe(15);
});

it('enforces catalog unit price and discount bounds for branch staff on POS sales', function () {
    $branch = Location::factory()->brandShop()->create();
    $cashier = User::factory()->create(['email_verified_at' => now(), 'status' => 'active', 'branch_id' => $branch->id]);
    $cashier->assignRole('Sales Staff');

    $product = Product::factory()->create(['sale_price' => 15000]);
    $unit = Unit::factory()->for($product)->create(['quantity' => 5]);
    stockAtLocation($unit, 5, $branch->id);

    // Attempt to tamper unit_price down to 1.00
    $this->actingAs($cashier)
        ->post(route('pos.store'), [
            'location_id' => $branch->id,
            'paid_amount' => 15000,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 1, 'discount' => 0],
            ],
        ])
        ->assertRedirect(route('pos.index'));

    $invoice = SalesInvoice::latest('id')->firstOrFail();

    expect((float) $invoice->grand_total)->toBe(15000.0)
        ->and((float) $invoice->items->first()->unit_price)->toBe(15000.0);

    // Attempt to pass cart_discount > subtotal
    $this->actingAs($cashier)
        ->post(route('pos.store'), [
            'location_id' => $branch->id,
            'cart_discount' => 20000,
            'paid_amount' => 0,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 15000, 'discount' => 0],
            ],
        ])
        ->assertSessionHasErrors('cart_discount');
});

it('attaches security headers to authenticated ERP responses', function () {
    actingAsSuperAdmin();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
