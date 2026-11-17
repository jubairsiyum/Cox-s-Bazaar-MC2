<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

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

it('shows a storekeeper only the sections matching their permissions', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Brands')
        ->assertSee('Stock Levels')
        ->assertSee('Purchase Invoices')
        ->assertDontSee('Purchase Orders')
        ->assertDontSee('Sales Orders')
        ->assertDontSee('Collections')
        ->assertDontSee('Reports');
});

it('blocks a storekeeper from sales, reports and pos pages directly', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole('Storekeeper');

    $this->actingAs($storekeeper);

    foreach (['sales-orders.index', 'sales-invoices.index', 'sales-returns.index', 'customer-collections.index', 'reports.sales', 'pos.index'] as $routeName) {
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
