<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the dashboard for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Stock Value')
        ->assertSee('Sales & Collection Trend')
        ->assertSee('Low Stock & Reorder Alerts')
        ->assertSee('Recent Distribution Invoices');
});

it('renders tailadmin showcase routes for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('ecommerce'))->assertOk();
    $this->actingAs($user)->get(route('calendar'))->assertOk();
    $this->actingAs($user)->get(route('alerts'))->assertOk();
    $this->actingAs($user)->get(route('buttons'))->assertOk();
});

it('attaches security headers to authenticated ERP responses', function () {
    actingAsSuperAdmin();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
