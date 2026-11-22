<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('gates every main nav item behind a permission except the dashboard', function () {
    $ungated = collect(MenuHelper::getMainNavItems())
        ->reject(fn (array $item) => $item['name'] === 'Dashboard')
        ->reject(fn (array $item) => isset($item['can']) || isset($item['canany']));

    expect($ungated->pluck('name')->all())->toBeEmpty();
});

it('gates every menu sub-item behind a permission', function () {
    $ungated = collect(MenuHelper::getMainNavItems())
        ->flatMap(fn (array $item) => $item['subItems'] ?? [])
        ->reject(fn (array $subItem) => isset($subItem['can']) || isset($subItem['canany']));

    expect($ungated->pluck('name')->all())->toBeEmpty();
});

/**
 * Menu visibility is asserted against the sidebar markup only: dashboard
 * widgets reuse words like "Collections" and the sidebar's inline script
 * lists every menu path, so whole-page matching false-positives.
 */
it('shows a storekeeper only the sections matching their permissions', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $response = $this->actingAs($storekeeper)->get(route('dashboard'))->assertOk();

    $sidebar = Str::between($response->getContent(), '<aside', '</aside>');

    expect($sidebar)
        ->toContain('Brands')
        ->toContain('Stocks')
        ->toContain('Purchase Invoices')
        ->not->toContain('Purchase Orders')
        ->not->toContain('Sales Orders')
        ->not->toContain('Collections')
        ->not->toContain('Reports');
});

it('blocks a storekeeper from sales, reports and pos pages directly', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper);

    foreach (['sales-orders.index', 'sales-invoices.index', 'sales-returns.index', 'collections.index', 'reports.sales', 'pos.index'] as $routeName) {
        $this->get(route($routeName))->assertForbidden();
    }
});

it('allows sales staff into the pos and reports pages', function () {
    $salesStaff = User::factory()->create();
    $salesStaff->assignRole('Sales Staff');

    $this->actingAs($salesStaff);

    $this->get(route('pos.index'))->assertOk();
    $this->get(route('reports.sales'))->assertOk();
});

it('enforces per-page permissions for placeholders', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant);

    $this->get(route('sales-invoices.index'))->assertOk();
    $this->get(route('sales-orders.index'))->assertForbidden();
    $this->get(route('purchase-orders.index'))->assertForbidden();
});
