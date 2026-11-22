<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists locations for an authorized user', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create(['name' => 'Main Warehouse']);

    $this->get(route('branches.index'))
        ->assertOk()
        ->assertSee('Main Warehouse')
        ->assertSee($location->code);
});

it('shows the create location form', function () {
    actingAsSuperAdmin();

    $this->get(route('branches.create'))
        ->assertOk()
        ->assertSee('Branch Name')
        ->assertSee('Branch Code');
});

it('creates a location', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('branches.store'), [
        'name' => 'Brand Shop - Cox\'s Bazar',
        'code' => 'BS01',
        'type' => 'brand_shop',
        'status' => 'active',
        'address' => 'Hotel Motel Zone',
        'phone' => '01700000000',
        'note' => 'Company outlet',
    ]);

    $response->assertRedirect(route('branches.index'));

    $this->assertDatabaseHas('locations', [
        'name' => 'Brand Shop - Cox\'s Bazar',
        'code' => 'BS01',
        'type' => 'brand_shop',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Location::class,
    ]);
});

it('validates required fields when creating a location', function () {
    actingAsSuperAdmin();

    $this->post(route('branches.store'), [])
        ->assertSessionHasErrors(['name', 'type']);
});

it('rejects a duplicate location code', function () {
    actingAsSuperAdmin();

    Location::factory()->create(['code' => 'WH01']);

    $this->post(route('branches.store'), [
        'name' => 'Second Warehouse',
        'code' => 'WH01',
        'type' => 'warehouse',
    ])->assertSessionHasErrors('code');
});

it('restores an archived location when the same code is used again', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create(['code' => 'WH-01']);
    $location->delete();

    $this->post(route('branches.store'), [
        'name' => 'Main Warehouse',
        'code' => 'WH-01',
        'type' => 'warehouse',
    ])->assertRedirect(route('branches.index'));

    $restored = Location::where('code', 'WH-01')->first();

    expect($restored)->not->toBeNull()
        ->and($restored->id)->toBe($location->id)
        ->and($restored->trashed())->toBeFalse()
        ->and(Location::withTrashed()->where('code', 'WH-01')->count())->toBe(1);
});

it('updates a location', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();

    $this->patch(route('branches.update', $location), [
        'name' => 'Updated Location',
        'code' => $location->code,
        'type' => 'sr_placement',
        'status' => 'inactive',
    ])->assertRedirect(route('branches.index'));

    $this->assertDatabaseHas('locations', [
        'id' => $location->id,
        'name' => 'Updated Location',
        'type' => 'sr_placement',
        'status' => 'inactive',
    ]);
});

it('deletes a location', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();

    $this->delete(route('branches.destroy', $location))
        ->assertRedirect(route('branches.index'));

    $this->assertSoftDeleted('locations', ['id' => $location->id]);
});

it('denies access without the branches.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales Staff');

    $this->actingAs($user)->get(route('branches.index'))->assertForbidden();
});
