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

    $this->get(route('warehouses.index'))
        ->assertOk()
        ->assertSee('Main Warehouse')
        ->assertSee($location->code);
});

it('shows the create location form', function () {
    actingAsSuperAdmin();

    $this->get(route('warehouses.create'))
        ->assertOk()
        ->assertSee('Location Name')
        ->assertSee('Location Code');
});

it('creates a location', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('warehouses.store'), [
        'name' => 'Brand Shop - Cox\'s Bazar',
        'code' => 'BS01',
        'type' => 'brand_shop',
        'status' => 'active',
        'address' => 'Hotel Motel Zone',
        'phone' => '01700000000',
        'note' => 'Company outlet',
    ]);

    $response->assertRedirect(route('warehouses.index'));

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

    $this->post(route('warehouses.store'), [])
        ->assertSessionHasErrors(['name', 'type']);
});

it('rejects a duplicate location code', function () {
    actingAsSuperAdmin();

    Location::factory()->create(['code' => 'WH01']);

    $this->post(route('warehouses.store'), [
        'name' => 'Second Warehouse',
        'code' => 'WH01',
        'type' => 'warehouse',
    ])->assertSessionHasErrors('code');
});

it('updates a location', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();

    $this->patch(route('warehouses.update', $location), [
        'name' => 'Updated Location',
        'code' => $location->code,
        'type' => 'sr_placement',
        'status' => 'inactive',
    ])->assertRedirect(route('warehouses.index'));

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

    $this->delete(route('warehouses.destroy', $location))
        ->assertRedirect(route('warehouses.index'));

    $this->assertSoftDeleted('locations', ['id' => $location->id]);
});

it('denies access without the warehouses.manage permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales Staff');

    $this->actingAs($user)->get(route('warehouses.index'))->assertForbidden();
});
