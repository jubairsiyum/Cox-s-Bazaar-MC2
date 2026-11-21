<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified user holding the given role, assigned to the given location.
 */
function salesAccessUser(string $role, Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole($role);

    return $user;
}

it('lets an outlet manager view the sales order and invoice registers', function () {
    $manager = salesAccessUser('Outlet Manager', Location::factory()->create());

    $this->actingAs($manager)
        ->get(route('sales-orders.index'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(route('sales-invoices.index'))
        ->assertOk();
});

it('denies the sales order register to a user without sales permissions', function () {
    $storekeeper = salesAccessUser('Storekeeper', Location::factory()->create());

    $this->actingAs($storekeeper)
        ->get(route('sales-orders.index'))
        ->assertForbidden();
});

it('restores the outlet manager sales permissions when the seeder is re-run', function () {
    $role = Role::findByName('Outlet Manager');
    $role->revokePermissionTo('sales-orders.manage');

    $manager = salesAccessUser('Outlet Manager', Location::factory()->create());

    $this->actingAs($manager)
        ->get(route('sales-orders.index'))
        ->assertForbidden();

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs($manager)
        ->get(route('sales-orders.index'))
        ->assertOk();
});
