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
 * @return array<string, string>
 */
function supplierProfileSectionLabels(): array
{
    return [
        'details' => 'Overview',
        'dues' => 'Branch Dues',
        'orders' => 'Purchase Orders',
        'payments' => 'Payments',
        'returns' => 'Returns',
        'requests' => 'Payment Requests',
        'activity' => 'Activity',
    ];
}

function supplierProfileSidebarMarkup(string $content): string
{
    return Str::between($content, '<aside', '</aside>');
}

it('lists every profile section in the sidebar of a supplier login as its own page', function () {
    $supplierUser = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $supplierUser->assignRole('Supplier');
    Supplier::factory()->create(['user_id' => $supplierUser->id]);

    $response = $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk();

    $sidebar = supplierProfileSidebarMarkup($response->getContent());

    foreach (supplierProfileSectionLabels() as $section => $label) {
        expect($sidebar)
            ->toContain('href="/my-supplier-profile/'.$section.'"')
            ->toContain('>'.$label.'<');
    }

    expect($sidebar)->not->toContain('#details');
});

it('does not show the profile sections in the sidebar of other pages', function () {
    $supplierUser = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $supplierUser->assignRole('Supplier');
    Supplier::factory()->create(['user_id' => $supplierUser->id]);

    $supplierSidebar = supplierProfileSidebarMarkup(
        $this->actingAs($supplierUser)->get(route('notifications.index'))->assertOk()->getContent()
    );

    $admin = actingAsSuperAdmin();
    $dashboardSidebar = supplierProfileSidebarMarkup(
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent()
    );

    foreach (array_keys(supplierProfileSectionLabels()) as $section) {
        expect($supplierSidebar)->toContain('href="/my-supplier-profile/'.$section.'"');
        expect($dashboardSidebar)->not->toContain('/my-supplier-profile/'.$section.'"');
    }

    expect($dashboardSidebar)->not->toContain('Branch Dues');
});

it('lists the profile sections pointing at the supplier profile anchors for staff', function () {
    $admin = actingAsSuperAdmin();
    $supplier = Supplier::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk();

    $sidebar = supplierProfileSidebarMarkup($response->getContent());

    expect($sidebar)->toContain('Branch Dues');

    foreach (array_keys(supplierProfileSectionLabels()) as $anchor) {
        expect($sidebar)->toContain('href="'.route('suppliers.profile', $supplier).'#'.$anchor.'"');
    }
});
