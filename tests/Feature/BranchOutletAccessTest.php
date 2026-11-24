<?php

declare(strict_types=1);

use App\Enums\AccountEntryStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\AccountTransfer;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->seed(AccountSeeder::class);
});

it('restricts the sidebar for an outlet manager to operational items only', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Outlet 1']);
    $manager = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);
    $manager->assignRole('Outlet Manager');

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertOk();

    // Necessary operational items are present
    $response->assertSee('POS Terminal');
    $response->assertSee('Sales');
    $response->assertSee('Inventory');
    $response->assertSee('Customers');
    $response->assertSee('Cash Remittance');
    $response->assertSee('Daily Cash Close');
    $response->assertSee('Reports');

    // Unnecessary / Out-of-scope items are NOT present in branch outlet sidebar
    $response->assertDontSee('Master Data');
    $response->assertDontSee('Purchasing');
    $response->assertDontSee('Suppliers');
    $response->assertDontSee('Cash Accounts');
    $response->assertDontSee('Bank Accounts');
    $response->assertDontSee('Supplier Payment');
    $response->assertDontSee('TailAdmin Components');
});

it('allows an outlet manager to access cash remittance and submit a pending remittance to HQ', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Outlet 1']);
    $manager = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);
    $manager->assignRole('Outlet Manager');

    // Ensure branch has a cash till with balance
    $branchCash = Account::firstOrCreate(
        ['location_id' => $branch->id, 'type' => AccountType::Cash],
        [
            'name' => 'Cash Till - Cox Outlet 1',
            'opening_balance' => 0,
            'current_balance' => 15000,
            'status' => 'active',
        ]
    );
    $branchCash->update(['current_balance' => 15000]);

    // Central HQ Cash
    $hqCash = Account::whereNull('location_id')->where('type', AccountType::Cash)->first()
        ?? Account::factory()->cash()->withBalance(50000)->create(['location_id' => null, 'name' => 'Central Cash']);

    // 1. Can visit Cash Remittance list
    $this->actingAs($manager)
        ->get(route('account-transfers.index'))
        ->assertOk()
        ->assertSee('Cash Remittance');

    // 2. Can visit New Remittance form
    $this->actingAs($manager)
        ->get(route('account-transfers.create'))
        ->assertOk()
        ->assertSee('Cash Remittance')
        ->assertSee($branchCash->name);

    // 3. Can submit cash remittance to Central HQ
    $this->actingAs($manager)
        ->post(route('account-transfers.store'), [
            'from_account_id' => $branchCash->id,
            'to_account_id' => $hqCash->id,
            'amount' => '8000',
            'transfer_date' => now()->toDateString(),
            'method' => 'cash',
            'note' => 'Daily counter cash remittance to HQ',
        ])
        ->assertRedirect(route('account-transfers.index'))
        ->assertSessionHas('success');

    $transfer = AccountTransfer::latest('id')->firstOrFail();

    expect($transfer->status)->toBe(AccountEntryStatus::Pending)
        ->and((float) $transfer->amount)->toBe(8000.0)
        ->and((int) $transfer->location_id)->toBe($branch->id)
        ->and((int) $transfer->created_by)->toBe($manager->id);

    // 4. Branch manager CANNOT approve or delete their own remittance
    $this->actingAs($manager)
        ->patch(route('account-transfers.approve', $transfer))
        ->assertForbidden();

    $this->actingAs($manager)
        ->delete(route('account-transfers.destroy', $transfer))
        ->assertForbidden();

    // 5. Super Admin approves remittance
    actingAsSuperAdmin();
    $this->patch(route('account-transfers.approve', $transfer))
        ->assertSessionHas('success');

    expect($transfer->fresh()->status)->toBe(AccountEntryStatus::Completed)
        ->and((float) $branchCash->fresh()->current_balance)->toBe(7000.0);
});
