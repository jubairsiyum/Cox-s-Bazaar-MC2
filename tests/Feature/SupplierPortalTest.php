<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SupplierAccountSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active login holding the Supplier role.
 */
function supplierLogin(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'email' => 'supplier@cberp.local',
        'password' => 'password@123',
        'status' => 'active',
    ], $attributes));

    $user->assignRole('Supplier');

    return $user;
}

it('no longer offers a supplier profile action in the supplier list', function () {
    actingAsSuperAdmin();

    Supplier::factory()->create(['name' => 'Bismillah Telecom']);

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertDontSee('<span>Supplier Profile</span>', false)
        ->assertSee('Edit &amp; Payment Ledger', false);
});

it('offers the Supplier role on the Add User form with a supplier name field', function () {
    actingAsSuperAdmin();

    $this->get(route('users.create'))
        ->assertOk()
        ->assertSee('value="Supplier"', false)
        ->assertSee('name="supplier_name"', false);
});

it('creates a linked supplier profile when a Supplier-role user is added', function () {
    actingAsSuperAdmin();

    $this->post(route('users.store'), [
        'name' => 'Matin Traders',
        'email' => 'matin@example.test',
        'phone' => '01711000000',
        'status' => 'active',
        'password' => 'password@123',
        'password_confirmation' => 'password@123',
        'roles' => ['Supplier'],
        'supplier_name' => 'Matin Traders Ltd',
    ])->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'matin@example.test')->firstOrFail();
    $supplier = $user->supplier;

    expect($user->hasRole('Supplier'))->toBeTrue()
        ->and($supplier)->not->toBeNull()
        ->and($supplier->name)->toBe('Matin Traders Ltd')
        ->and($supplier->mobile)->toBe('01711000000')
        ->and($supplier->email)->toBe('matin@example.test');
});

it('requires a supplier name when the Supplier role is selected', function () {
    actingAsSuperAdmin();

    $this->post(route('users.store'), [
        'name' => 'Matin Traders',
        'email' => 'matin@example.test',
        'status' => 'active',
        'password' => 'password@123',
        'password_confirmation' => 'password@123',
        'roles' => ['Supplier'],
    ])->assertSessionHasErrors('supplier_name');

    $this->assertDatabaseMissing('users', ['email' => 'matin@example.test']);
});

it('logs a supplier in with email and password and opens their own profile', function () {
    $supplierUser = supplierLogin();
    Supplier::factory()->create(['name' => 'Realme Hub Bangladesh', 'user_id' => $supplierUser->id]);

    $this->post(route('login'), [
        'email' => 'supplier@cberp.local',
        'password' => 'password@123',
    ])->assertRedirect(route('suppliers.my-profile'));

    $this->get(route('suppliers.my-profile'))
        ->assertRedirect(route('suppliers.my-profile.section', 'details'));

    $this->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk()
        ->assertSee('Realme Hub Bangladesh');

    $this->get(route('suppliers.my-profile.section', 'activity'))
        ->assertOk()
        ->assertSee('Notifications &amp; Activity', false);
});

it('keeps a supplier login to its own profile and read-only actions', function () {
    $supplierUser = supplierLogin();
    $ownSupplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);
    $otherSupplier = Supplier::factory()->create();

    $this->actingAs($supplierUser)
        ->get(route('suppliers.profile', $otherSupplier))
        ->assertForbidden();

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk()
        ->assertSee($ownSupplier->name);

    $this->actingAs($supplierUser)
        ->get(route('suppliers.index'))
        ->assertForbidden();
});

it('refuses a supplier login that has no linked supplier profile', function () {
    $this->actingAs(supplierLogin())
        ->get(route('suppliers.my-profile'))
        ->assertForbidden();
});

it('seeds the Realme Hub Bangladesh supplier login linked to its existing profile', function () {
    $existing = Supplier::factory()->create(['name' => 'Realme Hub Bangladesh']);

    $this->seed(SupplierAccountSeeder::class);

    $user = User::query()->where('email', 'supplier@cberp.local')->firstOrFail();

    expect(Hash::check('password@123', $user->password))->toBeTrue()
        ->and($user->hasRole('Supplier'))->toBeTrue()
        ->and(Supplier::query()->where('name', 'Realme Hub Bangladesh')->count())->toBe(1)
        ->and($existing->fresh()->user_id)->toBe($user->id);
});
