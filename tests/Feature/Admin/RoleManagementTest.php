<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists roles for a Super Admin', function () {
    actingAsSuperAdmin();

    $this->get(route('roles.index'))->assertOk();
});

it('creates a role with permissions', function () {
    actingAsSuperAdmin();

    $this->post(route('roles.store'), [
        'name' => 'Regional Manager',
        'permissions' => ['users.view', 'products.view', 'reports.view'],
    ])->assertRedirect(route('roles.index'));

    $role = Role::findByName('Regional Manager');

    $this->assertTrue($role->hasPermissionTo('users.view'));
    $this->assertTrue($role->hasPermissionTo('reports.view'));
    $this->assertFalse($role->hasPermissionTo('users.create'));
});

it('updates a role and syncs permissions', function () {
    actingAsSuperAdmin();

    $role = Role::findByName('Manager');

    $this->patch(route('roles.update', $role), [
        'name' => 'Manager',
        'permissions' => ['users.view'],
    ])->assertRedirect(route('roles.index'));

    $role->refresh();

    $this->assertTrue($role->hasPermissionTo('users.view'));
    $this->assertFalse($role->hasPermissionTo('products.manage'));
});

it('prevents deleting the Super Admin role', function () {
    actingAsSuperAdmin();

    $superAdmin = Role::findByName('Super Admin');

    $this->delete(route('roles.destroy', $superAdmin))->assertForbidden();
});

it('prevents deleting a role that has assigned users', function () {
    actingAsSuperAdmin();

    $manager = Role::findByName('Manager');
    $user = User::factory()->create();
    $user->assignRole('Manager');

    $this->delete(route('roles.destroy', $manager))->assertSessionHas('error');
});
