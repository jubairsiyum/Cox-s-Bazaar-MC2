<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('forbids guests from viewing the user list', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing the user list', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

it('lists users for a Super Admin', function () {
    actingAsSuperAdmin();

    $this->get(route('users.index'))->assertOk();
});

it('creates a user and assigns roles', function () {
    actingAsSuperAdmin();

    $this->post(route('users.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '01700000000',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
        'status' => 'active',
        'roles' => ['Manager'],
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'john@example.com')->first();

    $this->assertNotNull($user);
    $this->assertTrue($user->hasRole('Manager'));
});

it('rejects creating a user with a duplicate email', function () {
    actingAsSuperAdmin();

    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('users.store'), [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
        'status' => 'active',
    ])->assertSessionHasErrors('email');
});

it('prevents a user from deactivating themselves', function () {
    $admin = actingAsSuperAdmin();

    $this->patch(route('users.toggle-status', $admin))->assertForbidden();
});

it('prevents deleting the Super Admin role holder', function () {
    $admin = actingAsSuperAdmin();

    $otherSuperAdmin = User::factory()->create();
    $otherSuperAdmin->assignRole('Super Admin');

    $this->delete(route('users.destroy', $otherSuperAdmin))->assertForbidden();
});
