<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function branchManagerAt(Location $location): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $location->id]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('lists company-wide accounts in the supplier payment form for a branch user', function () {
    $branch = Location::factory()->create();
    $shared = Account::factory()->create(['name' => 'Shared Cash Till', 'location_id' => null, 'status' => 'active']);
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $branch->id,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    $this->actingAs(branchManagerAt($branch))
        ->get(route('supplier-payments.create', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertSee($shared->name);
});

it('loads the supplier payments list for a branch user with an unpaid order', function () {
    $branch = Location::factory()->create();
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $branch->id,
        'grand_total' => 1000,
        'paid_amount' => 250,
    ]);

    $this->actingAs(branchManagerAt($branch))
        ->get(route('supplier-payments.index'))
        ->assertOk();
});

it('does not list another branch account in the supplier payment form', function () {
    $branch = Location::factory()->create();
    $otherBranch = Location::factory()->create();
    $otherAccount = Account::factory()->create(['name' => 'Other Branch Drawer', 'location_id' => $otherBranch->id, 'status' => 'active']);
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'location_id' => $branch->id]);

    $this->actingAs(branchManagerAt($branch))
        ->get(route('supplier-payments.create', ['supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertDontSee($otherAccount->name);
});
