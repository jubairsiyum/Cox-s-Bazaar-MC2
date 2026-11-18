<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('lists the notifications of the logged-in user in the notification center', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($user, 'Stock transfer awaits your approval', 'TRF-1 → Outlet Store', '/stock-transfers/1', 'warning');

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Stock transfer awaits your approval')
        ->assertSee('TRF-1')
        ->assertSee('unread');
});

it('renders the header notification dropdown with real user data', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($user, 'Adjustment approved', 'ADJ-9 applied', '/stock-adjustments', 'success');

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Adjustment approved')
        ->assertSee('Mark all as read');
});

it('marks a single notification as read', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($user, 'Hello', 'Body', null, 'info');

    $notification = $user->notifications()->first();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($user->notifications()->first()->read_at)->not->toBeNull();
});

it('marks a single notification as read over json', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($user, 'Hello', 'Body', null, 'info');

    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->patchJson(route('notifications.read', $notification->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->notifications()->first()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notifyMany([$user, $user], 'First', 'Body', null, 'warning');
    NotificationService::notify($user, 'Second', 'Body', null, 'info');

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'));

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('filters unread notifications', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($user, 'Unread one', 'Body', null, 'warning');
    NotificationService::notify($user, 'Read one', 'Body', null, 'info');

    $user->notifications()->get()
        ->firstWhere('data.title', 'Read one')
        ->markAsRead();

    $this->actingAs($user)
        ->get(route('notifications.index', ['filter' => 'unread']))
        ->assertOk()
        ->assertSee('Unread one')
        ->assertViewHas('notifications', function ($notifications) {
            return $notifications->count() === 1
                && $notifications->first()->data['title'] === 'Unread one';
        });
});

it('only exposes the notifications of the logged-in user', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);

    NotificationService::notify($owner, 'Private note', 'Body', null, 'info');

    $notification = $owner->notifications()->first();

    $this->actingAs($other)
        ->patch(route('notifications.read', $notification->id))
        ->assertNotFound();

    $this->actingAs($other)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee('Private note');

    expect($owner->notifications()->first()->read_at)->toBeNull();
});

it('requires authentication for the notification center', function () {
    $this->get(route('notifications.index'))->assertRedirect('/login');
});
