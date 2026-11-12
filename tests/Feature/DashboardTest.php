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
        ->assertSee('Welcome back')
        ->assertSee('Total Users')
        ->assertSee('Active Users')
        ->assertSee('Recently Added Users');
});

it('renders tailadmin showcase routes for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('ecommerce'))->assertOk();
    $this->actingAs($user)->get(route('calendar'))->assertOk();
    $this->actingAs($user)->get(route('alerts'))->assertOk();
    $this->actingAs($user)->get(route('buttons'))->assertOk();
});
