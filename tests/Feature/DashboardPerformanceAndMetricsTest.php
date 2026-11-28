<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('redirects unauthenticated requests to dashboard metrics', function () {
    $this->get(route('dashboard.metrics'))
        ->assertRedirect(route('login'));
});

it('returns scoped JSON metrics with no-store cache headers for super admin', function () {
    $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    $branch = Location::factory()->brandShop()->create(['name' => 'Marine Drive Shop']);

    $response = $this->actingAs($admin)
        ->getJson(route('dashboard.metrics', ['location_id' => $branch->id]))
        ->assertOk()
        ->assertJsonStructure([
            'scoped_location_id',
            'is_outlet_scope',
            'today_sales',
            'today_collection',
            'total_stock_value',
            'payable_summary',
            'network_payable',
            'as_of',
            'as_of_formatted',
        ])
        ->assertJsonPath('scoped_location_id', $branch->id)
        ->assertJsonPath('is_outlet_scope', true);

    $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
    expect($cacheControl)
        ->toContain('no-store')
        ->toContain('private');
});

it('forbids outlet managers from querying another branch metrics', function () {
    $ownBranch = Location::factory()->create(['name' => 'Teknaf Branch']);
    $otherBranch = Location::factory()->create(['name' => 'Ukhiya Branch']);

    $manager = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
        'branch_id' => $ownBranch->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager)
        ->getJson(route('dashboard.metrics'))
        ->assertOk()
        ->assertJsonPath('scoped_location_id', $ownBranch->id);

    $this->actingAs($manager)
        ->getJson(route('dashboard.metrics', ['location_id' => $otherBranch->id]))
        ->assertForbidden();
});

it('forbids supplier portal users from accessing dashboard metrics', function () {
    $supplierUser = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $supplierUser->assignRole('Supplier');
    Supplier::factory()->create(['user_id' => $supplierUser->id]);

    $this->actingAs($supplierUser)
        ->getJson(route('dashboard.metrics'))
        ->assertForbidden();
});

it('validates location_id existence on dashboard metrics', function () {
    $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->getJson(route('dashboard.metrics', ['location_id' => 999999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id']);
});

it('rate limits excessive dashboard metrics polling', function () {
    $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($admin)
            ->getJson(route('dashboard.metrics'))
            ->assertOk();
    }

    $this->actingAs($admin)
        ->getJson(route('dashboard.metrics'))
        ->assertStatus(429);
});

it('renders expanded active sidebar group and immediate dashboard KPIs in SSR HTML', function () {
    $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    $brandsResponse = $this->actingAs($admin)
        ->get(route('brands.index'))
        ->assertOk();

    $brandsHtml = $brandsResponse->getContent();
    $aside = Str::between($brandsHtml, '<aside', '</aside>');

    expect($brandsHtml)->toContain('lg:w-64');
    expect($aside)
        ->toContain('Master Data')
        ->toContain('bg-soft text-brand-500')
        ->not->toContain('enter mv-left');

    $dashboardResponse = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    $dashboardHtml = $dashboardResponse->getContent();
    expect($dashboardHtml)
        ->toContain('id="nav-progress"')
        ->toContain('As of')
        ->toContain('gauge')
        ->toContain('x-count')
        ->toContain('rv mv-blur');
});
