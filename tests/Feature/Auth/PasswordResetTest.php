<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

it('renders the password reset request page', function () {
    $this->get(route('password.request'))->assertOk();
});

it('sends a reset link email to an existing user', function () {
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email is registered', function () {
    $this->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('renders the reset password page for a valid token', function () {
    $user = User::factory()->create();

    $token = Password::broker()->createToken($user);

    $this->get(route('password.reset', ['token' => $token]))
        ->assertOk();
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertRedirect(route('login'));

    $this->assertTrue(Hash::check('NewPassword@123', $user->fresh()->password));
});

it('rejects a password reset with an invalid token', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'NewPassword@123',
        'password_confirmation' => 'NewPassword@123',
    ])->assertSessionHasErrors('email');
});
