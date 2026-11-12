<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('forbids users without the settings permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
});

it('renders the settings page for a Super Admin', function () {
    actingAsSuperAdmin();

    $this->get(route('settings.index'))->assertOk();
});

it('updates a setting value', function () {
    actingAsSuperAdmin();

    $this->patch(route('settings.update'), [
        'settings' => [
            'company_name' => 'Updated Company Ltd.',
        ],
    ])->assertRedirect();

    $this->assertSame('Updated Company Ltd.', Setting::get('company_name'));
});

it('normalizes the allow_negative_stock toggle to a stored string', function () {
    actingAsSuperAdmin();

    $this->patch(route('settings.update'), [
        'settings' => [
            'allow_negative_stock' => '1',
        ],
    ])->assertRedirect();

    $this->assertSame('1', Setting::get('allow_negative_stock'));
});
