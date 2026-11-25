<?php

use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

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

it('lists the branches (outlet locations) in the add user form', function () {
    actingAsSuperAdmin();

    Location::factory()->create(['name' => 'Sadar Outlet']);
    Location::factory()->create(['name' => 'Chakaria Outlet']);

    $this->get(route('users.create'))
        ->assertOk()
        ->assertSee('Sadar Outlet')
        ->assertSee('Chakaria Outlet');
});

it('creates a user with roles and a branch assignment', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->create(['name' => 'Sadar Outlet']);

    $this->post(route('users.store'), [
        'name' => 'Branch User',
        'email' => 'branch-user@example.com',
        'phone' => '01700000001',
        'password' => 'Password@123',
        'password_confirmation' => 'Password@123',
        'status' => 'active',
        'branch_id' => $branch->id,
        'roles' => ['Outlet Manager'],
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'branch-user@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->branch_id)->toBe($branch->id)
        ->and($user->hasRole('Outlet Manager'))->toBeTrue();
});

it('offers a single Branch field and no location field on the user forms', function () {
    actingAsSuperAdmin();

    $branch = Location::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ([route('users.create'), route('users.edit', $user)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('name="branch_id"', false)
            ->assertDontSee('name="location_id"', false);
    }
});

it('shows the branch on the users list and filters users by branch', function () {
    actingAsSuperAdmin();

    $sadar = Location::factory()->create(['name' => 'Sadar Outlet']);
    $chakaria = Location::factory()->create(['name' => 'Chakaria Outlet']);

    $sadarUser = User::factory()->create(['name' => 'Sadar Staff', 'branch_id' => $sadar->id]);
    $chakariaUser = User::factory()->create(['name' => 'Chakaria Staff', 'branch_id' => $chakaria->id]);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('<td data-label="Branch">Sadar Outlet</td>', false)
        ->assertSee('<td data-label="Branch">Chakaria Outlet</td>', false);

    $this->get(route('users.index', ['branch_id' => $sadar->id]))
        ->assertOk()
        ->assertSee('name="branch_id"', false)
        ->assertSee($sadarUser->name)
        ->assertDontSee($chakariaUser->name);
});

it('automatically verifies provisioned accounts created outside the admin UI', function () {
    // e.g. created via tinker or an ad-hoc seeder: still trusted, never walled.
    $user = User::create([
        'name' => 'Outlet Manager X',
        'email' => 'outlet-x@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
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

it('blocks a branch manager from editing Super Admin or escalating roles', function () {
    $this->seed(SettingsSeeder::class);

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
