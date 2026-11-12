<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('renders the login page', function () {
    $this->get(route('login'))->assertOk();
});

it('redirects authenticated users away from the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
});

it('logs in a user with valid credentials', function () {
    $user = User::factory()->create(['password' => 'Password@123']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects a deactivated account even with valid credentials', function () {
    $user = User::factory()->create(['password' => 'Password@123', 'status' => 'inactive']);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Password@123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
