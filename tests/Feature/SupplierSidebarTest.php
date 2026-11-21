<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active login holding the Supplier role, linked to its own supplier profile.
 */
function supplierSidebarLogin(): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    Supplier::factory()->create(['user_id' => $user->id]);

    return $user;
}

function sidebarMarkup(string $content): string
{
    return Str::between($content, '<aside', '</aside>');
}

it('does not show My Profile in the sidebar of a supplier login', function () {
    $supplierUser = supplierSidebarLogin();

    $response = $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk();

    expect(sidebarMarkup($response->getContent()))
        ->not->toContain('My Profile')
        ->not->toContain('href="/my-supplier-profile"');
});

it('keeps a supplier login sidebar to the profile sections and Notifications', function () {
    $supplierUser = supplierSidebarLogin();

    $response = $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk();

    $sidebar = sidebarMarkup($response->getContent());

    expect($sidebar)
        ->not->toContain('My Profile')
        ->toContain('Notifications')
        ->toContain('Log Out')
        ->not->toContain('Dashboard')
        ->not->toContain('Purchasing')
        ->not->toContain('Purchase Invoices')
        ->not->toContain('Ecommerce Demo')
        ->not->toContain('Administration')
        ->not->toContain('Settings');
});

it('does not show My Profile in the sidebar of a super admin', function () {
    $admin = actingAsSuperAdmin();

    $response = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    expect(sidebarMarkup($response->getContent()))
        ->not->toContain('My Profile')
        ->not->toContain('href="/my-supplier-profile"')
        ->toContain('Purchasing');
});

it('does not show My Profile in the sidebar of a staff login without the supplier role', function () {
    $storekeeper = User::factory()->create(['email_verified_at' => now()]);
    $storekeeper->assignRole('Storekeeper');

    $response = $this->actingAs($storekeeper)
        ->get(route('dashboard'))
        ->assertOk();

    expect(sidebarMarkup($response->getContent()))
        ->not->toContain('My Profile')
        ->toContain('Brands');
});

it('lets a supplier login open their profile sections and notifications pages', function () {
    $supplierUser = supplierSidebarLogin();

    $this->actingAs($supplierUser)->get('/my-supplier-profile')->assertRedirect('/my-supplier-profile/details');
    $this->actingAs($supplierUser)->get('/my-supplier-profile/details')->assertOk();
    $this->actingAs($supplierUser)->get(route('notifications.index'))->assertOk();
});

it('lists only the notifications addressed to the supplier login', function () {
    $supplierUser = supplierSidebarLogin();
    $otherUser = User::factory()->create(['email_verified_at' => now()]);

    $supplierUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\SupplierAlert',
        'data' => ['title' => 'Own supplier alert', 'type' => 'info'],
    ]);
    $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\SupplierAlert',
        'data' => ['title' => 'Someone else alert', 'type' => 'info'],
    ]);

    $this->actingAs($supplierUser)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Own supplier alert')
        ->assertDontSee('Someone else alert');
});

it('keeps the notifications breadcrumb of a supplier login free of staff sections', function () {
    $supplierUser = supplierSidebarLogin();

    $response = $this->actingAs($supplierUser)
        ->get(route('notifications.index'))
        ->assertOk();

    expect($response->getContent())
        ->not->toContain('>Administration<');
});
