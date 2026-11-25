<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

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

it('caches Setting::get lookups and invalidates cache when Setting::set updates a key', function () {
    Setting::set('company_name', 'Initial Co');

    // Warm cache on first lookup
    expect(Setting::get('company_name'))->toBe('Initial Co');

    DB::flushQueryLog();
    DB::enableQueryLog();
    expect(Setting::get('company_name'))->toBe('Initial Co')
        ->and(Setting::get('company_name'))->toBe('Initial Co');
    $cachedQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($cachedQueries)->toBe(0);

    // Setting::set must invalidate cache immediately
    Setting::set('company_name', 'Updated Cached Co');
    expect(Setting::get('company_name'))->toBe('Updated Cached Co');

    // Missing key returns each caller default consistently via null sentinel
    expect(Setting::get('nonexistent_setting_key', 'fallback_a'))->toBe('fallback_a')
        ->and(Setting::get('nonexistent_setting_key', 'fallback_b'))->toBe('fallback_b');
});
